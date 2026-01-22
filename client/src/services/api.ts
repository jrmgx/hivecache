/**
 * API service for HiveCache client
 * Uses shared API client with localStorage adapter
 */

import { createApiClient, createLocalStorageAdapter, getCursorFromUrl, type ApiClient, type BookmarksResponse, type Bookmark, type Tag, type UserOwner } from '@shared';

const storageAdapter = createLocalStorageAdapter();

// Cache for the API client instance
let apiClientCache: ApiClient | null = null;
let apiClientBaseUrl: string | null = null;

// Get API client, creating a new one if base URL changed
async function getOrCreateApiClient(): Promise<ApiClient> {
  const baseUrl = await storageAdapter.getBaseUrl();
  if (!baseUrl) {
    throw new Error('API base URL is not configured. Please set your instance URL in the login form.');
  }

  // If base URL changed or client doesn't exist, create a new one
  if (!apiClientCache || apiClientBaseUrl !== baseUrl) {
    apiClientCache = createApiClient({
      baseUrl,
      storage: storageAdapter,
      enableCache: true,
    });
    apiClientBaseUrl = baseUrl;
  }

  return apiClientCache;
}

// Re-export ApiError for backward compatibility
export { ApiError } from '@shared';

// Re-export types for backward compatibility
export type { UserCreate, UserOwner } from '@shared';

/**
 * Extract cursor from pagination URL
 * Re-exported from shared utils
 */
export { getCursorFromUrl };

/**
 * Login and store token
 * Unified method name: login (was login in client, authenticate in extension)
 */
export const login = async (instanceUrl: string, username: string, password: string): Promise<string> => {
  // Save instance URL first
  await storageAdapter.setBaseUrl(instanceUrl);

  // Get fresh API client with new base URL
  const client = await getOrCreateApiClient();

  return client.login(username, password);
};

/**
 * Register a new user account
 */
export const register = async (instanceUrl: string, userData: { password: string; username: string }): Promise<UserOwner> => {
  // Save instance URL first
  await storageAdapter.setBaseUrl(instanceUrl);

  // Get fresh API client with new base URL
  const client = await getOrCreateApiClient();

  return client.register(userData);
};

/**
 * Get paginated bookmarks with optional tag filter
 */
export const getBookmarks = async (tags?: string, after?: string): Promise<BookmarksResponse> => {
  const client = await getOrCreateApiClient();
  return client.getBookmarks(tags, after);
};

/**
 * Get paginated bookmarks for client-side indexing (more performant)
 * Uses the /index endpoint which is optimized for bulk fetching
 */
export const getBookmarksIndex = async (after?: string): Promise<BookmarksResponse> => {
  const client = await getOrCreateApiClient();
  return client.getBookmarksIndex(after);
};

/**
 * Get bookmark index changes (diff) for syncing client-side index
 */
export const getBookmarkIndexDiff = async (before?: string) => {
  const client = await getOrCreateApiClient();
  return client.getBookmarkIndexDiff(before);
};

/**
 * Get a single bookmark by ID
 */
export const getBookmark = async (id: string): Promise<Bookmark | null> => {
  const client = await getOrCreateApiClient();
  return client.getBookmark(id);
};

/**
 * Get bookmark history for a specific bookmark
 */
export const getBookmarkHistory = async (id: string): Promise<BookmarksResponse> => {
  const client = await getOrCreateApiClient();
  return client.getBookmarkHistory(id);
};

/**
 * Get social timeline bookmarks
 * Returns public bookmarks from users you follow and your instance
 */
export const getSocialTimeline = async (after?: string): Promise<BookmarksResponse> => {
  const client = await getOrCreateApiClient();
  return client.getSocialTimeline(after);
};

/**
 * Get social tag bookmarks
 * Returns public bookmarks filtered by an instance tag
 */
export const getSocialTagBookmarks = async (slug: string, after?: string): Promise<BookmarksResponse> => {
  const client = await getOrCreateApiClient();
  return client.getSocialTagBookmarks(slug, after);
};

