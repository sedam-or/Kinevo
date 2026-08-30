<?php

namespace App\Domain\Scheduling\Resolution;

use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\Recurrence\RecurrenceOccurrenceGenerator;
use App\Domain\Scheduling\Recurrence\RecurrenceRule;
use App\Domain\Scheduling\ScheduleOverride;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use App\Domain\Scheduling\ValueObjects\ScheduleOverrideType;
use App\Domain\Scheduling\ValueObjects\SchedulePrecedence;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Effective Landscape Resolver — the canonical resolution layer for Hard
 * Landscape (ADR-015).
 *
 * PURE DOMAIN SERVICE: same inputs always produce the same outputs. It must
 * never query repositories, touch Eloquent, persist, mutate its inputs, call
 * HTTP/AI, place flexible tasks, or read global application state. Consumers
 * (read models, scheduler input assembly) fetch sources and overrides through
 * the existing repository ports and pass them in.
 *
 * ES-IMPL-01 scope: base-event and recurrence expansion ONLY. `$overrides`
 * is accepted for ADR-015 signature forward-compatibility (ES-IMPL-04/05) and
 * MUST NOT alter the output yet. Provenance is always `base`.
 *
 * Recurrence semantics are entirely delegated to the existing canonical
 * engine (`RecurrenceRule` + `RecurrenceOccurrenceGenerator` — the same
 * engine ICS export uses). No DAILY/WEEKLY/BYDAY/COUNT/UNTIL/INTERVAL or
 * timezone logic is duplicated here.
 *
 * Window semantics: half-open interval overlap identical to the repository
 * (`start_at < to AND end_at > from`, `EloquentHardLandscapeRepository`) and
 * the export path (`ExportScheduleIcsUseCase::overlapsWindow`).
 *
 * Failure contract (ADR-015): an unparseable recurrence DEGRADES to the base
 * single occurrence — the block is never silently dropped — and a
 * {@see RecurrenceResolutionWarning} is attached to the result for the
 * application layer to surface (`recurrence_warning`, ES-IMPL-02).
 */
final class EffectiveLandscapeResolver
{
    public function __construct(
        private readonly RecurrenceOccurrenceGenerator $recurrences = new RecurrenceOccurrenceGenerator,
    ) {}

    /**
     * Resolve the effective Hard Landscape for a window.
     *
     * @param  array<int, HardLandscapeEvent>  $sources  any input order; output order is deterministic
     * @param  array<int, ScheduleOverride>  $overrides  forward-compatibility parameter; MUST NOT alter output in this slice
     *
     * @throws InvalidArgumentException when the window is inverted
     */
    public function resolve(
        array $sources,
        array $overrides,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): EffectiveLandscapeResolution {
        if ($to->lt($from)) {
            throw new InvalidArgumentException('Resolution window "to" cannot precede "from".');
        }

        // Process in source-id order so diagnostics (warnings) are also
        // deterministic regardless of input order.
        $ordered = $sources;
        usort($ordered, static fn (HardLandscapeEvent $a, HardLandscapeEvent $b) => $a->id <=> $b->id);

        $overridesBySource = [];
        $maxForwardDays = 0;
        $maxBackwardDays = 0;

        foreach ($overrides as $override) {
            $overridesBySource[$override->hardLandscapeEventId][] = $override;

            // A permanent shift can move a covered occurrence onto a
            // DIFFERENT calendar day. The expansion window must therefore be
            // widened by the largest shift displacement so effective
            // occurrences are generated even when their ORIGINAL date lies
            // outside the requested window.
            if ($override->type->equals(ScheduleOverrideType::permanent())) {
                $delta = (int) CarbonImmutable::parse($override->effectiveFrom->toDateString())
                    ->startOfDay()
                    ->diffInDays(CarbonImmutable::parse($override->overrideStartAt->toDateString())->startOfDay());

                if ($delta > $maxForwardDays) {
                    $maxForwardDays = $delta;
                }

                if ($delta < $maxBackwardDays) {
                    $maxBackwardDays = $delta;
                }
            }
        }

        // originalDate = effectiveDate − delta. Effective dates inside
        // [from, to] therefore require original dates inside
        // [from − maxForward, to + |maxBackward|].
        $expansionFrom = $from->startOfDay()->subDays($maxForwardDays);
        $expansionTo = $to->endOfDay()->addDays(abs($maxBackwardDays));

        $occurrences = [];
        $warnings = [];
        $cancelled = [];

        foreach ($ordered as $source) {
            $sourceOverrides = $overridesBySource[$source->id] ?? [];

            if ($source->type->equals(HardLandscapeType::recurring()) && $source->recurrence !== null) {
                $this->resolveRecurring($source, $sourceOverrides, $expansionFrom, $expansionTo, $occurrences, $warnings, $cancelled);

                continue;
            }

            // Non-recurring source rows (permanent / one_time): one base
            // occurrence when they overlap the window.
            if ($this->overlapsWindow($source, $from, $to)) {
                $occurrences[] = HardLandscapeOccurrence::base(
                    $source->id,
                    $source->title,
                    $source->startAt,
                    $source->endAt,
                    SchedulePrecedence::hardLandscape(),
                );
            }
        }

        return new EffectiveLandscapeResolution(
            // The resolved window is the EFFECTIVE window: occurrences whose
            // resolved block does not overlap it (e.g. shifted away) are not
            // part of this window's landscape.
            $this->sorted(array_values(array_filter(
                $occurrences,
                fn (HardLandscapeOccurrence $occurrence) => $this->overlapsEffectiveWindow($occurrence, $from, $to),
            ))),
            $warnings,
            $this->sorted($cancelled),
        );
    }

