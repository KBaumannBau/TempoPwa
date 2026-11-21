// Small key-value store in IndexedDB for authentication data (account, vault).
// No library on purpose; the read model and the outbox get their own stores later in sync/
// (docs/pwa/offline.md, section 3). localStorage stays off limits except for the display language.

const DB_NAME = 'tempo-auth';
const DB_VERSION = 1;
const STORE = 'auth';

let opening: Promise<IDBDatabase> | undefined;

function database(): Promise<IDBDatabase> {
  opening ??= new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);
    request.onupgradeneeded = () => {
      if (!request.result.objectStoreNames.contains(STORE)) request.result.createObjectStore(STORE);
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => { opening = undefined; reject(request.error); };
  });
  return opening;
}

function transaction<T>(mode: IDBTransactionMode, run: (store: IDBObjectStore) => IDBRequest): Promise<T> {
  return database().then((db) => new Promise<T>((resolve, reject) => {
    const tx = db.transaction(STORE, mode);
    const request = run(tx.objectStore(STORE));
    tx.oncomplete = () => resolve(request.result as T);
    tx.onerror = () => reject(tx.error);
    tx.onabort = () => reject(tx.error);
  }));
}

export const read = <T>(key: string) => transaction<T | undefined>('readonly', (s) => s.get(key));
export const write = (key: string, value: unknown) => transaction<IDBValidKey>('readwrite', (s) => s.put(value, key));
export const remove = (key: string) => transaction<undefined>('readwrite', (s) => s.delete(key));
export const clearAll = () => transaction<undefined>('readwrite', (s) => s.clear());
