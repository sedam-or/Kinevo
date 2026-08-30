SHELL := /usr/bin/env bash

PACK ?= KINEVO_DOCUMENTATION_MASTER_PACK.md
SRS ?= KINEVO_SRS_v2.0.0.md

COMPOSE := docker compose -f infrastructure/docker-compose.yml
APP := $(COMPOSE) exec -T app
COMPOSE_PROD := docker compose -f infrastructure/docker-compose.prod.yml

.PHONY: setup setup-force dry-run validate secrets status doctor \
	up down logs migrate shell \
	test lint format analyse ci \
	frontend-install frontend-typecheck frontend-test frontend-build \
	check-links check-openapi \
	version version-check changelog-check release-check release-dry-run release-prepare \
	ollama-up ollama-down ai-status ai-smoke \
	prod-build prod-up prod-down prod-migrate prod-logs prod-certbot \
	prod-backup prod-backup-list prod-restore prod-smoke

# --- Documentation bootstrap -------------------------------------------------
setup:
	./scripts/bootstrap-docs.sh --pack "$(PACK)" --srs "$(SRS)"

setup-force:
	./scripts/bootstrap-docs.sh --pack "$(PACK)" --srs "$(SRS)" --force

dry-run:
	./scripts/bootstrap-docs.sh --pack "$(PACK)" --srs "$(SRS)" --dry-run

# --- Repository validation ---------------------------------------------------
validate:
	./scripts/validate-repo.sh .

secrets:
	./scripts/check-secrets.sh .

status:
	./scripts/status.sh .

check-links:
	./scripts/check-doc-links.sh .

check-openapi:
	./scripts/check-openapi.sh .

# --- Release management (non-destructive; see docs/release-management.md) ----
# None of these targets publish. Tagging + GitHub Releases are deliberate manual
# actions (the release.yml workflow only responds to an already-created v* tag).
version:
	./scripts/check-version.sh .

version-check:
	./scripts/check-version.sh . $(VERSION)

changelog-check:
	./scripts/check-changelog.sh .

release-check:
	./scripts/release-dry-run.sh .

release-dry-run:
	./scripts/release-dry-run.sh . $(VERSION)

# Prepare-release is a documentation checklist aid; it does NOT tag or push.
# usage: make release-prepare VERSION=0.6.0
release-prepare:
	@echo "Release preparation checklist (see docs/release-management.md):"
	@echo "  1. Confirm release scope (tasks/features/breaking/migration changes)"
	@echo "  2. Update CHANGELOG.md '## [Unreleased]' with user-facing entries"
	@echo "  3. Ensure eligibility gates pass (test, lint, analyse, build, security)"
	@echo "  4. Run: make version-check VERSION=$(VERSION)"
	@echo "  5. Run: make changelog-check"
	@echo "  6. Run: make release-dry-run VERSION=$(VERSION)"
	@echo "  7. Rename '## [Unreleased]' to '## [$(VERSION)] - YYYY-MM-DD'"
	@echo "  8. Commit + create annotated tag v$(VERSION)"
	@echo "  9. Push tag (release.yml creates the GitHub Release)"
	@echo "Publishing remains a deliberate manual action."

doctor: validate status

# --- Docker services ---------------------------------------------------------
up:
	$(COMPOSE) up -d --build

down:
	$(COMPOSE) down

logs:
	$(COMPOSE) logs -f

migrate:
	$(APP) php artisan migrate --force

shell:
	$(APP) sh

# --- Optional AI development adapter (Ollama) --------------------------------
# The ollama service is opt-in (compose profile "ai") and internal-network
# only. The app remains fully operational without it (SRS FR-60).
ollama-up:
	$(COMPOSE) --profile ai up -d

ollama-down:
	$(COMPOSE) --profile ai down

ai-status:
	$(APP) php artisan ai:status

ai-smoke:
	$(APP) php artisan ai:smoke

# --- Production profile (TASK-080; deployment.md) ---------------------------
# Secrets (APP_KEY, DB_PASSWORD, APP_URL) come from the deployment environment.
prod-build:
	$(COMPOSE_PROD) build

prod-up:
	$(COMPOSE_PROD) up -d

prod-down:
	$(COMPOSE_PROD) down

prod-migrate:
	$(COMPOSE_PROD) run --rm app migrate

prod-logs:
	$(COMPOSE_PROD) logs -f

# Issue/renew a Let's Encrypt cert for SERVER_NAME via the webroot method
# (TASK-081). Requires the reverse-proxy to be up and SERVER_NAME + an email.
# usage: make prod-certbot EMAIL=admin@example.com
prod-certbot:
	$(COMPOSE_PROD) --profile certbot run --rm certbot certonly \
		--webroot -w /var/www/certbot \
		--email "$(EMAIL)" --agree-tos --no-eff-email \
		-d "$${SERVER_NAME}"