    /**
     * Expand one recurring source within the window using the canonical
     * generator (or degrade to the base occurrence on an unparseable rule),
     * then apply the source's overrides with the ADR-015 precedence:
     *
     *   ONE-TIME EXCEPTION (targets the occurrence date)  >
     *   LATEST APPLICABLE PERMANENT SHIFT (greatest effective_from, then
     *   greatest id)  >
     *   BASE RECURRENCE
     *
     * A cancelling one-time exception removes exactly its target occurrence.
     * The source series is never mutated.
     *
     * @param  array<int, ScheduleOverride>  $sourceOverrides
     * @param  list<HardLandscapeOccurrence>  $occurrences
     * @param  list<RecurrenceResolutionWarning>  $warnings
     * @param  list<HardLandscapeOccurrence>  $cancelled
     */
    private function resolveRecurring(
        HardLandscapeEvent $source,
        array $sourceOverrides,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array &$occurrences,
        array &$warnings,
        array &$cancelled,
    ): void {
        try {
            $rule = RecurrenceRule::parse($source->recurrence ?? '', $source->startAt);
        } catch (InvalidArgumentException $e) {
            // Degrade to the base single occurrence (export contract: the
            // block is never silently dropped) and record the diagnostic.
            $warnings[] = new RecurrenceResolutionWarning(
                $source->id,
                (string) $source->recurrence,
                $e->getMessage(),
            );

            if ($this->overlapsWindow($source, $from, $to)) {
                $occurrences[] = HardLandscapeOccurrence::base(
                    $source->id,
                    $source->title,
                    $source->startAt,
                    $source->endAt,
                    SchedulePrecedence::recurring(),
                );
            }

            return;
        }

        // Wall-clock time-of-day stays pinned in the rule start's timezone
        // (generator behavior); the source duration is preserved per
        // occurrence exactly like the ICS export path.
        $durationMinutes = (int) abs($source->endAt->diffInMinutes($source->startAt));

        foreach ($this->recurrences->generate($rule, $from, $to) as $occurrenceStart) {
            $occurrenceEnd = $occurrenceStart->addMinutes($durationMinutes);

            // Deterministic selection: exceptions first, then the latest
            // applicable permanent shift (greatest effective_from, then
            // greatest id). Database row order can never leak through.
            $exception = null;
            $shift = null;

            foreach ($sourceOverrides as $override) {
                if ($override->type->equals(ScheduleOverrideType::oneTime())
                    && $override->effectiveFrom->toDateString() === $occurrenceStart->toDateString()) {
                    if ($exception === null
                        || $this->overridesOverride($override, $exception)) {
                        $exception = $override;
                    }

                    continue;
                }

                if ($override->type->equals(ScheduleOverrideType::permanent())
                    && $this->coversDate($override, $occurrenceStart)) {
                    if ($shift === null
                        || $this->overridesOverride($override, $shift)) {
                        $shift = $override;
                    }
                }
            }

            if ($exception !== null && $exception->cancelsOccurrence) {
                $cancelled[] = new HardLandscapeOccurrence(
                    $source->id,
                    $source->title,
                    $occurrenceStart,
                    $occurrenceEnd,
                    $occurrenceStart,
                    $occurrenceEnd,
                    OccurrenceProvenance::cancelled($exception->id),
                    SchedulePrecedence::explicitOverride(),
                );

                continue;
            }

            if ($exception !== null) {
                $occurrences[] = new HardLandscapeOccurrence(
                    $source->id,
                    $source->title,
                    $occurrenceStart,
                    $occurrenceEnd,
                    $exception->overrideStartAt,
                    $exception->overrideEndAt,
                    OccurrenceProvenance::excepted($exception->id),
                    SchedulePrecedence::explicitOverride(),
                );

                continue;
            }

            if ($shift !== null) {
                // Permanent shift semantics (ADR-015): every covered occurrence
                // is re-timed by the date delta between its original date and
                // the shift's effective_from, keeping the override interval's
                // time-of-day and duration — "Monday 09:00" becomes "Wednesday
                // 13:00" for each covered week.
                $deltaDays = (int) CarbonImmutable::parse($shift->effectiveFrom->toDateString())
                    ->startOfDay()
                    ->diffInDays(CarbonImmutable::parse($occurrenceStart->toDateString())->startOfDay());

                $occurrences[] = new HardLandscapeOccurrence(
                    $source->id,
                    $source->title,
                    $occurrenceStart,
                    $occurrenceEnd,
                    $shift->overrideStartAt->addDays($deltaDays),
                    $shift->overrideEndAt->addDays($deltaDays),
                    OccurrenceProvenance::shifted($shift->id),
                    SchedulePrecedence::explicitOverride(),
                );

                continue;
            }

            $occurrences[] = HardLandscapeOccurrence::base(
                $source->id,
                $source->title,
                $occurrenceStart,
                $occurrenceEnd,
                SchedulePrecedence::recurring(),
            );
        }
    }

