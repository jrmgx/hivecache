import { getToken, clearToken } from './auth';
import type { Bookmark, Tag, FileObject, User } from '../types';
import { LAYOUT_DEFAULT } from '../types';

const BASE_URL = import.meta.env.VITE_API_BASE_URL || 'https://bookmarkhive.test';
const META_PREFIX = 'client-o-';

// API response types
interface ApiTagMeta {
  [key: string]: string | boolean | number | null | undefined;
}

interface ApiTag {
  slug: string;
  name: string;
  isPublic: boolean;
  meta?: ApiTagMeta;
}

interface ApiTagRequest {
  slug: string;
  name: string;
  isPublic: boolean;
  meta?: ApiTagMeta;
}

interface ApiBookmark {
  id: string;
  createdAt: string;
  title: string;
  url: string;
  tags: ApiTag[];
  owner: User;
  mainImage: FileObject | null;
  pdf: FileObject | null;
  archive: FileObject | null;
  isPublic: boolean;
}

// Cache for tags (since they don't change often)
let tagsCache: Tag[] | null = null;
let tagsCachePromise: Promise<Tag[]> | null = null;

/**
 * Transform API tag response to extract pinned, layout, and icon from meta object
 */
const transformTagFromApi = (apiTag: ApiTag): Tag => {
  const meta = apiTag.meta || {};
  const iconValue = meta[`${META_PREFIX}icon`];
  const icon = iconValue != null && iconValue !== false && iconValue !== '' && String(iconValue).trim() !== ''
    ? String(iconValue)
    : null;

  return {
    slug: apiTag.slug,
    name: apiTag.name,
    isPublic: apiTag.isPublic ?? false,
    pinned: Boolean(meta[`${META_PREFIX}pinned`] ?? false),
    layout: String(meta[`${META_PREFIX}layout`] ?? LAYOUT_DEFAULT),
    icon,
  };
};

/**
 * Transform tag to API format with meta object
 */
const transformTagToApi = (tag: Tag): ApiTagRequest => {
  const meta: ApiTagMeta = {};
  if (tag.pinned) {
    meta[`${META_PREFIX}pinned`] = tag.pinned;
  }
  if (tag.layout && tag.layout !== LAYOUT_DEFAULT) {
    meta[`${META_PREFIX}layout`] = tag.layout;
  }
  if (tag.icon) {
    meta[`${META_PREFIX}icon`] = tag.icon;
  }

  return {
    slug: tag.slug,
    name: tag.name,
    isPublic: tag.isPublic,
    ...(Object.keys(meta).length > 0 ? { meta } : {}),
  };
};

const getAuthHeaders = (): HeadersInit => {
  const token = getToken();
  if (!token) {
    throw new Error('JWT token not found.');
  }
  return {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
  };
};

/**
 * Invalidate the tags cache
 */
export const invalidateTagsCache = (): void => {
  tagsCache = null;
  tagsCachePromise = null;
};

const handleResponse = async <T>(response: Response): Promise<T> => {
  if (!response.ok) {
    if (response.status === 401) {
      clearToken();
      // Clear tags cache on logout/auth failure since tags are user-specific
      invalidateTagsCache();
      throw new Error('Authentication failed. Please login again.');
    }
    const errorText = await response.text();
    throw new Error(errorText || `HTTP error! status: ${response.status}`);
  }
  return response.json();
};

export const login = async (email: string, password: string): Promise<string> => {
  const response = await fetch(`${BASE_URL}/api/auth`, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ email, password }),
  });

  const data = await handleResponse<{ token: string }>(response);
  if (!data.token) {
    throw new Error('Token not found in authentication response.');
  }
  return data.token;
};

export interface UserCreate {
  email: string;
  password: string;
  username: string;
}

export interface UserOwner extends User {
  email: string;
}

export const register = async (userData: UserCreate): Promise<UserOwner> => {
  const response = await fetch(`${BASE_URL}/api/account`, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(userData),
  });

  return handleResponse<UserOwner>(response);
};

export interface BookmarksResponse {
  collection: Bookmark[];
  nextPage: string | null;
  prevPage: string | null;
  total: number | null;
}

/**
 * Extract cursor from pagination URL
 */
export const getCursorFromUrl = (url: string | null): string | undefined => {
  if (!url) return undefined;
  try {
    // Handle relative URLs (e.g., /api/users/me/bookmarks?after=...)
    const urlObj = url.startsWith('http')
      ? new URL(url)
      : new URL(url, window.location.origin);
    return urlObj.searchParams.get('after') || undefined;
  } catch {
    return undefined;
  }
};

