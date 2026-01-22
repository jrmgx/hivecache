/**
 * API client configuration interface
 */

import type { StorageAdapter } from '../storage/adapter';

export interface ApiConfig {
  /**
   * Base URL for the API (e.g., 'https://hivecache.test')
   */
  baseUrl: string;

  /**
   * Storage adapter for token and configuration management
   */
  storage: StorageAdapter;

  /**
   * Enable tag caching (default: true)
   * When enabled, tags are cached and invalidated on mutations
   */
  enableCache?: boolean;
}

