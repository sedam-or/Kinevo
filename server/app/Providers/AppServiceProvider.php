<?php

namespace App\Providers;

use App\Domain\ActivityLogs\Contracts\ActivityLogRepository;
use App\Domain\Adaptive\Contracts\ContextObservationRepository;
use App\Domain\Ai\AiOrchestrator;
use App\Domain\Ai\Contracts\AiProposalRepository;
use App\Domain\Ai\Contracts\AiProviderResolver;
use App\Domain\Ai\Contracts\AiRunRepository;
use App\Domain\Canvas\Contracts\CanvasRepository;
use App\Domain\Focus\Contracts\FocusSessionRepository;
use App\Domain\Goals\Contracts\GoalRepository;
use App\Domain\Identity\Contracts\ProfileRepository;
use App\Domain\Knowledge\Contracts\KnowledgeLinkRepository;
use App\Domain\Knowledge\Contracts\NoteRepository;
use App\Domain\Milestones\Contracts\MilestoneRepository;
use App\Domain\Notifications\Contracts\NotificationRepository;
use App\Domain\Observability\Contracts\SchedulerRunRepository;
use App\Domain\Observability\ObservabilityService;
use App\Domain\Programs\Contracts\ProgramRepository;
use App\Domain\Progress\Contracts\ProgressEventRepository;
use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\Contracts\ScheduleOverrideRepository;
use App\Domain\Tasks\Contracts\SubtaskRepository;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Infrastructure\ActivityLogs\EloquentActivityLogRepository;
use App\Infrastructure\Adaptive\EloquentContextObservationRepository;
use App\Infrastructure\Ai\ConfigAiProviderResolver;
use App\Infrastructure\Ai\EloquentAiProposalRepository;
use App\Infrastructure\Ai\EloquentAiRunRepository;
use App\Infrastructure\Canvas\EloquentCanvasRepository;
use App\Infrastructure\Focus\EloquentFocusSessionRepository;
use App\Infrastructure\Goals\EloquentGoalRepository;
use App\Infrastructure\Identity\EloquentProfileRepository;
use App\Infrastructure\Knowledge\EloquentKnowledgeLinkRepository;
use App\Infrastructure\Knowledge\EloquentNoteRepository;
use App\Infrastructure\Milestones\EloquentMilestoneRepository;
use App\Infrastructure\Notifications\EloquentNotificationRepository;
use App\Infrastructure\Observability\EloquentSchedulerRunRepository;
use App\Infrastructure\Programs\EloquentProgramRepository;
use App\Infrastructure\Progress\EloquentProgressEventRepository;
use App\Infrastructure\Scheduling\EloquentHardLandscapeRepository;
use App\Infrastructure\Scheduling\EloquentScheduleAssignmentRepository;
use App\Infrastructure\Scheduling\EloquentScheduleOverrideRepository;
use App\Infrastructure\Tasks\EloquentSubtaskRepository;
use App\Infrastructure\Tasks\EloquentTaskRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ProfileRepository::class, EloquentProfileRepository::class);
        $this->app->singleton(GoalRepository::class, EloquentGoalRepository::class);
        $this->app->singleton(MilestoneRepository::class, EloquentMilestoneRepository::class);
        $this->app->singleton(ProgramRepository::class, EloquentProgramRepository::class);
        $this->app->singleton(TaskRepository::class, EloquentTaskRepository::class);
        $this->app->singleton(SubtaskRepository::class, EloquentSubtaskRepository::class);
        $this->app->singleton(ActivityLogRepository::class, EloquentActivityLogRepository::class);
        $this->app->singleton(ContextObservationRepository::class, EloquentContextObservationRepository::class);
        $this->app->singleton(NotificationRepository::class, EloquentNotificationRepository::class);
        $this->app->singleton(NoteRepository::class, EloquentNoteRepository::class);
        $this->app->singleton(KnowledgeLinkRepository::class, EloquentKnowledgeLinkRepository::class);
        $this->app->singleton(CanvasRepository::class, EloquentCanvasRepository::class);
        $this->app->singleton(FocusSessionRepository::class, EloquentFocusSessionRepository::class);
        $this->app->singleton(ProgressEventRepository::class, EloquentProgressEventRepository::class);
        $this->app->singleton(ScheduleAssignmentRepository::class, EloquentScheduleAssignmentRepository::class);
        $this->app->singleton(HardLandscapeRepository::class, EloquentHardLandscapeRepository::class);
        $this->app->singleton(ScheduleOverrideRepository::class, EloquentScheduleOverrideRepository::class);
        $this->app->singleton(AiProviderResolver::class, ConfigAiProviderResolver::class);
        $this->app->singleton(AiRunRepository::class, EloquentAiRunRepository::class);
        $this->app->singleton(AiProposalRepository::class, EloquentAiProposalRepository::class);
        $this->app->singleton(AiOrchestrator::class, static fn ($app) => new AiOrchestrator($app->make(AiProviderResolver::class)));
        $this->app->singleton(SchedulerRunRepository::class, EloquentSchedulerRunRepository::class);
        $this->app->singleton(ObservabilityService::class, static function ($app) {
            $resolver = $app->make(AiProviderResolver::class);

            return new ObservabilityService(
                $app->make(SchedulerRunRepository::class),
                static fn () => $resolver->resolve()->status(),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
    }
}