export const getBookmarks = async (tags?: string, after?: string): Promise<BookmarksResponse> => {
  let url = `${BASE_URL}/api/users/me/bookmarks`;
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
    headers: getAuthHeaders(),
  });

  const data = await handleResponse<{ collection: ApiBookmark[]; nextPage: string | null; prevPage: string | null; total: number | null }>(response);
  if (!data.collection) {
    throw new Error('Bookmarks collection not found.');
  }

  // Transform tags within each bookmark
  const bookmarks = data.collection.map((bookmark) => ({
    ...bookmark,
    tags: bookmark.tags ? bookmark.tags.map(transformTagFromApi) : [],
  }));

  return {
    collection: bookmarks,
    nextPage: data.nextPage,
    prevPage: data.prevPage,
    total: data.total,
  };
};

export const getBookmark = async (id: string): Promise<Bookmark | null> => {
  const response = await fetch(`${BASE_URL}/api/users/me/bookmarks/${id}`, {
    method: 'GET',
    headers: getAuthHeaders(),
  });

  const bookmark = await handleResponse<ApiBookmark>(response);
  // Transform tags within the bookmark
  return {
    ...bookmark,
    tags: bookmark.tags ? bookmark.tags.map(transformTagFromApi) : [],
  };
};

/**
 * Helper function to convert tag slug to IRI
 */
const tagSlugToIri = (slug: string): string => {
  return `/api/users/me/tags/${slug}`;
};

export const updateBookmarkTags = async (id: string, tagSlugs: string[]): Promise<Bookmark> => {
  const tagIris = tagSlugs.map(tagSlugToIri);
  const response = await fetch(`${BASE_URL}/api/users/me/bookmarks/${id}`, {
    method: 'PATCH',
    headers: getAuthHeaders(),
    body: JSON.stringify({ tags: tagIris }),
  });

  const bookmark = await handleResponse<ApiBookmark>(response);
  // Transform tags within the bookmark
  return {
    ...bookmark,
    tags: bookmark.tags ? bookmark.tags.map(transformTagFromApi) : [],
  };
};

export const getTags = async (): Promise<Tag[]> => {
  // Return cached result if available
  if (tagsCache !== null) {
    return tagsCache;
  }

  // If a request is already in progress, return that promise
  if (tagsCachePromise !== null) {
    return tagsCachePromise;
  }

  // Make the API request and cache the result
  tagsCachePromise = (async () => {
    try {
      const response = await fetch(`${BASE_URL}/api/users/me/tags`, {
        method: 'GET',
        headers: getAuthHeaders(),
      });

      const data = await handleResponse<{ collection: ApiTag[] }>(response);
      if (!data.collection) {
        throw new Error('Tags collection not found.');
      }
      const tags = data.collection.map(transformTagFromApi);
      tagsCache = tags;
      return tags;
    } finally {
      // Clear the promise cache after completion (success or error)
      tagsCachePromise = null;
    }
  })();

  return tagsCachePromise;
};

export const getTag = async (slug: string): Promise<Tag | null> => {
  const response = await fetch(`${BASE_URL}/api/users/me/tags/${slug}`, {
    method: 'GET',
    headers: getAuthHeaders(),
  });

  const apiTag = await handleResponse<ApiTag>(response);
  return transformTagFromApi(apiTag);
};

export const createTag = async (name: string): Promise<Tag> => {
  const response = await fetch(`${BASE_URL}/api/users/me/tags`, {
    method: 'POST',
    headers: getAuthHeaders(),
    body: JSON.stringify({ name }),
  });

  const apiTag = await handleResponse<ApiTag>(response);
  const tag = transformTagFromApi(apiTag);
  // Invalidate cache since we added a new tag
  invalidateTagsCache();
  return tag;
};

export const updateTag = async (slug: string, tag: Tag): Promise<Tag> => {
  const apiTagData = transformTagToApi(tag);
  const response = await fetch(`${BASE_URL}/api/users/me/tags/${slug}`, {
    method: 'PATCH',
    headers: getAuthHeaders(),
    body: JSON.stringify(apiTagData),
  });

  const apiTag = await handleResponse<ApiTag>(response);
  const updatedTag = transformTagFromApi(apiTag);
  // Invalidate cache since we updated a tag
  invalidateTagsCache();
  return updatedTag;
};

export const deleteTag = async (slug: string): Promise<void> => {
  const response = await fetch(`${BASE_URL}/api/users/me/tags/${slug}`, {
    method: 'DELETE',
    headers: getAuthHeaders(),
  });

  if (!response.ok) {
    if (response.status === 401) {
      clearToken();
      // Cache already invalidated in handleResponse for 401, but we call it here too for consistency
      invalidateTagsCache();
      throw new Error('Authentication failed. Please login again.');
    }
    throw new Error(`HTTP error! status: ${response.status}`);
  }
  // Invalidate cache since we deleted a tag
  invalidateTagsCache();
};
