/**
 * Authentication service
 * Now uses shared storage adapter, but kept for backward compatibility
 */

import { createLocalStorageAdapter } from '@shared';

const adapter = createLocalStorageAdapter();

/**
 * Set authentication token
 * @deprecated Use API client login() method instead
 */
export const setToken = async (token: string): Promise<void> => {
  await adapter.setToken(token);
};

/**
 * Get authentication token
 * @deprecated Use API client directly instead
 */
export const getToken = async (): Promise<string | null> => {
  return adapter.getToken();
};

/**
 * Clear authentication token
 * @deprecated Use API client directly instead
 */
export const clearToken = async (): Promise<void> => {
  return adapter.clearToken();
};

/**
 * Set API base URL
 */
export const setBaseUrl = async (baseUrl: string): Promise<void> => {
  await adapter.setBaseUrl(baseUrl);
};

/**
 * Get API base URL
 */
export const getBaseUrl = async (): Promise<string | null> => {
  return adapter.getBaseUrl();
};

/**
 * Check if user is authenticated
 * Uses synchronous localStorage access for backward compatibility
 */
export const isAuthenticated = (): boolean => {
  const TOKEN_KEY = 'jwt_token';
  return localStorage.getItem(TOKEN_KEY) !== null;
};

import { clearIndex } from './bookmarkIndex';

/**
 * Logout user by clearing all session data
 * Clears token and bookmark index
 */
export const logout = async (): Promise<void> => {
  // Clear authentication token
  await clearToken();

  // Clear bookmark index (IndexedDB and localStorage)
  await clearIndex();
};
