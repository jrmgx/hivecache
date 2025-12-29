/**
 * Unified API client for BookmarkHive
 * Supports both browser extension and web client via storage adapters
 */

import type {
  AuthRequest,
  AuthResponse,
  UserCreate,
  UserOwner,
  BookmarkCreate,
  BookmarkOwner,
  Bookmark,
  BookmarksResponse,
  Tag,
  ApiTag,
  FileObject,
} from '../types';
import { ApiError } from './error';
import type { ApiConfig } from './config';
import { transformTagFromApi, transformTagToApi } from '../tag/transform';

/**
 * API client interface returned by createApiClient
 */
export interface ApiClient {
  // Authentication
  login(email: string, password: string): Promise<string>;
  register(userData: UserCreate): Promise<UserOwner>;

  // Bookmarks
  getBookmarks(tags?: string, after?: string): Promise<BookmarksResponse>;
  getBookmark(id: string): Promise<Bookmark | null>;
  getBookmarkHistory(id: string): Promise<BookmarksResponse>;
  createBookmark(payload: BookmarkCreate): Promise<BookmarkOwner>;
  updateBookmarkTags(id: string, tagSlugs: string[]): Promise<Bookmark>;

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
    async login(email: string, password: string): Promise<string> {
      const authRequest: AuthRequest = { email, password };

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
      const response = await fetch(`${baseUrl}/account`, {
        method: 'POST',
        headers: {
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
        tags: bookmark.tags ? bookmark.tags.map(transformTagFromApi) : [],
      }));

      return {
        collection: bookmarks,
        nextPage: data.nextPage,
        prevPage: data.prevPage,
        total: data.total,
      };
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
        tags: bookmark.tags ? bookmark.tags.map(transformTagFromApi) : [],
      };
    },

    /**
     * Gets bookmark history for a specific bookmark
     */
    async getBookmarkHistory(id: string): Promise<BookmarksResponse> {
      const response = await fetch(`${baseUrl}/users/me/bookmarks/${id}/history`, {
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
        tags: bookmark.tags ? bookmark.tags.map(transformTagFromApi) : [],
      }));

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
     * Updates bookmark tags
     */
    async updateBookmarkTags(id: string, tagSlugs: string[]): Promise<Bookmark> {
      const tagIris = tagSlugs.map(tagSlugToIri);
      const response = await fetch(`${baseUrl}/users/me/bookmarks/${id}`, {
        method: 'PATCH',
        headers: await getAuthHeaders(),
        body: JSON.stringify({ tags: tagIris }),
      });

      const bookmark = await handleResponse<BookmarkOwner>(response);
      // Transform tags within the bookmark
      return {
        ...bookmark,
        tags: bookmark.tags ? bookmark.tags.map(transformTagFromApi) : [],
      };
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