    /**
     * Deterministic override-vs-override comparison: greatest effective_from
     * wins; tie-break greatest id (ADR-015 precedence).
     */
    private function overridesOverride(ScheduleOverride $candidate, ScheduleOverride $incumbent): bool
    {
        if (! $candidate->effectiveFrom->equalTo($incumbent->effectiveFrom)) {
            return $candidate->effectiveFrom->greaterThan($incumbent->effectiveFrom);
        }

        return $candidate->id > $incumbent->id;
    }

    /**
     * Whether a permanent shift covers the occurrence's local calendar date
     * (inclusive `[effective_from date, effective_to date]`).
     */
    private function coversDate(ScheduleOverride $override, CarbonImmutable $occurrenceStart): bool
    {
        $date = $occurrenceStart->toDateString();

        return $override->effectiveFrom->toDateString() <= $date
            && $override->effectiveTo->toDateString() >= $date;
    }

    /**
     * Half-open window overlap — identical to `EloquentHardLandscapeRepository`
     * range queries and `ExportScheduleIcsUseCase::overlapsWindow`.
     */
    private function overlapsWindow(HardLandscapeEvent $source, CarbonImmutable $from, CarbonImmutable $to): bool
    {
        return $source->startAt->lt($to) && $source->endAt->gt($from);
    }

    /**
     * Half-open overlap of a resolved occurrence's EFFECTIVE window with the
     * requested window (ADR-015: read models see the effective schedule).
     */
    private function overlapsEffectiveWindow(HardLandscapeOccurrence $occurrence, CarbonImmutable $from, CarbonImmutable $to): bool
    {
        return $occurrence->effectiveStart->lt($to) && $occurrence->effectiveEnd->gt($from);
    }

    /**
     * Deterministic ordering: effectiveStart, then sourceEventId, then
     * originalStart (ADR-015). Database input order can never leak through.
     *
     * @param  list<HardLandscapeOccurrence>  $occurrences
     * @return list<HardLandscapeOccurrence>
     */
    private function sorted(array $occurrences): array
    {
        usort($occurrences, static fn (HardLandscapeOccurrence $a, HardLandscapeOccurrence $b) => $a->effectiveStart <=> $b->effectiveStart
            ?: $a->sourceEventId <=> $b->sourceEventId
            ?: $a->originalStart <=> $b->originalStart
        );

        return $occurrences;
    }
}
