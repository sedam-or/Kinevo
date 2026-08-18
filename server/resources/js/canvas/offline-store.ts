/**
 * IndexedDB-backed MutationStore (SRS §9.2, offline-sync.md §Local storage).
 *
 * IndexedDB is a cache/queue, never canonical. Stores:
 * - outbound mutation queue;
 * - local canvas snapshots (so an offline edit survives tab close);
 * - sync metadata (queued/syncing/applied/failed status).
 *
 * The store is used behind the `MutationStore` contract so the queue/sync
 * logic remains testable without a real IndexedDB.
 */
import type { MutationEnvelope, MutationStore } from './offline';

const DB_NAME = 'kinevo-offline';
const DB_VERSION = 1;
const MUTATIONS_STORE = 'mutations';
const SNAPSHOTS_STORE = 'snapshots';

function openDb(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains(MUTATIONS_STORE)) {
                const mutations = db.createObjectStore(MUTATIONS_STORE, { keyPath: 'operationId' });
                mutations.createIndex('status', 'status', { unique: false });
            }
            if (!db.objectStoreNames.contains(SNAPSHOTS_STORE)) {
                db.createObjectStore(SNAPSHOTS_STORE, { keyPath: 'canvasId' });
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

export class IndexedDbMutationStore implements MutationStore {
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
        await this.remove(operationId);
    }

    async markFailed(
        operationId: string,
        status: 'failed_retryable' | 'failed_permanent',
        error: string,
    ): Promise<void> {
        await this.patch(operationId, { status, lastError: error });
    }

    async saveSnapshot(canvasId: number, scene: unknown, baseVersion: number): Promise<void> {
        const db = await this.db();
        const tx = db.transaction(SNAPSHOTS_STORE, 'readwrite');
        tx.objectStore(SNAPSHOTS_STORE).put({ canvasId, scene, baseVersion });
        await txComplete(tx);
    }

    async getSnapshot(canvasId: number): Promise<{ scene: unknown; baseVersion: number } | null> {
        const db = await this.db();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(SNAPSHOTS_STORE, 'readonly');
            const request = tx.objectStore(SNAPSHOTS_STORE).get(canvasId);
            request.onsuccess = () =>
                resolve((request.result as { scene: unknown; baseVersion: number } | undefined) ?? null);
            request.onerror = () => reject(request.error);
        });
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

    private async remove(operationId: string): Promise<void> {
        const db = await this.db();
        const tx = db.transaction(MUTATIONS_STORE, 'readwrite');
        tx.objectStore(MUTATIONS_STORE).delete(operationId);
        await txComplete(tx);
    }
}
