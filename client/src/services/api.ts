/**
 * API service for BookmarkHive client
 * Uses shared API client with localStorage adapter
 */

import { createApiClient, createLocalStorageAdapter, getCursorFromUrl, type ApiClient, type BookmarksResponse, type Bookmark, type Tag, type UserOwner } from '@shared';

const BASE_URL = import.meta.env.VITE_API_BASE_URL;
if (!BASE_URL) {
  throw new Error('VITE_API_BASE_URL environment variable is not set. Please check your .env file.');
}

// Create API client instance with localStorage adapter
const apiClient: ApiClient = createApiClient({
  baseUrl: BASE_URL,
  storage: createLocalStorageAdapter(),
  enableCache: true,
});

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
export const login = async (email: string, password: string): Promise<string> => {
  return apiClient.login(email, password);
};

/**
 * Register a new user account
 */
export const register = async (userData: { email: string; password: string; username: string }): Promise<UserOwner> => {
  return apiClient.register(userData);
};

/**
 * Get paginated bookmarks with optional tag filter
 */
export const getBookmarks = async (tags?: string, after?: string): Promise<BookmarksResponse> => {
  return apiClient.getBookmarks(tags, after);
};

/**
 * Get a single bookmark by ID
 */
export const getBookmark = async (id: string): Promise<Bookmark | null> => {
  return apiClient.getBookmark(id);
};

/**
 * Get bookmark history for a specific bookmark
 */
export const getBookmarkHistory = async (id: string): Promise<BookmarksResponse> => {
  return apiClient.getBookmarkHistory(id);
};

/**
 * Update bookmark tags
 */
export const updateBookmarkTags = async (id: string, tagSlugs: string[]): Promise<Bookmark> => {
  return apiClient.updateBookmarkTags(id, tagSlugs);
};

/**
 * Get all user tags
 * Unified method name: getTags (was getTags in client, fetchUserTags in extension)
 */
export const getTags = async (): Promise<Tag[]> => {
  return apiClient.getTags();
};

/**
 * Get a single tag by slug
 */
export const getTag = async (slug: string): Promise<Tag | null> => {
  return apiClient.getTag(slug);
};

/**
 * Create a new tag
 */
export const createTag = async (name: string): Promise<Tag> => {
  return apiClient.createTag(name);
};

/**
 * Update an existing tag
 */
export const updateTag = async (slug: string, tag: Tag): Promise<Tag> => {
  return apiClient.updateTag(slug, tag);
};

/**
 * Delete a tag
 */
export const deleteTag = async (slug: string): Promise<void> => {
  return apiClient.deleteTag(slug);
};

/**
 * Invalidate the tags cache
 */
export const invalidateTagsCache = (): void => {
  apiClient.invalidateTagsCache();
};
