//! Database job and transactional-outbox worker loops.

use kinevo_db::{
    Db, SystemTx,
    jobs::{self, JobContext, JobHandler, JobKind, RetryPolicy, WorkerId},
    outbox::{self, SubscriberSet},
};
use kinevo_kernel::{
    errors::ErrorCode,
    id::IdGen,
    policy::{PolicyDuration, registry},
    time::Clock,
};
use kinevo_mod_admin::{
    MODULE as ADMIN, app::modules::ModuleService, app::policies::PolicyService,
};
use kinevo_mod_identity::{
    MODULE as IDENTITY,
    app::export::{ExportService, IdentityExporter},
};
use kinevo_ports::{module::ModuleDescriptor, platform::ModuleSwitches};
use std::{collections::BTreeMap, future::Future, sync::Arc, time::Duration};
use tokio::{sync::watch, time};

/// Runs module-owned jobs, recurring schedules and outbox subscribers until shutdown.
pub async fn run(
    db: Db,
    clock: Arc<dyn Clock>,
    ids: Arc<dyn IdGen>,
    tick: Duration,
    shutdown: impl Future<Output = ()> + Send + 'static,
) {
    let descriptors = vec![&IDENTITY, &ADMIN];
    let policies = Arc::new(PolicyService::new(
        db.clone(),
        Arc::clone(&ids),
        Arc::clone(&clock),
        outbox::SubscriberRegistry::default(),
    ));
    let module_service = Arc::new(ModuleService::new(
        db.clone(),
        descriptors.clone(),
        Arc::clone(&ids),
        Arc::clone(&clock),
        policies.reader(),
        outbox::SubscriberRegistry::default(),
    ));
    let switches: Arc<dyn ModuleSwitches> = Arc::clone(module_service.switches()) as _;
    let exporter: Arc<dyn kinevo_ports::module::AccountExporter<kinevo_db::Tx>> =
        Arc::new(IdentityExporter);
    let exports = if let Ok(exports) = ExportService::new(
        db.clone(),
        Arc::clone(&ids),
        Arc::clone(&clock),
        policies.reader(),
        vec![exporter],
    ) {
        Arc::new(exports)
    } else {
        tracing::error!(target: "kinevo::worker", code = "config_invalid", "worker initialization failed");
        return;
    };
    let mut handlers = kinevo_mod_identity::jobs_with_exports(exports);
    handlers.extend(kinevo_mod_admin::jobs(policies.reader()));
    let handler_by_kind = handlers
        .iter()
        .map(|handler| (handler.kind().as_str(), Arc::clone(handler)))
        .collect::<BTreeMap<_, _>>();
    let subscribers = SubscriberSet::new(kinevo_mod_admin::subscribers_with_modules(
        &policies,
        &module_service,
    ));
    let worker = WorkerId::new(ids.new_uuid_v7().to_string());
    let (shutdown_tx, mut shutdown_rx) = watch::channel(false);
    let shutdown_tx_task = shutdown_tx.clone();
    tokio::spawn(async move {
        shutdown.await;
        let _ = shutdown_tx_task.send(true);
    });
    let mut interval = time::interval(tick.max(Duration::from_millis(1)));
    interval.set_missed_tick_behavior(time::MissedTickBehavior::Skip);

    loop {
        tokio::select! {
            _ = shutdown_rx.changed() => break,
            _ = interval.tick() => {
                if let Err(error) = run_once(
                    &db,
                    &clock,
                    &ids,
                    &worker,
                    &descriptors,
                    switches.as_ref(),
                    &handler_by_kind,
                    &subscribers,
                    policies.reader().as_ref(),
                ).await {
                    tracing::warn!(target: "kinevo::worker", code = error.as_str(), "worker tick failed");
                }
            }
        }
    }
}

