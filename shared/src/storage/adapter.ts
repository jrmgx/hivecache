/**
 * Storage adapter interface for abstracting different storage mechanisms
 * Allows both sync (localStorage) and async (chrome.storage) implementations
 */

export interface StorageAdapter {
  /**
   * Retrieves the JWT token from storage
   * @returns Promise that resolves to the JWT token or null if not found
   */
  getToken(): Promise<string | null>;

  /**
   * Stores the JWT token in storage
   * @param token The JWT token to store
   */
  setToken(token: string): Promise<void>;

  /**
   * Clears the JWT token from storage
   */
  clearToken(): Promise<void>;

  /**
   * Gets the API base URL from storage or configuration
   * @returns Promise that resolves to the base URL or null if not configured
   */
  getBaseUrl(): Promise<string | null>;
}

