/**
 * Bookmark indexing service
 * Fetches all bookmarks and stores them in IndexedDB for client-side search
 * Falls back to localStorage if IndexedDB is not available
 */

import { getBookmarks, getCursorFromUrl } from './api';
import type { Bookmark } from '../types';
import { storeBookmarks as storeInIndexedDB, getBookmarks as getFromIndexedDB, clearBookmarks as clearIndexedDB, isIndexedDBAvailable } from '../utils/indexedDB';

const STORAGE_KEY = 'bookmarkIndex';

export type ProgressCallback = (progress: number, fetched: number, total: number) => void;

/**
 * Index all bookmarks by fetching all pages
 * @param onProgress Optional callback to track indexing progress
 * @returns Promise that resolves with all indexed bookmarks
 */
export async function indexAllBookmarks(onProgress?: ProgressCallback): Promise<Bookmark[]> {
  const allBookmarks: Bookmark[] = [];
  let cursor: string | undefined;
  let total: number | null = null;
  let fetched = 0;

  try {
    // Fetch first page to get total count
    const firstResponse = await getBookmarks();
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
      const response = await getBookmarks(undefined, cursor);
      allBookmarks.push(...response.collection);
      fetched += response.collection.length;
      cursor = getCursorFromUrl(response.nextPage);

      // Report progress
      if (onProgress && total !== null) {
        const progress = Math.round((fetched / total) * 100);
        onProgress(progress, fetched, total);
      }
    }

    // Store in IndexedDB (preferred) or localStorage (fallback)
    if (isIndexedDBAvailable()) {
      try {
        await storeInIndexedDB(allBookmarks);
      } catch (indexedDBError) {
        // If IndexedDB fails, try localStorage as fallback
        console.warn('IndexedDB storage failed, falling back to localStorage:', indexedDBError);
        try {
          localStorage.setItem(STORAGE_KEY, JSON.stringify(allBookmarks));
        } catch (localStorageError) {
          // Clear partial index on error
          await clearIndex();
          throw new Error(`Failed to store bookmarks: ${localStorageError instanceof Error ? localStorageError.message : String(localStorageError)}`);
        }
      }
    } else {
      // Fallback to localStorage if IndexedDB is not available
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(allBookmarks));
      } catch (localStorageError) {
        // Clear partial index on error
        await clearIndex();
        throw new Error(`Failed to store bookmarks: ${localStorageError instanceof Error ? localStorageError.message : String(localStorageError)}`);
      }
    }

    // Report completion
    if (onProgress && total !== null) {
      onProgress(100, fetched, total);
    }

    return allBookmarks;
  } catch (error) {
    // Clear partial index on error
    await clearIndex();
    throw error;
  }
}

/**
 * Get indexed bookmarks from IndexedDB or localStorage
 * @returns Array of bookmarks or null if not indexed
 */
export async function getIndexedBookmarks(): Promise<Bookmark[] | null> {
  // Try IndexedDB first if available
  if (isIndexedDBAvailable()) {
    try {
      const bookmarks = await getFromIndexedDB();
      if (bookmarks) {
        return bookmarks as Bookmark[];
      }
    } catch (error) {
      console.warn('Failed to get bookmarks from IndexedDB, trying localStorage:', error);
    }
  }

  // Fallback to localStorage
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (!stored) {
      return null;
    }
    return JSON.parse(stored) as Bookmark[];
  } catch {
    return null;
  }
}

/**
 * Clear the bookmark index from IndexedDB and localStorage
 */
export async function clearIndex(): Promise<void> {
  // Clear IndexedDB if available
  if (isIndexedDBAvailable()) {
    try {
      await clearIndexedDB();
    } catch (error) {
      console.warn('Failed to clear IndexedDB:', error);
    }
  }

  // Also clear localStorage
  try {
    localStorage.removeItem(STORAGE_KEY);
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

