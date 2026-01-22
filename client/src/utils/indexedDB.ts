/**
 * IndexedDB utility for storing bookmarks
 * Provides a simple interface for storing and retrieving bookmarks from IndexedDB
 * which has much larger quotas than localStorage (especially important for Safari)
 */

const DB_NAME = 'hivecache';
const DB_VERSION = 1;
const STORE_NAME = 'bookmarks';

let dbPromise: Promise<IDBDatabase> | null = null;

/**
 * Initialize IndexedDB database
 */
function initDB(): Promise<IDBDatabase> {
  if (dbPromise) {
    return dbPromise;
  }

  dbPromise = new Promise((resolve, reject) => {
    // Check if IndexedDB is available
    if (!window.indexedDB) {
      reject(new Error('IndexedDB is not supported in this browser'));
      return;
    }

    const request = indexedDB.open(DB_NAME, DB_VERSION);

    request.onerror = () => {
      reject(new Error(`Failed to open IndexedDB: ${request.error?.message}`));
    };

    request.onsuccess = () => {
      resolve(request.result);
    };

    request.onupgradeneeded = (event) => {
      const db = (event.target as IDBOpenDBRequest).result;

      // Create object store if it doesn't exist
      if (!db.objectStoreNames.contains(STORE_NAME)) {
        const objectStore = db.createObjectStore(STORE_NAME, { keyPath: 'id' });
        // Create index for faster lookups if needed
        objectStore.createIndex('domain', 'domain', { unique: false });
      }
    };
  });

  return dbPromise;
}

/**
 * Store bookmarks in IndexedDB
 * @param bookmarks Array of bookmarks to store
 */
export async function storeBookmarks(bookmarks: unknown[]): Promise<void> {
  try {
    const db = await initDB();
    const transaction = db.transaction([STORE_NAME], 'readwrite');
    const store = transaction.objectStore(STORE_NAME);

    // Clear existing data
    await new Promise<void>((resolve, reject) => {
      const clearRequest = store.clear();
      clearRequest.onsuccess = () => resolve();
      clearRequest.onerror = () => reject(clearRequest.error);
    });

    // Store all bookmarks
    const promises = bookmarks.map((bookmark) => {
      return new Promise<void>((resolve, reject) => {
        const request = store.add(bookmark);
        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error);
      });
    });

    await Promise.all(promises);
  } catch (error) {
    throw new Error(`Failed to store bookmarks in IndexedDB: ${error instanceof Error ? error.message : String(error)}`);
  }
}

/**
 * Get all bookmarks from IndexedDB
 * @returns Array of bookmarks or null if not found
 */
export async function getBookmarks(): Promise<unknown[] | null> {
  try {
    const db = await initDB();
    const transaction = db.transaction([STORE_NAME], 'readonly');
    const store = transaction.objectStore(STORE_NAME);

    return new Promise((resolve, reject) => {
      const request = store.getAll();
      request.onsuccess = () => {
        const bookmarks = request.result;
        resolve(bookmarks.length > 0 ? bookmarks : null);
      };
      request.onerror = () => reject(request.error);
    });
  } catch (error) {
    // If IndexedDB fails, return null (will fall back to localStorage or re-index)
    console.warn('Failed to get bookmarks from IndexedDB:', error);
    return null;
  }
}

/**
 * Clear all bookmarks from IndexedDB
 */
export async function clearBookmarks(): Promise<void> {
  try {
    const db = await initDB();
    const transaction = db.transaction([STORE_NAME], 'readwrite');
    const store = transaction.objectStore(STORE_NAME);

    await new Promise<void>((resolve, reject) => {
      const request = store.clear();
      request.onsuccess = () => resolve();
      request.onerror = () => reject(request.error);
    });
  } catch (error) {
    console.warn('Failed to clear bookmarks from IndexedDB:', error);
    // Don't throw, just log the warning
  }
}

/**
 * Check if IndexedDB is available
 */
export function isIndexedDBAvailable(): boolean {
  return typeof window !== 'undefined' && 'indexedDB' in window;
}