/**
 * Get instance bookmarks
 * Returns public bookmarks from the current instance (type: 'this') or other instances (type: 'other')
 */
export const getInstanceBookmarks = async (type: 'this' | 'other', after?: string): Promise<BookmarksResponse> => {
  const client = await getOrCreateApiClient();
  return client.getInstanceBookmarks(type, after);
};

/**
 * Update a bookmark
 */
export const updateBookmark = async (id: string, payload: { title?: string; isPublic?: boolean; tags?: string[]; mainImage?: string | null; archive?: string | null }): Promise<Bookmark> => {
  const client = await getOrCreateApiClient();
  return client.updateBookmark(id, payload);
};

/**
 * Update bookmark tags
 */
export const updateBookmarkTags = async (id: string, tagSlugs: string[]): Promise<Bookmark> => {
  const client = await getOrCreateApiClient();
  return client.updateBookmarkTags(id, tagSlugs);
};

/**
 * Delete a bookmark
 */
export const deleteBookmark = async (id: string): Promise<void> => {
  const client = await getOrCreateApiClient();
  return client.deleteBookmark(id);
};

/**
 * Get all user tags
 * Unified method name: getTags (was getTags in client, fetchUserTags in extension)
 */
export const getTags = async (): Promise<Tag[]> => {
  const client = await getOrCreateApiClient();
  return client.getTags();
};

/**
 * Get a single tag by slug
 */
export const getTag = async (slug: string): Promise<Tag | null> => {
  const client = await getOrCreateApiClient();
  return client.getTag(slug);
};

/**
 * Create a new tag
 */
export const createTag = async (name: string): Promise<Tag> => {
  const client = await getOrCreateApiClient();
  return client.createTag(name);
};

/**
 * Update an existing tag
 */
export const updateTag = async (slug: string, tag: Tag): Promise<Tag> => {
  const client = await getOrCreateApiClient();
  return client.updateTag(slug, tag);
};

/**
 * Delete a tag
 */
export const deleteTag = async (slug: string): Promise<void> => {
  const client = await getOrCreateApiClient();
  return client.deleteTag(slug);
};

/**
 * Invalidate the tags cache
 */
export const invalidateTagsCache = (): void => {
  // Note: This invalidates cache on the current client instance
  // If base URL changes, a new client will be created anyway
  if (apiClientCache) {
    apiClientCache.invalidateTagsCache();
  }
};

/**
 * Webfinger response structure
 */
interface WebfingerLink {
  rel: string;
  type?: string;
  href?: string;
  template?: string;
}

interface WebfingerResponse {
  subject: string;
  aliases?: string[];
  links: WebfingerLink[];
}

/**
 * Query webfinger endpoint on a specific instance
 * @param acct - Account identifier parameter (e.g., username@instance.host)
 * @param instanceHost - Optional instance host. If not provided, uses the current instance from storage
 * @returns WebFinger JSON response
 */
export const webfinger = async (acct: string, instanceHost?: string): Promise<WebfingerResponse> => {
  let baseUrl: string;

  if (instanceHost) {
    // Use provided instance host
    const protocol = instanceHost.includes('localhost') || instanceHost.includes('127.0.0.1') ? 'http' : 'https';
    baseUrl = `${protocol}://${instanceHost}`;
  } else {
    // Use current instance from storage
    const storedBaseUrl = await storageAdapter.getBaseUrl();
    if (!storedBaseUrl) {
      throw new Error('API base URL is not configured. Please set your instance URL in the login form.');
    }
    baseUrl = storedBaseUrl;
  }

  const url = new URL('/.well-known/webfinger', baseUrl);
  url.searchParams.set('resource', 'acct:' + acct); // ie: `acct:jrmgx@mamot.fr`

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers: {
      'Accept': 'application/jrd+json',
    },
  });

  if (!response.ok) {
    throw new Error(`WebFinger request failed: ${response.status} ${response.statusText}`);
  }

  return response.json();
};