# --- Backup & restore (TASK-082, SRS §16.4/NFR-05) ---------------------------
# Run an on-demand backup against the running prod postgres. Remote copy is
# enabled with REMOTE_BUCKET=s3://bucket/prefix.
prod-backup:
	$(COMPOSE_PROD) run --rm --entrypoint /bin/sh backup -c "apk add --no-cache bash >/dev/null 2>&1 && bash /backup/backup.sh"

# List local backups in the backup volume.
prod-backup-list:
	$(COMPOSE_PROD) run --rm --entrypoint /bin/sh backup -c "ls -lht /backups"

# Restore the newest local backup (or BACKUP_FILE). DESTRUCTIVE — requires
# CONFIRM_RESTORE=yes.
prod-restore:
	$(COMPOSE_PROD) run --rm -e CONFIRM_RESTORE="$(CONFIRM_RESTORE)" \
		-e BACKUP_DIR=/backups \
		-e "BACKUP_FILE=$(BACKUP_FILE)" \
		--entrypoint /bin/sh backup -c \
		"apk add --no-cache bash >/dev/null 2>&1 && bash /backup/restore.sh \"$$BACKUP_FILE\""

# --- Production smoke test (TASK-156) --------------------------------------
# Brings up the REAL production Docker path (build → deploy → migrate → health
# → login → goal → task → schedule → today → backup → restore). Secrets are
# generated at runtime; the stack is torn down afterwards unless KEEP_UP=1.
prod-smoke:
	./scripts/prod-smoke.sh

# --- Quality gates (run inside the app container) ---------------------------
# DB_* are pinned to in-memory sqlite at the process level: this Laravel
# build's Dotenv is mutable, so phpunit.xml/.env.testing alone cannot stop a
# RefreshDatabase run from migrate:fresh-ing the LIVE database (TASK-R5
# incident: every `make test` wiped user data).
# AUTH_MAX_ATTEMPTS_PER_MINUTE is pinned to the production default (5) so the
# auth-throttle security contract (RateLimitingTest) is deterministic even when
# the dev sandbox raises the limit via docker-compose env.
TESTENV := $(COMPOSE) exec -T -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= -e AUTH_MAX_ATTEMPTS_PER_MINUTE=5 app
test:
	$(TESTENV) php artisan test

# --- Browser E2E smoke (rescue R1) -------------------------------------------
# Real-browser verification against the running dev SPA. Builds the Playwright
# runner image on first use and attaches to the host network so it can reach the
# app on 127.0.0.1:8000. Requires: dev stack up (make up). See tests/e2e/README.md.
e2e-build:
	docker build -t kinevo-e2e tests/e2e

# Always rebuild SPA assets WITH the compile-time e2e seam first: canvas tests
# drive the __kinevoCanvasAdapter seam (§82), which plain production builds
# dead-code-eliminate. Without this, a plain `npm run build` silently disarms
# the canvas matrix (TASK-R6 incident).
# Reset the dev E2E sandbox database to an empty-domain baseline. Every row in
# this database originates from browser tests/probes; unbounded fixture
# accumulation broke layout-dependent gates (P17-021: 671 accumulated goals
# pushed the Analytics surface past the 32767px full-page capture cap).
e2e-clean:
	$(COMPOSE) exec -T postgres psql -U kinevo -d kinevo -c "TRUNCATE goals, milestones, subtasks, tasks, task_assignments, notes, knowledge_links, attachments, programs, progress_events, focus_sessions, execution_sessions, boost_targets, break_periods, pause_events, recharge_sessions, schedule_overrides, scheduler_runs, hard_landscape_events, adaptive_context, ai_proposals, ai_runs, canvas_documents, canvas_files, canvases, imports, activity_logs CASCADE"

e2e: e2e-clean e2e-assets
	$(COMPOSE) exec -T app sh < tests/e2e/scripts/seed-journey-c.sh
	docker run --rm --network host -e E2E_BASE_URL=http://127.0.0.1:8000 \
		-v "$(CURDIR)/tests/e2e:/e2e" -w /e2e kinevo-e2e npx playwright test

e2e-assets:
	cd server && KINEVO_E2E_SEAM=1 npm run build

lint:
	$(APP) vendor/bin/pint --test

format:
	$(APP) vendor/bin/pint

analyse:
	$(APP) vendor/bin/phpstan analyse --memory-limit=512M

# --- Frontend gates (run inside the app container; Node is available in the
# dev image) ------------------------------------------------------------------
frontend-install:
	$(APP) npm install --ignore-scripts

frontend-typecheck:
	$(APP) npm run typecheck

frontend-test:
	$(APP) npm run test

frontend-build:
	$(APP) npm run build

ci: validate secrets check-links check-openapi lint analyse test \
	frontend-typecheck frontend-test frontend-build
