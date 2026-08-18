/**
 * Today cache (TASK-051, FR-44, SRS §9.2).
 *
 * The Today view is the primary execution surface (design.md §Today screen).
 * To work offline, Today data — tasks, subtasks, the canonical schedule
 * snapshot from `GET /api/v1/today?date=` — is cached in IndexedDB after the
 * first online load (FR-44 precondition: "Today has been loaded online at
 * least once for full baseline cache"). IndexedDB is a cache, never canonical:
 * PostgreSQL remains authoritative (offline-sync.md §Principle).
 */

/** A task entity on the Today surface. */
export interface TodayTask {
    id: number;
    title: string;
    status: string;
    priorityTier: number;
    estimatedMinutes?: number;
    dueAt?: string | null;
    progress?: number;
    context?: { programId?: number | null; goalId?: number | null };
}

/** A subtask entity under a task. */
export interface TodaySubtask {
    id: number;
    taskId: number;
    title: string;
    completed: boolean;
}

/** A schedule slot on the Today timeline (06:00–24:00). */
export interface TodaySlot {
    start: string;
    end: string;
    taskId?: number;
    title?: string;
    kind: 'assigned' | 'empty' | 'hard_landscape';
}

/** Canonical Today payload returned by `GET /api/v1/today?date=`. */
export interface TodayData {
    date: string;
    tasks: TodayTask[];
    subtasks: TodaySubtask[];
    slots: TodaySlot[];
    cachedAt: string;
}

/**
 * Persistent store for Today snapshots, keyed by date. The IndexedDB
 * implementation is injected at the app boundary; an in-memory store is used
 * in tests (happy-dom has no real IndexedDB).
 */
export interface TodayCacheStore {
    put(date: string, data: TodayData): Promise<void>;
    get(date: string): Promise<TodayData | null>;
    clear(date: string): Promise<void>;
}

/** Contract for fetching the canonical Today schedule. Injectable for tests. */
export interface TodayFetcher {
    fetch(date: string): Promise<Omit<TodayData, 'cachedAt'>>;
}
