/**
 * Unified API client for HiveCache
 * Supports both browser extension and web client via storage adapters
 */

import type {
  AuthRequest,
  AuthResponse,
  UserCreate,
  UserOwner,
  BookmarkCreate,
  BookmarkUpdate,
  BookmarkOwner,
  Bookmark,
  BookmarksResponse,
  Tag,
  ApiTag,
  FileObject,
  BookmarkIndexDiffResponse,
  Account,
} from '../types';
import { ApiError } from './error';
import type { ApiConfig } from './config';
import { transformTagFromApi, transformTagToApi } from '../tag/transform';

/**
 * API client interface returned by createApiClient
 */
export interface ApiClient {
  // Authentication
  login(username: string, password: string): Promise<string>;
  register(userData: UserCreate): Promise<UserOwner>;

  // Bookmarks
  getBookmarks(tags?: string, after?: string): Promise<BookmarksResponse>;
  getBookmarksIndex(after?: string): Promise<BookmarksResponse>;
  getBookmarkIndexDiff(before?: string): Promise<BookmarkIndexDiffResponse>;
  getBookmark(id: string): Promise<Bookmark | null>;
  getBookmarkHistory(id: string): Promise<BookmarksResponse>;
  getSocialTimeline(after?: string): Promise<BookmarksResponse>;
  getSocialTagBookmarks(slug: string, after?: string): Promise<BookmarksResponse>;
  getInstanceBookmarks(type: 'this' | 'other', after?: string): Promise<BookmarksResponse>;
  createBookmark(payload: BookmarkCreate): Promise<BookmarkOwner>;
  updateBookmark(id: string, payload: BookmarkUpdate): Promise<Bookmark>;
  updateBookmarkTags(id: string, tagSlugs: string[]): Promise<Bookmark>;
  deleteBookmark(id: string): Promise<void>;

  // Tags
  getTags(): Promise<Tag[]>;
  getTag(slug: string): Promise<Tag | null>;
  createTag(name: string): Promise<Tag>;
  updateTag(slug: string, tag: Tag): Promise<Tag>;
  deleteTag(slug: string): Promise<void>;
  ensureTagsExist(tagNames: string[], existingTags: Tag[]): Promise<string[]>;

  // Files
  uploadFileObject(file: File | Blob): Promise<FileObject>;

  // Cache management
  invalidateTagsCache(): void;
}

/**
 * Creates a configured API client instance
 * @param config API configuration including base URL, storage adapter, and cache settings
 * @returns Configured API client instance
 */
