/**
 * Browser extension storage adapter using chrome.storage.local
 * Uses async chrome.storage API
 */

import type { StorageAdapter } from './adapter';

/**
 * Type definition for browser storage API
 */
interface BrowserStorage {
  local: {
    get(keys: string[], callback: (result: Record<string, unknown>) => void): void;
    set(items: Record<string, unknown>, callback?: () => void): void;
    remove(keys: string[], callback?: () => void): void;
  };
}

/**
 * Gets the browser storage API (chrome or browser)
 */
function getBrowserStorage(): BrowserStorage | null {
  // @ts-expect-error - browser may not be defined in Chrome/Edge
  const browserAPI = typeof browser !== 'undefined' ? browser : typeof chrome !== 'undefined' ? chrome : null;
  return browserAPI?.storage || null;
}

/**
 * Creates a browser extension storage adapter
 * Uses chrome.storage.local for token and API host storage
 */
export function createBrowserStorageAdapter(): StorageAdapter {
  return {
    async getToken(): Promise<string | null> {
      const storage = getBrowserStorage();
      if (!storage) {
        console.error('Storage API not available');
        return null;
      }

      return new Promise((resolve) => {
        storage.local.get(['jwtToken'], (result: { jwtToken?: string }) => {
          // @ts-expect-error - chrome.runtime.lastError may not exist
          if (chrome?.runtime?.lastError) {
            console.error('Error retrieving token:', chrome.runtime.lastError.message);
            resolve(null);
          } else {
            resolve(result.jwtToken || null);
          }
        });
      });
    },

    async setToken(token: string): Promise<void> {
      const storage = getBrowserStorage();
      if (!storage) {
        console.error('Storage API not available');
        return;
      }

      return new Promise((resolve) => {
        storage.local.set({ jwtToken: token }, () => {
          // @ts-expect-error - chrome.runtime.lastError may not exist
          if (chrome?.runtime?.lastError) {
            console.error('Error storing token:', chrome.runtime.lastError.message);
          }
          resolve();
        });
      });
    },

    async clearToken(): Promise<void> {
      const storage = getBrowserStorage();
      if (!storage) {
        console.error('Storage API not available');
        return;
      }

      return new Promise((resolve) => {
        storage.local.remove(['jwtToken'], () => {
          // @ts-expect-error - chrome.runtime.lastError may not exist
          if (chrome?.runtime?.lastError) {
            console.error('Error clearing token:', chrome.runtime.lastError.message);
          }
          resolve();
        });
      });
    },

    async getBaseUrl(): Promise<string | null> {
      const storage = getBrowserStorage();
      if (!storage) {
        console.error('Storage API not available');
        return null;
      }

      return new Promise((resolve) => {
        storage.local.get(['apiHost'], (result: { apiHost?: string }) => {
          // @ts-expect-error - chrome.runtime.lastError may not exist
          if (chrome?.runtime?.lastError) {
            console.error('Error retrieving API host:', chrome.runtime.lastError.message);
            resolve(null);
          } else {
            resolve(result.apiHost || null);
          }
        });
      });
    },
  };
}

