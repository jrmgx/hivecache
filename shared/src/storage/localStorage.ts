/**
 * localStorage-based storage adapter for client applications
 * Uses synchronous localStorage API wrapped in Promises for consistency
 */

import type { StorageAdapter } from './adapter';

const TOKEN_KEY = 'jwt_token';
const BASE_URL_KEY = 'api_base_url';

/**
 * Creates a localStorage-based storage adapter
 * @param defaultBaseUrl Optional default base URL if not found in storage
 */
export function createLocalStorageAdapter(defaultBaseUrl?: string): StorageAdapter {
  return {
    async getToken(): Promise<string | null> {
      return localStorage.getItem(TOKEN_KEY);
    },

    async setToken(token: string): Promise<void> {
      localStorage.setItem(TOKEN_KEY, token);
    },

    async clearToken(): Promise<void> {
      localStorage.removeItem(TOKEN_KEY);
    },

    async getBaseUrl(): Promise<string | null> {
      const stored = localStorage.getItem(BASE_URL_KEY);
      if (stored) {
        return stored;
      }
      return defaultBaseUrl || null;
    },
  };
}

