/**
 * IndexedDB-backed TodayCacheStore (TASK-051, SRS §9.2).
 *
 * Stores Today schedule snapshots keyed by ISO date so the Today view can
 * render offline after the first online load (FR-44). IndexedDB is a cache,
 * never canonical — PostgreSQL remains authoritative.
 */
import type { TodayCacheStore, TodayData } from './today-types';

const DB_NAME = 'kinevo-today-cache';
const DB_VERSION = 1;
const STORE_NAME = 'today';

function openDb(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                db.createObjectStore(STORE_NAME, { keyPath: 'date' });
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

export class IndexedDbTodayCacheStore implements TodayCacheStore {
    private dbPromise: Promise<IDBDatabase> | null = null;

    private db(): Promise<IDBDatabase> {
        if (this.dbPromise === null) {
            this.dbPromise = openDb();
        }
        return this.dbPromise;
    }

    async put(_date: string, data: TodayData): Promise<void> {
        const db = await this.db();
        const tx = db.transaction(STORE_NAME, 'readwrite');
        tx.objectStore(STORE_NAME).put(data);
        await txComplete(tx);
    }

    async get(date: string): Promise<TodayData | null> {
        const db = await this.db();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_NAME, 'readonly');
            const request = tx.objectStore(STORE_NAME).get(date);
            request.onsuccess = () =>
                resolve((request.result as TodayData | undefined) ?? null);
            request.onerror = () => reject(request.error);
        });
    }

    async clear(date: string): Promise<void> {
        const db = await this.db();
        const tx = db.transaction(STORE_NAME, 'readwrite');
        tx.objectStore(STORE_NAME).delete(date);
        await txComplete(tx);
    }
}
