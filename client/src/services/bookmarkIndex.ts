/**
 * Bookmark indexing service
 * Fetches all bookmarks and stores them in IndexedDB for client-side search
 * Requires IndexedDB - search feature is disabled if IndexedDB is not available
 * Uses incremental sync via diff endpoint to update index
 */

import { getBookmarksIndex, getBookmarkIndexDiff, getBookmark, getCursorFromUrl } from './api';
import type { Bookmark } from '../types';
import { storeBookmarks as storeInIndexedDB, getBookmarks as getFromIndexedDB, clearBookmarks as clearIndexedDB, isIndexedDBAvailable } from '../utils/indexedDB';

const STORAGE_KEY = 'bookmarkIndex';
const LAST_OPERATION_ID_KEY = 'bookmarkIndexLastOperationId';
const LAST_UPDATE_KEY = 'bookmarkIndexLastUpdate';
const MAX_INDEX_AGE_DAYS = 28;

export type ProgressCallback = (progress: number, fetched: number, total: number) => void;

function getLastOperationId(): string | null {
  try {
    return localStorage.getItem(LAST_OPERATION_ID_KEY);
  } catch {
    return null;
  }
}

function saveLastOperationId(operationId: string): void {
  try {
    localStorage.setItem(LAST_OPERATION_ID_KEY, operationId);
  } catch (error) {
    console.warn('Failed to save last operation ID:', error);
  }
}

function getLastUpdate(): Date | null {
  try {
    const timestamp = localStorage.getItem(LAST_UPDATE_KEY);
    return timestamp ? new Date(timestamp) : null;
  } catch {
    return null;
  }
}

function saveLastUpdate(): void {
  try {
    localStorage.setItem(LAST_UPDATE_KEY, new Date().toISOString());
  } catch (error) {
    console.warn('Failed to save last update timestamp:', error);
  }
}

function isIndexTooOld(): boolean {
  const lastUpdate = getLastUpdate();
  if (!lastUpdate) {
    return true;
  }
  const daysSinceUpdate = (Date.now() - lastUpdate.getTime()) / (1000 * 60 * 60 * 24);
  return daysSinceUpdate > MAX_INDEX_AGE_DAYS;
}

/**
 * Check if search feature is available (IndexedDB required)
 * @returns true if IndexedDB is available, false otherwise
 */
export function isSearchAvailable(): boolean {
  return isIndexedDBAvailable();
}

/**
 * Index all bookmarks by fetching all pages
 * After indexing, fetches the latest diff operation ID and saves it
 * Requires IndexedDB - will throw error if IndexedDB is not available
 * @param onProgress Optional callback to track indexing progress
 * @returns Promise that resolves with all indexed bookmarks
 * @throws Error if IndexedDB is not available
 */
export async function indexAllBookmarks(onProgress?: ProgressCallback): Promise<Bookmark[]> {
  if (!isIndexedDBAvailable()) {
    throw new Error('IndexedDB is not available. Search feature requires IndexedDB support.');
  }

  const allBookmarks: Bookmark[] = [];
  let cursor: string | undefined;
  let total: number | null = null;
  let fetched = 0;

  // Fetch first page to get total count
  const firstResponse = await getBookmarksIndex();
  allBookmarks.push(...firstResponse.collection);
  fetched += firstResponse.collection.length;
  total = firstResponse.total;
  cursor = getCursorFromUrl(firstResponse.nextPage);

  // Report initial progress
  if (onProgress && total !== null) {
    const progress = Math.round((fetched / total) * 100);
    onProgress(progress, fetched, total);
  }

  // Continue fetching remaining pages
  while (cursor) {
    const response = await getBookmarksIndex(cursor);
    allBookmarks.push(...response.collection);
    fetched += response.collection.length;
    cursor = getCursorFromUrl(response.nextPage);

    // Report progress
    if (onProgress && total !== null) {
      const progress = Math.round((fetched / total) * 100);
      onProgress(progress, fetched, total);
    }
  }

  // Store in IndexedDB (required)
  try {
    await storeInIndexedDB(allBookmarks);
  } catch (indexedDBError) {
    throw new Error(`Failed to store bookmarks in IndexedDB: ${indexedDBError instanceof Error ? indexedDBError.message : String(indexedDBError)}`);
  }

  // After successful indexing, get the latest diff operation ID
  try {
    const diffResponse = await getBookmarkIndexDiff();
    if (diffResponse.collection && diffResponse.collection.length > 0) {
      // Get the last operation ID (operations are ordered by ID ascending)
      const lastAction = diffResponse.collection[diffResponse.collection.length - 1];
      saveLastOperationId(lastAction.id);
    }
  } catch (error) {
    console.warn('Failed to fetch last operation ID, continuing without it:', error);
  }

  // Save update timestamp
  saveLastUpdate();

  // Report completion
  if (onProgress && total !== null) {
    onProgress(100, fetched, total);
  }

  return allBookmarks;
}

/**
 * Get indexed bookmarks from IndexedDB
 * Checks if index is too old and clears it if needed
 * Requires IndexedDB - returns null if IndexedDB is not available
 * @returns Array of bookmarks or null if not indexed or IndexedDB unavailable
 */