#[allow(
    clippy::too_many_arguments,
    reason = "each worker dependency is explicit and injected from the composition root"
)]
async fn run_once(
    db: &Db,
    clock: &Arc<dyn Clock>,
    ids: &Arc<dyn IdGen>,
    worker: &WorkerId,
    descriptors: &[&'static ModuleDescriptor],
    switches: &dyn ModuleSwitches,
    handlers: &BTreeMap<&'static str, Arc<dyn JobHandler>>,
    subscribers: &SubscriberSet,
    policies: &dyn kinevo_ports::platform::PolicyReader,
) -> Result<(), ErrorCode> {
    switches.refresh().await;
    let now = clock.now();
    let values = policies
        .snapshot_values(None, now)
        .await
        .map_err(|error| error.code)?;
    let PolicyDuration(lease_seconds) = values
        .get(&registry::platform::JOB_LEASE_SECONDS)
        .ok_or(ErrorCode::InternalError)?;
    let PolicyDuration(base_seconds) = values
        .get(&registry::platform::JOB_RETRY_BASE_SECONDS)
        .ok_or(ErrorCode::InternalError)?;
    let PolicyDuration(cap_seconds) = values
        .get(&registry::platform::JOB_RETRY_CAP_SECONDS)
        .ok_or(ErrorCode::InternalError)?;
    let max_attempts = values
        .get(&registry::platform::JOB_RETRY_MAX_ATTEMPTS)
        .and_then(|value| u8::try_from(value).ok())
        .ok_or(ErrorCode::InternalError)?;
    let retry = RetryPolicy {
        base_seconds,
        cap_seconds,
    };
    materialise_schedules(
        db,
        ids.as_ref(),
        now,
        max_attempts,
        descriptors,
        switches,
        &values,
    )
    .await?;

    let enabled = descriptors
        .iter()
        .filter(|descriptor| {
            descriptor.kind == kinevo_ports::module::ModuleKind::Core
                || kinevo_ports::module::ModuleKey::parse(descriptor.key)
                    .is_ok_and(|key| switches.is_enabled(&key))
        })
        .flat_map(|descriptor| descriptor.jobs.iter().copied())
        .filter(|kind| handlers.contains_key(kind))
        .filter_map(|kind| JobKind::new(kind).ok())
        .collect::<Vec<_>>();
    let mut claim = SystemTx::begin(db).await.map_err(|error| error.code)?;
    let claimed = jobs::claim(&mut claim, worker, now, lease_seconds, 16, &enabled)
        .await
        .map_err(|error| error.code)?;
    claim.commit().await.map_err(|error| error.code)?;

    let context = JobContext::new(Arc::new(db.clone()), Arc::clone(ids), Arc::clone(clock));
    for job in claimed {
        let result = match handlers.get(job.kind.as_str()) {
            Some(handler) => handler.run(context.clone(), job.clone()).await,
            None => Err(kinevo_kernel::errors::AppError::new(
                ErrorCode::ServiceUnavailable,
            )),
        };
        let mut tx = SystemTx::begin(db).await.map_err(|error| error.code)?;
        match result {
            Ok(()) => jobs::complete(&mut tx, job.id, clock.now())
                .await
                .map_err(|error| error.code)?,
            Err(error) => jobs::fail(&mut tx, job.id, clock.now(), error.code, retry)
                .await
                .map_err(|error| error.code)?,
        }
        tx.commit().await.map_err(|error| error.code)?;
    }

    outbox::dispatch_once(
        db,
        subscribers,
        clock.now(),
        retry,
        u32::from(max_attempts),
        32,
    )
    .await
    .map_err(|error| error.code)?;
    Ok(())
}

async fn materialise_schedules(
    db: &Db,
    ids: &dyn IdGen,
    now: kinevo_kernel::time::Instant,
    max_attempts: u8,
    descriptors: &[&'static ModuleDescriptor],
    switches: &dyn ModuleSwitches,
    values: &kinevo_ports::platform::ResolvedPolicies,
) -> Result<(), ErrorCode> {
    let mut slots = Vec::new();
    for descriptor in descriptors {
        if descriptor.kind != kinevo_ports::module::ModuleKind::Core
            && !kinevo_ports::module::ModuleKey::parse(descriptor.key)
                .is_ok_and(|key| switches.is_enabled(&key))
        {
            continue;
        }
        for schedule in descriptor.job_schedules {
            let cadence = match schedule.cadence_policy_key {
                "jobs.schedule.daily_seconds" => values
                    .get(&registry::platform::JOB_SCHEDULE_DAILY_SECONDS)
                    .map(|PolicyDuration(seconds)| seconds),
                "jobs.schedule.hourly_seconds" => values
                    .get(&registry::platform::JOB_SCHEDULE_HOURLY_SECONDS)
                    .map(|PolicyDuration(seconds)| seconds),
                _ => None,
            }
            .filter(|seconds| *seconds > 0)
            .ok_or(ErrorCode::InternalError)?;
            let cadence_millis = cadence.checked_mul(1_000).ok_or(ErrorCode::InternalError)?;
            let scheduled_millis = now.unix_millis().div_euclid(cadence_millis) * cadence_millis;
            let scheduled_for = kinevo_kernel::time::Instant::from_unix_millis(scheduled_millis)
                .map_err(|_| ErrorCode::InternalError)?;
            slots.push(jobs::ScheduleSlot {
                kind: JobKind::new(schedule.kind).map_err(|error| error.code)?,
                account_id: None,
                scheduled_for,
            });
        }
    }
    let mut tx = SystemTx::begin(db).await.map_err(|error| error.code)?;
    jobs::materialise(&mut tx, ids, now, max_attempts, &slots)
        .await
        .map_err(|error| error.code)?;
    tx.commit().await.map_err(|error| error.code)
}
