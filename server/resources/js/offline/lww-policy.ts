/**
 * Last-write-wins offline conflict-resolution policy (TASK-053, SRS §9.4,
 * offline-sync.md §Conflict strategy).
 *
 * SRS §9.4: last-write-wins remains valid for the narrow MVP offline queue
 * where explicitly configured; versioned rich content and canvas documents
 * use the more conservative rule (stale writes surface a conflict and are NOT
 * silently discarded).
 *
 * This policy is the single, domain-owned place that decides how a queued
 * offline mutation is resolved when it conflicts with authoritative server
 * state. It is deterministic and pure — no I/O — so it is fully unit-testable.
 */

/** How to resolve a conflicting offline mutation. */
export type ConflictResolution = 'last_write_wins' | 'conflict';

/** Inputs describing an offline mutation that collided with server state. */
export interface ConflictContext {
    /** Domain entity type (e.g. 'task', 'note', 'canvas'). */
    entityType: string;
    /** Operation type (e.g. 'create', 'update', 'toggle', 'quick_capture'). */
    operationType: string;
    /** True if the mutation carries a baseVersion (versioned rich content). */
    isVersioned: boolean;
    /** Authoritative server version at sync time. */
    serverVersion: number;
    /** The version the local mutation was based on. */
    localBaseVersion: number;
}

/**
 * Entities that are low-risk and eligible for last-write-wins (offline-sync.md
 * §Conflict strategy). Versioned rich content (canvas/note) is never here.
 */
export const LWW_ELIGIBLE_ENTITY_TYPES: ReadonlySet<string> = new Set([
    'task',
    'subtask',
    'goal',
    'milestone',
    'program',
    'quick_capture',
]);

/** Operations considered low-risk regardless of entity. */
export const LWW_ELIGIBLE_OPERATIONS: ReadonlySet<string> = new Set([
    'toggle',
    'quick_capture',
]);

/**
 * The product last-write-wins policy. Decisions are deterministic for the same
 * inputs.
 */
export interface LastWriteWinsPolicy {
    /** Whether a mutation may use last-write-wins under any circumstance. */
    isLwwEligible(entityType: string, operationType: string, isVersioned: boolean): boolean;
    /** Decide how to resolve a conflicting mutation. */
    resolveConflict(context: ConflictContext): ConflictResolution;
}

/**
 * Default policy:
 * - a mutation is LWW-eligible only if it is NOT versioned AND its entity is
 *   low-risk OR its operation is explicitly low-risk;
 * - when LWW-eligible, the latest local payload wins;
 * - otherwise (versioned rich content, or entity not on the LWW allow-list)
 *   the conflict is surfaced — never a silent overwrite.
 */
export class DefaultLastWriteWinsPolicy implements LastWriteWinsPolicy {
    constructor(
        private readonly lwwEntities: ReadonlySet<string> = LWW_ELIGIBLE_ENTITY_TYPES,
        private readonly lwwOperations: ReadonlySet<string> = LWW_ELIGIBLE_OPERATIONS,
    ) {}

    isLwwEligible(entityType: string, operationType: string, isVersioned: boolean): boolean {
        if (isVersioned) {
            // Versioned rich content (canvas/note) is always conservative.
            return false;
        }
        return this.lwwEntities.has(entityType) || this.lwwOperations.has(operationType);
    }

    resolveConflict(context: ConflictContext): ConflictResolution {
        if (this.isLwwEligible(context.entityType, context.operationType, context.isVersioned)) {
            // Stale but low-risk: last write wins (FR-44 exception flow).
            return 'last_write_wins';
        }
        return 'conflict';
    }
}