export async function getIndexedBookmarks(): Promise<Bookmark[] | null> {
  if (!isIndexedDBAvailable()) {
    return null;
  }

  if (isIndexTooOld()) {
    console.log('Index is older than 28 days, clearing it');
    await clearIndex();
    return null;
  }

  try {
    const bookmarks = await getFromIndexedDB();
    if (bookmarks) {
      return bookmarks as Bookmark[];
    }
  } catch (error) {
    console.warn('Failed to get bookmarks from IndexedDB:', error);
  }

  return null;
}

/**
 * Clear the bookmark index from IndexedDB and localStorage
 * Also clears the last operation ID and last update timestamp
 */
export async function clearIndex(): Promise<void> {
  if (isIndexedDBAvailable()) {
    try {
      await clearIndexedDB();
    } catch (error) {
      console.warn('Failed to clear IndexedDB:', error);
    }
  }

  try {
    localStorage.removeItem(STORAGE_KEY);
    localStorage.removeItem(LAST_OPERATION_ID_KEY);
    localStorage.removeItem(LAST_UPDATE_KEY);
  } catch (error) {
    console.warn('Failed to clear localStorage:', error);
  }
}

/**
 * Check if bookmarks are indexed
 * @returns Promise that resolves to true if index exists and is valid
 */
export async function isIndexed(): Promise<boolean> {
  const bookmarks = await getIndexedBookmarks();
  return bookmarks !== null && Array.isArray(bookmarks) && bookmarks.length > 0;
}

/**
 * Sync bookmark index using diff endpoint
 * Fetches changes since last operation ID and applies them to the index
 * Requires IndexedDB - returns false if IndexedDB is not available
 * @returns Promise that resolves to true if sync was successful
 */
export async function syncBookmarkIndex(): Promise<boolean> {
  if (!isIndexedDBAvailable()) {
    return false;
  }

  try {
    const lastOperationId = getLastOperationId();
    if (!lastOperationId) {
      // No last operation ID means we need to do a full index first
      console.log('Sync skipped: No last operation ID, full index required');
      return false;
    }

    console.log('Syncing bookmark index from operation ID:', lastOperationId);

    // Fetch diff since last operation
    const diffResponse = await getBookmarkIndexDiff(lastOperationId);
    if (!diffResponse.collection || diffResponse.collection.length === 0) {
      // No changes, update timestamp and return
      console.log('Sync complete: No changes found');
      saveLastUpdate();
      return true;
    }

    console.log(`Sync found ${diffResponse.collection.length} changes`);

    // Get current index
    const currentBookmarks = await getIndexedBookmarks();
    if (!currentBookmarks) {
      // Index doesn't exist, need to do full index
      return false;
    }

    // Apply changes
    let updatedBookmarks = [...currentBookmarks];
    let lastProcessedId: string | null = null;

    for (const action of diffResponse.collection) {
      lastProcessedId = action.id;

      switch (action.type) {
        case 'created':
        case 'updated':
          // Fetch the bookmark and add/update it
          try {
            const bookmark = await getBookmark(action.bookmark);
            if (bookmark) {
              // Remove existing bookmark if present
              updatedBookmarks = updatedBookmarks.filter((b) => b.id !== bookmark.id);
              // Add updated bookmark
              updatedBookmarks.push(bookmark);
            }
          } catch (error) {
            console.warn(`Failed to fetch bookmark ${action.bookmark} for ${action.type}:`, error);
          }
          break;

        case 'deleted':
        case 'outdated':
          // Remove the bookmark
          updatedBookmarks = updatedBookmarks.filter((b) => b.id !== action.bookmark);
          break;
      }
    }

    // Save updated index in IndexedDB (required)
    try {
      await storeInIndexedDB(updatedBookmarks);
    } catch (indexedDBError) {
      throw new Error(`Failed to store updated bookmarks in IndexedDB: ${indexedDBError instanceof Error ? indexedDBError.message : String(indexedDBError)}`);
    }

    // Save last processed operation ID
    if (lastProcessedId) {
      saveLastOperationId(lastProcessedId);
      console.log('Sync complete: Updated to operation ID:', lastProcessedId);
    }

    // Save update timestamp
    saveLastUpdate();

    return true;
  } catch (error) {
    console.error('Failed to sync bookmark index:', error);
    return false;
  }
}

/**
 * Force re-index all bookmarks
 * Clears existing index and rebuilds it from scratch
 * Can be called from browser console: window.forceReindex()
 * @deprecated This may be removed later
 * @param onProgress Optional callback to track indexing progress
 * @returns Promise that resolves with all indexed bookmarks
 */
export async function forceReindex(onProgress?: ProgressCallback): Promise<Bookmark[]> {
  if (!isIndexedDBAvailable()) {
    throw new Error('IndexedDB is not available. Search feature requires IndexedDB support.');
  }

  console.log('Starting forced re-index...');

  await clearIndex();

  const indexed = await indexAllBookmarks(onProgress);

  console.log(`Re-index complete. Indexed ${indexed.length} bookmarks.`);

  return indexed;
}

