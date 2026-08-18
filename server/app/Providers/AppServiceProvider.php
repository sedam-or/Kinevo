<?php

namespace App\Providers;

use App\Domain\Goals\Contracts\GoalRepository;
use App\Domain\Identity\Contracts\ProfileRepository;
use App\Domain\Milestones\Contracts\MilestoneRepository;
use App\Domain\Programs\Contracts\ProgramRepository;
use App\Domain\Tasks\Contracts\SubtaskRepository;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Infrastructure\Goals\EloquentGoalRepository;
use App\Infrastructure\Identity\EloquentProfileRepository;
use App\Infrastructure\Milestones\EloquentMilestoneRepository;
use App\Infrastructure\Programs\EloquentProgramRepository;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
    }
}
