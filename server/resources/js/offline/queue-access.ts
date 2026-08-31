/**
 * ADR-017 §2.14 — process-wide access to the active offline MutationQueue.
 * AuthHost owns the queue lifecycle; stores read it to decide online vs
 * offline submission. Null in test/SSR environments without IndexedDB.
 */
import type { MutationQueue } from './queue';

let queue: MutationQueue | null = null;

export function setOfflineQueue(next: MutationQueue | null): void {
    queue = next;
}

export function getOfflineQueue(): MutationQueue | null {
    return queue;
}