export function createApiClient(config: ApiConfig): ApiClient {
  const { baseUrl, storage, enableCache = true } = config;

  // Tag cache state
  let tagsCache: Tag[] | null = null;
  let tagsCachePromise: Promise<Tag[]> | null = null;

  /**
   * Gets authentication headers with token
   */
  async function getAuthHeaders(): Promise<HeadersInit> {
    const token = await storage.getToken();
    if (!token) {
      await storage.clearToken();
      invalidateTagsCache();
      throw new ApiError('Authentication failed. Please login again.', 401);
    }
    return {
      'Authorization': `Bearer ${token}`,
      'accept': 'application/json',
      'Content-Type': 'application/json',
    };
  }

  /**
   * Handles API response with error handling
   */
  async function handleResponse<T>(response: Response): Promise<T> {
    if (!response.ok) {
      if (response.status === 401) {
        await storage.clearToken();
        invalidateTagsCache();
        throw new ApiError('Authentication failed. Please login again.', 401);
      }
      const errorText = await response.text();
      throw new ApiError(errorText || `HTTP error! status: ${response.status}`, response.status);
    }
    return response.json();
  }

  /**
   * Invalidates the tags cache
   */
  function invalidateTagsCache(): void {
    if (enableCache) {
      tagsCache = null;
      tagsCachePromise = null;
    }
  }

  /**
   * Helper to convert tag slug to IRI
   */
  function tagSlugToIri(slug: string): string {
    return `${baseUrl}/users/me/tags/${slug}`;
  }


  return {
    /**
     * Authenticates the user and stores the token
     * Unified from extension's authenticate() and client's login()
     */
    async login(username: string, password: string): Promise<string> {
      const authRequest: AuthRequest = { username, password };

      const response = await fetch(`${baseUrl}/auth`, {
        method: 'POST',
        headers: {
          'accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(authRequest),
      });

      const data = await handleResponse<AuthResponse>(response);
      if (!data.token) {
        throw new ApiError('Token not found in authentication response.', 500);
      }

      await storage.setToken(data.token);
      return data.token;
    },

    /**
     * Registers a new user account
     */
    async register(userData: UserCreate): Promise<UserOwner> {
      const response = await fetch(`${baseUrl}/register`, {
        method: 'POST',
        headers: {
          'accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(userData),
      });

      return handleResponse<UserOwner>(response);
    },

    /**
     * Gets paginated bookmarks with optional tag filter
     */
    async getBookmarks(tags?: string, after?: string): Promise<BookmarksResponse> {
      let url = `${baseUrl}/users/me/bookmarks`;
      const params = new URLSearchParams();

      if (tags && tags.length > 0) {
        params.set('tags', tags);
      }
      if (after) {
        params.set('after', after);
      }

      if (params.toString()) {
        url += `?${params.toString()}`;
      }

      const response = await fetch(url, {
        method: 'GET',
        headers: await getAuthHeaders(),
      });

      const data = await handleResponse<{
        collection: BookmarkOwner[];
        nextPage: string | null;
        prevPage: string | null;
        total: number | null;
      }>(response);

      if (!data.collection) {
        throw new ApiError('Bookmarks collection not found.', 500);
      }

      // Transform tags within each bookmark
      const bookmarks: Bookmark[] = data.collection.map((bookmark) => ({
        ...bookmark,
        tags: Array.isArray(bookmark.tags) ? bookmark.tags.map(transformTagFromApi) : [],
      }));

      return {
        collection: bookmarks,
        nextPage: data.nextPage,
        prevPage: data.prevPage,
        total: data.total,
      };
    },

    /**
     * Gets paginated bookmarks for client-side indexing (more performant)
     * Uses the /index endpoint which is optimized for bulk fetching
     */
    async getBookmarksIndex(after?: string): Promise<BookmarksResponse> {
      let url = `${baseUrl}/users/me/bookmarks/search/index`;
      if (after) {
        url += `?after=${encodeURIComponent(after)}`;
      }

      const response = await fetch(url, {
        method: 'GET',
        headers: await getAuthHeaders(),
      });

      const data = await handleResponse<{
        collection: BookmarkOwner[];
        nextPage: string | null;
        prevPage: string | null;
        total: number | null;
      }>(response);

      if (!data.collection) {
        throw new ApiError('Bookmarks collection not found.', 500);
      }

      // Transform tags within each bookmark
      const bookmarks: Bookmark[] = data.collection.map((bookmark) => ({
        ...bookmark,
        tags: Array.isArray(bookmark.tags) ? bookmark.tags.map(transformTagFromApi) : [],
      }));

      return {
        collection: bookmarks,
        nextPage: data.nextPage,
        prevPage: data.prevPage,
        total: data.total,
      };
    },

    /**
     * Gets bookmark index changes (diff) for syncing client-side index
     * @param before Cursor for pagination - index action ID to fetch results after
     * @returns Collection of bookmark index actions
     */
    async getBookmarkIndexDiff(before?: string): Promise<BookmarkIndexDiffResponse> {
      let url = `${baseUrl}/users/me/bookmarks/search/diff`;
      if (before) {
        url += `?before=${encodeURIComponent(before)}`;
      }

      const response = await fetch(url, {
        method: 'GET',
        headers: await getAuthHeaders(),
      });

      return handleResponse<BookmarkIndexDiffResponse>(response);
    },

    /**
     * Gets a single bookmark by ID
     */
    async getBookmark(id: string): Promise<Bookmark | null> {
      const response = await fetch(`${baseUrl}/users/me/bookmarks/${id}`, {
        method: 'GET',
        headers: await getAuthHeaders(),
      });

      const bookmark = await handleResponse<BookmarkOwner>(response);
      // Transform tags within the bookmark
      return {
        ...bookmark,
        tags: Array.isArray(bookmark.tags) ? bookmark.tags.map(transformTagFromApi) : [],
      };
    },

    /**
     * Gets bookmark history for a specific bookmark
     * Note: History endpoint does not return pagination fields
     */
    async getBookmarkHistory(id: string): Promise<BookmarksResponse> {
      const response = await fetch(`${baseUrl}/users/me/bookmarks/${id}/history`, {
        method: 'GET',
        headers: await getAuthHeaders(),
      });

      const data = await handleResponse<{
        collection: BookmarkOwner[];
      }>(response);

      if (!data.collection) {
        throw new ApiError('Bookmarks collection not found.', 500);
      }

      // Transform tags within each bookmark
      const bookmarks: Bookmark[] = data.collection.map((bookmark) => ({
        ...bookmark,
        tags: Array.isArray(bookmark.tags) ? bookmark.tags.map(transformTagFromApi) : [],
      }));

      return {
        collection: bookmarks,
        nextPage: null,
        prevPage: null,
        total: null,
      };
    },

    /**
     * Gets social timeline bookmarks
     * Returns public bookmarks from users you follow and your instance
     */
    async getSocialTimeline(after?: string): Promise<BookmarksResponse> {
      let url = `${baseUrl}/users/me/bookmarks/social/timeline`;
      if (after) {
        url += `?after=${encodeURIComponent(after)}`;
      }

      const response = await fetch(url, {
        method: 'GET',
        headers: await getAuthHeaders(),
      });

      const data = await handleResponse<{
        collection: Array<{
          id: string;
          createdAt: string;
          title: string;
          url: string;
          domain: string;
          account?: Account;
          tags: Array<{
            '@iri': string;
            name: string;
            slug: string;
          }>;
          mainImage: FileObject | null;
          archive: FileObject | null;
          instance: string;
          '@iri': string;
        }>;
        nextPage: string | null;
        prevPage: string | null;
        total: number | null;
      }>(response);

      if (!data.collection) {
        throw new ApiError('Bookmarks collection not found.', 500);
      }

      // Transform public bookmarks to internal Bookmark format
      const bookmarks: Bookmark[] = data.collection.map((bookmark) => {
        const transformed: Bookmark & { account?: { username: string; instance?: string; '@iri': string } } = {
          ...bookmark,
          isPublic: true, // Timeline bookmarks are always public
          tags: Array.isArray(bookmark.tags)
            ? bookmark.tags.map((tag) => ({
                '@iri': tag['@iri'],
                name: tag.name,
                slug: tag.slug,
                isPublic: true, // Public tags are always public
                pinned: false, // Public tags don't have pinned info
                layout: 'default', // Public tags don't have layout info
                icon: null, // Public tags don't have icon info
              }))
            : [],
          // Transform account to owner format
          owner: bookmark.account
            ? {
                '@iri': bookmark.account['@iri'],
                username: bookmark.account.username,
                isPublic: true, // Timeline accounts are public
              }
            : {
                '@iri': bookmark['@iri'],
                username: 'unknown',
                isPublic: true,
              },
        };
        // Preserve account field with instance for profile navigation
        if (bookmark.account) {
          (transformed as any).account = {
            username: bookmark.account.username,
            instance: bookmark.instance,
            '@iri': bookmark.account['@iri'],
          };
        }
        return transformed;
      });

      return {
        collection: bookmarks,
        nextPage: data.nextPage,
        prevPage: data.prevPage,
        total: data.total,
      };
    },

    /**
     * Gets social tag bookmarks
     * Returns public bookmarks filtered by an instance tag
     */
    async getSocialTagBookmarks(slug: string, after?: string): Promise<BookmarksResponse> {
      let url = `${baseUrl}/users/me/bookmarks/social/tag/${encodeURIComponent(slug)}`;
      if (after) {
        url += `?after=${encodeURIComponent(after)}`;
      }

      const response = await fetch(url, {
        method: 'GET',
        headers: await getAuthHeaders(),
      });

      const data = await handleResponse<{
        collection: Array<{
          id: string;
          createdAt: string;
          title: string;
          url: string;
          domain: string;
          account?: Account;
          tags: Array<{
            '@iri': string;
            name: string;
            slug: string;
          }>;
          mainImage: FileObject | null;
          archive: FileObject | null;
          instance: string;
          '@iri': string;
        }>;
        nextPage: string | null;
        prevPage: string | null;
        total: number | null;
      }>(response);

      if (!data.collection) {
        throw new ApiError('Bookmarks collection not found.', 500);
      }

      // Transform public bookmarks to internal Bookmark format
      const bookmarks: Bookmark[] = data.collection.map((bookmark) => {
        const transformed: Bookmark & { account?: { username: string; instance?: string; '@iri': string } } = {
          ...bookmark,
          isPublic: true, // Social tag bookmarks are always public
          tags: Array.isArray(bookmark.tags)
            ? bookmark.tags.map((tag) => ({
                '@iri': tag['@iri'],
                name: tag.name,
                slug: tag.slug,
                isPublic: true, // Public tags are always public
                pinned: false, // Public tags don't have pinned info
                layout: 'default', // Public tags don't have layout info
                icon: null, // Public tags don't have icon info
              }))
            : [],
          // Transform account to owner format
          owner: bookmark.account
            ? {
                '@iri': bookmark.account['@iri'],
                username: bookmark.account.username,
                isPublic: true, // Social tag accounts are public
              }
            : {
                '@iri': bookmark['@iri'],
                username: 'unknown',
                isPublic: true,
              },
        };
        // Preserve account field with instance for profile navigation
        if (bookmark.account) {
          (transformed as any).account = {
            username: bookmark.account.username,
            instance: bookmark.instance,
            '@iri': bookmark.account['@iri'],
          };
        }
        return transformed;
      });

      return {
        collection: bookmarks,
        nextPage: data.nextPage,
        prevPage: data.prevPage,
        total: data.total,
      };
    },

    /**
     * Gets instance bookmarks
     * Returns public bookmarks from the current instance (type: 'this') or other instances (type: 'other')
     */
    async getInstanceBookmarks(type: 'this' | 'other', after?: string): Promise<BookmarksResponse> {
      let url = `${baseUrl}/instance/${type}`;
      if (after) {
        url += `?after=${encodeURIComponent(after)}`;
      }

      const response = await fetch(url, {
        method: 'GET',
        headers: await getAuthHeaders(),
      });

      const data = await handleResponse<{
        collection: Array<{
          id: string;
          createdAt: string;
          title: string;
          url: string;
          domain: string;
          account?: Account;
          tags: Array<{
            '@iri': string;
            name: string;
            slug: string;
          }>;
          mainImage: FileObject | null;
          archive: FileObject | null;
          instance: string;
          '@iri': string;
        }>;
        nextPage: string | null;
        prevPage: string | null;
        total: number | null;
      }>(response);

      if (!data.collection) {
        throw new ApiError('Bookmarks collection not found.', 500);
      }

      // Transform public bookmarks to internal Bookmark format
      const bookmarks: Bookmark[] = data.collection.map((bookmark) => {
        // Extract tags before spreading bookmark to avoid any conflicts
        // Handle various possible formats: array, object with numeric keys, null, undefined
        let rawTags: Array<{
          '@iri': string;
          name: string;
          slug: string;
        }> = [];
        
        if (bookmark.tags) {
          if (Array.isArray(bookmark.tags)) {
            rawTags = bookmark.tags;
          } else if (typeof bookmark.tags === 'object' && bookmark.tags !== null) {
            // If tags is an object, try to convert it to an array
            const tagsArray = Object.values(bookmark.tags) as Array<{
              '@iri'?: string;
              name?: string;
              slug?: string;
            }>;
            // Filter and map to ensure we have valid tag objects
            rawTags = tagsArray
              .filter((tag) => 
                typeof tag === 'object' && 
                tag !== null && 
                typeof tag.name === 'string' && 
                typeof tag.slug === 'string'
              )
              .map((tag) => {
                const tagWithIri = tag as { '@iri'?: string; name: string; slug: string };
                return {
                  '@iri': tagWithIri['@iri'] || '',
                  name: tagWithIri.name,
                  slug: tagWithIri.slug,
                };
              });
          }
        }
        
        const transformed: Bookmark & { account?: { username: string; instance?: string; '@iri': string } } = {
          ...bookmark,
          isPublic: true, // Instance bookmarks are always public
          tags: rawTags.map((tag) => ({
            '@iri': tag['@iri'],
            name: tag.name,
            slug: tag.slug,
            isPublic: true, // Public tags are always public
            pinned: false, // Public tags don't have pinned info
            layout: 'default', // Public tags don't have layout info
            icon: null, // Public tags don't have icon info
          })),
          // Transform account to owner format
          owner: bookmark.account
            ? {
                '@iri': bookmark.account['@iri'],
                username: bookmark.account.username,
                isPublic: true, // Instance accounts are public
              }
            : {
                '@iri': bookmark['@iri'],
                username: 'unknown',
                isPublic: true,
              },
        };
        // Preserve account field with instance for profile navigation
        if (bookmark.account) {
          (transformed as any).account = {
            username: bookmark.account.username,
            instance: bookmark.instance,
            '@iri': bookmark.account['@iri'],
          };
        }
        return transformed;
      });

      return {
        collection: bookmarks,
        nextPage: data.nextPage,
        prevPage: data.prevPage,
        total: data.total,
      };
    },

    /**
     * Creates a new bookmark
     */
    async createBookmark(payload: BookmarkCreate): Promise<BookmarkOwner> {
      const response = await fetch(`${baseUrl}/users/me/bookmarks`, {
        method: 'POST',
        headers: await getAuthHeaders(),
        body: JSON.stringify(payload),
      });

      return handleResponse<BookmarkOwner>(response);
    },

    /**
     * Updates a bookmark
     */
    async updateBookmark(id: string, payload: BookmarkUpdate): Promise<Bookmark> {
      const response = await fetch(`${baseUrl}/users/me/bookmarks/${id}`, {
        method: 'PATCH',
        headers: await getAuthHeaders(),
        body: JSON.stringify(payload),
      });

      const bookmark = await handleResponse<BookmarkOwner>(response);
      // Transform tags within the bookmark
      return {
        ...bookmark,
        tags: Array.isArray(bookmark.tags) ? bookmark.tags.map(transformTagFromApi) : [],
      };
    },

    /**
     * Updates bookmark tags
     */
    async updateBookmarkTags(id: string, tagSlugs: string[]): Promise<Bookmark> {
      const tagIris = tagSlugs.map(tagSlugToIri);
      return this.updateBookmark(id, { tags: tagIris });
    },

    /**
     * Deletes a bookmark
     */
    async deleteBookmark(id: string): Promise<void> {
      const response = await fetch(`${baseUrl}/users/me/bookmarks/${id}`, {
        method: 'DELETE',
        headers: await getAuthHeaders(),
      });

      if (!response.ok) {
        if (response.status === 401) {
          await storage.clearToken();
          invalidateTagsCache();
          throw new ApiError('Authentication failed. Please login again.', 401);
        }
        const errorText = await response.text();
        throw new ApiError(errorText || `HTTP error! status: ${response.status}`, response.status);
      }
    },

    /**
     * Gets all user tags with optional caching
     * Unified from extension's fetchUserTags() and client's getTags()
     */
    async getTags(): Promise<Tag[]> {
      // Return cached result if available
      if (enableCache && tagsCache !== null) {
        return tagsCache;
      }

      // If a request is already in progress, return that promise
      if (enableCache && tagsCachePromise !== null) {
        return tagsCachePromise;
      }

      // Make the API request
      const fetchTags = async (): Promise<Tag[]> => {
        try {
          const response = await fetch(`${baseUrl}/users/me/tags`, {
            method: 'GET',
            headers: await getAuthHeaders(),
          });

          const data = await handleResponse<{ collection: ApiTag[] }>(response);
          if (!data.collection) {
            throw new ApiError('Tags collection not found.', 500);
          }
          const tags = data.collection.map(transformTagFromApi);

          // Sort tags in natural order (case-insensitive)
          tags.sort((a, b) =>
            a.name.localeCompare(b.name, undefined, {
              numeric: true,
              sensitivity: 'base'
            })
          );

          if (enableCache) {
            tagsCache = tags;
          }
          return tags;
        } finally {
          if (enableCache) {
            tagsCachePromise = null;
          }
        }
      };

      if (enableCache) {
        tagsCachePromise = fetchTags();
        return tagsCachePromise;
      }

      return fetchTags();
    },

    /**
     * Gets a single tag by slug
     */
    async getTag(slug: string): Promise<Tag | null> {
      const response = await fetch(`${baseUrl}/users/me/tags/${slug}`, {
        method: 'GET',
        headers: await getAuthHeaders(),
      });

      const apiTag = await handleResponse<ApiTag>(response);
      return transformTagFromApi(apiTag);
    },

    /**
     * Creates a new tag
     */
    async createTag(name: string): Promise<Tag> {
      const response = await fetch(`${baseUrl}/users/me/tags`, {
        method: 'POST',
        headers: await getAuthHeaders(),
        body: JSON.stringify({ name }),
      });

      const apiTag = await handleResponse<ApiTag>(response);
      const tag = transformTagFromApi(apiTag);
      // Invalidate cache since we added a new tag
      invalidateTagsCache();
      return tag;
    },

    /**
     * Updates an existing tag
     */
    async updateTag(slug: string, tag: Tag): Promise<Tag> {
      const apiTagData = transformTagToApi(tag);
      const response = await fetch(`${baseUrl}/users/me/tags/${slug}`, {
        method: 'PATCH',
        headers: await getAuthHeaders(),
        body: JSON.stringify(apiTagData),
      });

      const apiTag = await handleResponse<ApiTag>(response);
      const updatedTag = transformTagFromApi(apiTag);
      // Invalidate cache since we updated a tag
      invalidateTagsCache();
      return updatedTag;
    },

    /**
     * Deletes a tag
     */
    async deleteTag(slug: string): Promise<void> {
      const response = await fetch(`${baseUrl}/users/me/tags/${slug}`, {
        method: 'DELETE',
        headers: await getAuthHeaders(),
      });

      if (!response.ok) {
        if (response.status === 401) {
          await storage.clearToken();
          invalidateTagsCache();
          throw new ApiError('Authentication failed. Please login again.', 401);
        }
        const errorText = await response.text();
        throw new ApiError(errorText || `HTTP error! status: ${response.status}`, response.status);
      }
      // Invalidate cache since we deleted a tag
      invalidateTagsCache();
    },

    /**
     * Ensures all selected tags exist and returns their IRIs
     * Creates new tags if they don't exist
     */
    async ensureTagsExist(tagNames: string[], existingTags: Tag[]): Promise<string[]> {
      const tagIRIs: string[] = [];

      for (const tagName of tagNames) {
        // Check if tag already exists in existingTags
        const existingTag = existingTags.find((tag) => tag.name.toLowerCase() === tagName.toLowerCase());

        if (existingTag) {
          // Use existing tag IRI from @iri property (API returns full URLs) or construct it
          const tagIRI = existingTag['@iri'] || `${baseUrl}/users/me/tags/${existingTag.slug}`;
          tagIRIs.push(tagIRI);
        } else {
          // Create new tag
          try {
            const newTag = await this.createTag(tagName);
            // Add to existingTags for future reference
            existingTags.push(newTag);
            // Use @iri property (API returns full URLs) or construct it
            const tagIRI = newTag['@iri'] || `${baseUrl}/users/me/tags/${newTag.slug}`;
            tagIRIs.push(tagIRI);
            console.log(`Created new tag: ${tagName} (slug: ${newTag.slug})`);
          } catch (error) {
            console.error(`Error creating tag "${tagName}":`, error);
            throw error;
          }
        }
      }

      return tagIRIs;
    },

    /**
     * Uploads a file and returns the file object
     */
    async uploadFileObject(file: File | Blob): Promise<FileObject> {
      const token = await storage.getToken();
      if (!token) {
        throw new ApiError('No authentication token found. Please login.', 401);
      }

      // Create FormData with the file
      const formData = new FormData();
      formData.append('file', file);

      const response = await fetch(`${baseUrl}/users/me/files`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'accept': 'application/json',
          // Don't set Content-Type - browser will set it with boundary for multipart/form-data
        },
        body: formData,
      });

      return handleResponse<FileObject>(response);
    },

    /**
     * Invalidates the tags cache
     */
    invalidateTagsCache,
  };
}
