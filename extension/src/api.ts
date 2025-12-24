/**
 * API interaction functions for communicating with the backend
 * Uses shared API client with browser storage adapter
 */

import { createApiClient, createBrowserStorageAdapter } from '@shared';
import type { BookmarkCreate, Tag, FileObject } from '@shared';
import { getBrowserStorage } from './lib/browser';

// Create API client instance with browser storage adapter
let apiClient: ReturnType<typeof createApiClient> | null = null;

/**
 * Gets or creates the API client instance
 */
async function getApiClient() {
  if (!apiClient) {
    const storage = getBrowserStorage();
    if (!storage) {
      throw new Error('Storage API not available');
    }

    const apiHost = await getAPIHost();
    if (!apiHost) {
      throw new Error('API host not configured');
    }

    const adapter = createBrowserStorageAdapter();
    apiClient = createApiClient({
      baseUrl: apiHost,
      storage: adapter,
      enableCache: true,
    });
  }
  return apiClient;
}

/**
 * Gets the API host from storage
 * @returns Promise that resolves to the API host or null if not configured
 */
export async function getAPIHost(): Promise<string | null> {
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
}

/**
 * Retrieves the JWT token from secure storage
 * @returns Promise that resolves to the JWT token or null if not found
 */
export async function getJWTToken(): Promise<string | null> {
  const adapter = createBrowserStorageAdapter();
  return adapter.getToken();
}

/**
 * Clears the JWT token from storage
 */
export async function clearJWTToken(): Promise<void> {
  const adapter = createBrowserStorageAdapter();
  return adapter.clearToken();
}

/**
 * Authenticates the user and stores the token
 * Unified method name: login (was authenticate)
 */
export async function authenticate(email: string, password: string): Promise<{ token: string }> {
  const client = await getApiClient();
  const token = await client.login(email, password);
  return { token };
}

/**
 * Uploads a file to the API and returns the file object response
 * Updated to use shared client (no longer requires apiHost parameter)
 */
export async function uploadFileObject(file: File | Blob): Promise<FileObject> {
  const client = await getApiClient();
  return client.uploadFileObject(file);
}

/**
 * Creates a bookmark via API
 * Updated to use shared client (no longer requires apiHost parameter)
 */
export async function createBookmark(payload: BookmarkCreate): Promise<unknown> {
  const client = await getApiClient();
  return client.createBookmark(payload);
}

/**
 * Creates a new tag via API
 * Updated to use shared client (no longer requires apiHost parameter)
 */
export async function createTag(tagName: string): Promise<Tag> {
  const client = await getApiClient();
  return client.createTag(tagName);
}

/**
 * Fetches all user tags from the API
 * Unified method name: getTags (was fetchUserTags)
 */
export async function fetchUserTags(): Promise<Tag[]> {
  const client = await getApiClient();
  return client.getTags();
}

/**
 * Ensures all selected tags exist and returns their IRIs
 * Creates new tags if they don't exist
 * Updated to use shared client (no longer requires apiHost parameter)
 */
export async function ensureTagsExist(
  selectedTagNames: string[],
  existingTags: Tag[]
): Promise<string[]> {
  const client = await getApiClient();
  return client.ensureTagsExist(selectedTagNames, existingTags);
}
