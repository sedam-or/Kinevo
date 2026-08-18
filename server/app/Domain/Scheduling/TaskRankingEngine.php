<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\Components\ContextFitComponent;
use App\Domain\Scheduling\Components\ContinuityPreferenceComponent;
use App\Domain\Scheduling\Components\DurationFitComponent;
use App\Domain\Scheduling\Components\FragmentationPenaltyComponent;
use App\Domain\Scheduling\Components\GoalDeadlineComponent;
use App\Domain\Scheduling\Components\MilestoneUrgencyComponent;
use App\Domain\Scheduling\Components\PriorityTierComponent;
use App\Domain\Scheduling\Components\ProgressLeverageComponent;
use App\Domain\Scheduling\Components\TaskDeadlineComponent;
use App\Domain\Scheduling\Contracts\ScoreComponent;

/**
 * Task ranking engine (FR-23, FR-64). Ranks hard-feasible candidates using
 * lexicographic soft-component ordering (scheduling-engine §Soft ranking).
 * Each component score stays observable for explanations.
 *
 * Only candidates that already passed the HardConstraintEngine may be ranked;
 * soft ordering can never override a hard violation (FR-64).
 */
final class TaskRankingEngine
{
    /**
     * @param  array<int, ScoreComponent>  $components  in lexicographic order
     */
    public function __construct(
        private readonly array $components = [],
    ) {}

    public static function default(): self
    {
        return new self([
            new PriorityTierComponent,
            new GoalDeadlineComponent,
            new MilestoneUrgencyComponent,
            new TaskDeadlineComponent,
            new ProgressLeverageComponent,
            new ContextFitComponent,
            new FragmentationPenaltyComponent,
            new DurationFitComponent,
            new ContinuityPreferenceComponent,
        ]);
    }

    /**
     * @param  array<int, RankingCandidate>  $candidates
     * @return array<int, RankingCandidate> ranked best-first
     */
    public function rank(array $candidates): array
    {
        $scored = $this->scoreAll($candidates);

        usort($scored, static function (RankedCandidate $a, RankedCandidate $b) {
            foreach ($a->components as $code => $scoreA) {
                $scoreB = $b->components[$code];
                if ($scoreA !== $scoreB) {
                    return $scoreA > $scoreB ? -1 : 1;
                }
            }

            return 0;
        });

        return array_map(static fn (RankedCandidate $c) => $c->candidate, $scored);
    }

    /**
     * @param  array<int, RankingCandidate>  $candidates
     * @return array<int, RankedCandidate>
     */
    public function scoreAll(array $candidates): array
    {
        return array_map(
            fn (RankingCandidate $candidate) => $this->score($candidate),
            $candidates,
        );
    }

    public function score(RankingCandidate $candidate): RankedCandidate
    {
        $scores = [];
        foreach ($this->components as $component) {
            $scores[$component->code()] = $component->score($candidate);
        }

        return new RankedCandidate($candidate, $scores);
    }
}
