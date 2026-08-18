/**
 * IndexedDB-backed OfflineMutationStore (TASK-052, SRS §9.2/§9.3).
 *
 * Stores the outbound mutation queue. IndexedDB is a cache/queue, never
 * canonical — PostgreSQL remains authoritative (offline-sync.md §Principle).
 * Used behind the `OfflineMutationStore` contract so the queue logic stays
 * testable without a real IndexedDB.
 */
import type { MutationEnvelope, OfflineMutationStore } from './queue-types';

const DB_NAME = 'kinevo-mutation-queue';
const DB_VERSION = 1;
const MUTATIONS_STORE = 'mutations';

function openDb(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains(MUTATIONS_STORE)) {
                const store = db.createObjectStore(MUTATIONS_STORE, { keyPath: 'operationId' });
                store.createIndex('status', 'status', { unique: false });
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function txComplete(tx: IDBTransaction): Promise<void> {
    return new Promise((resolve, reject) => {
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
        tx.onabort = () => reject(tx.error);
    });
}

export class IndexedDbQueueStore implements OfflineMutationStore {
    private dbPromise: Promise<IDBDatabase> | null = null;

    private db(): Promise<IDBDatabase> {
        if (this.dbPromise === null) {
            this.dbPromise = openDb();
        }
        return this.dbPromise;
    }

    async enqueue(envelope: MutationEnvelope): Promise<void> {
        const db = await this.db();
        const tx = db.transaction(MUTATIONS_STORE, 'readwrite');
        tx.objectStore(MUTATIONS_STORE).put(envelope);
        await txComplete(tx);
    }

    async listPending(): Promise<MutationEnvelope[]> {
        const db = await this.db();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(MUTATIONS_STORE, 'readonly');
            const request = tx.objectStore(MUTATIONS_STORE).getAll();
            request.onsuccess = () => {
                const all = (request.result as MutationEnvelope[]).filter(
                    (m) => m.status === 'queued' || m.status === 'syncing' || m.status === 'failed_retryable',
                );
                all.sort(
                    (a, b) =>
                        new Date(a.clientTimestamp).getTime() - new Date(b.clientTimestamp).getTime(),
                );
                resolve(all);
            };
            request.onerror = () => reject(request.error);
        });
    }

    async markSyncing(operationId: string): Promise<void> {
        await this.patch(operationId, { status: 'syncing' });
    }

    async markApplied(operationId: string): Promise<void> {
        const db = await this.db();
        const tx = db.transaction(MUTATIONS_STORE, 'readwrite');
        tx.objectStore(MUTATIONS_STORE).delete(operationId);
        await txComplete(tx);
    }

    async markFailed(
        operationId: string,
        status: 'failed_retryable' | 'failed_permanent',
        error: string,
    ): Promise<void> {
        await this.patch(operationId, { status, lastError: error });
    }

    private async patch(operationId: string, fields: Partial<MutationEnvelope>): Promise<void> {
        const db = await this.db();
        const tx = db.transaction(MUTATIONS_STORE, 'readwrite');
        const store = tx.objectStore(MUTATIONS_STORE);
        const request = store.get(operationId);
        request.onsuccess = () => {
            const record = request.result as MutationEnvelope | undefined;
            if (record !== undefined) {
                store.put({ ...record, ...fields });
            }
        };
        await txComplete(tx);
    }
}
