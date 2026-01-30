/**
 * Public API service for accessing public profiles, bookmarks, and tags
 * Includes webfinger resolution with caching
 */

import { ApiError } from '@shared';
import type { BookmarksResponse, Bookmark, Tag, UserProfile, FileObject } from '@shared';

// Webfinger cache structure
interface WebfingerCacheEntry {
  baseUrl: string;
  username: string;
  timestamp: number;
}

// Webfinger response structure
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

// API response types for public endpoints
interface ApiTagProfile {
  '@iri': string;
  name: string;
  slug: string;
}

interface ApiBookmarkProfile {
  '@iri': string;
  url: string;
  title: string;
  id: string;
  createdAt: string;
  tags?: ApiTagProfile[];
  domain?: string;
  owner?: {
    '@iri': string;
    username: string;
    isPublic: boolean;
  };
  archive?: {
    '@iri': string;
    contentUrl: string | null;
    size: number;
    mime: string;
  } | null;
  mainImage?: {
    '@iri': string;
    contentUrl: string | null;
    size: number;
    mime: string;
  } | null;
}

const WEBFINGER_CACHE_TTL = 24 * 60 * 60 * 1000; // 1 day in milliseconds
const WEBFINGER_CACHE_PREFIX = 'webfinger:';

/**
 * Parse profile identifier (USERNAME@instance.host) into username and instance host
 */
export function parseProfileIdentifier(profileIdentifier: string): { username: string; instanceHost: string } {
  const match = profileIdentifier.match(/^([^@]+)@(.+)$/);
  if (!match) {
    throw new Error(`Invalid profile identifier format: ${profileIdentifier}. Expected format: USERNAME@instance.host`);
  }
  return {
    username: match[1],
    instanceHost: match[2],
  };
}

/**
 * Extract API base URL from webfinger response
 * Looks for the 'self' link with type 'application/activity+json' or profile-page link
 */
function extractApiBaseUrl(webfingerResponse: WebfingerResponse): string {
  if (!webfingerResponse.links || !Array.isArray(webfingerResponse.links)) {
    throw new Error('Invalid webfinger response: missing links array');
  }

  // Try to find 'self' link with application/activity+json type first
  let profileUrl: string | null = null;
  for (const link of webfingerResponse.links) {
    if (link.rel === 'self' && link.type === 'application/activity+json' && link.href) {
      profileUrl = link.href;
      break;
    }
  }

  // Fallback to profile-page link
  if (!profileUrl) {
    for (const link of webfingerResponse.links) {
      if (link.rel === 'http://webfinger.net/rel/profile-page' && link.href) {
        profileUrl = link.href;
        break;
      }
    }
  }

  if (!profileUrl) {
    throw new Error('Invalid webfinger response: no profile URL found in links');
  }

  // Extract base URL from profile URL (e.g., https://hivecache.test/profile/username -> https://hivecache.test)
  try {
    const url = new URL(profileUrl);
    return `${url.protocol}//${url.host}`;
  } catch (e) {
    throw new Error(`Invalid profile URL in webfinger response: ${profileUrl}`);
  }
}

/**
 * Query webfinger endpoint on a specific instance
 * @param instanceHost - The instance host (e.g., hivecache.test)
 * @param acct - Account identifier (e.g., username@instance.host)
 * @returns WebFinger JSON response
 */
async function queryWebfinger(instanceHost: string, acct: string): Promise<WebfingerResponse> {
  // Construct webfinger URL - use https by default, fallback to http for localhost
  const protocol = instanceHost.includes('localhost') || instanceHost.includes('127.0.0.1') ? 'http' : 'https';
  const webfingerUrl = `${protocol}://${instanceHost}/.well-known/webfinger`;
  const url = new URL(webfingerUrl);
  url.searchParams.set('resource', 'acct:' + acct);

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers: {
      'Accept': 'application/jrd+json',
    },
  });

  if (!response.ok) {
    throw new ApiError(`WebFinger request failed: ${response.status} ${response.statusText}`, response.status);
  }

  return response.json();
}

/**
 * Resolve profile identifier to API base URL and username using webfinger
 * Caches the result for 1 day
 */
export async function resolveProfile(profileIdentifier: string): Promise<{ baseUrl: string; username: string }> {
  const { username, instanceHost } = parseProfileIdentifier(profileIdentifier);
  const cacheKey = `${WEBFINGER_CACHE_PREFIX}${profileIdentifier}`;

  // Check cache
  const cached = localStorage.getItem(cacheKey);
  if (cached) {
    try {
      const entry: WebfingerCacheEntry = JSON.parse(cached);
      const now = Date.now();
      if (now - entry.timestamp < WEBFINGER_CACHE_TTL) {
        return { baseUrl: entry.baseUrl, username: entry.username };
      }
      // Cache expired, remove it
      localStorage.removeItem(cacheKey);
    } catch (e) {
      // Invalid cache entry, remove it
      localStorage.removeItem(cacheKey);
    }
  }

  // Query webfinger
  const acct = `${username}@${instanceHost}`;
  const webfingerResponse = await queryWebfinger(instanceHost, acct);
  const baseUrl = extractApiBaseUrl(webfingerResponse);

  // Cache the result (cache base URL and username, not the whole response)
  const cacheEntry: WebfingerCacheEntry = {
    baseUrl,
    username,
    timestamp: Date.now(),
  };
  localStorage.setItem(cacheKey, JSON.stringify(cacheEntry));

  return { baseUrl, username };
}

/**
 * Get public user profile
 */
export async function getPublicProfile(baseUrl: string, username: string): Promise<UserProfile> {
  const response = await fetch(`${baseUrl}/profile/${encodeURIComponent(username)}`, {
    method: 'GET',
    headers: {
      'Accept': 'application/json',
    },
  });

  if (!response.ok) {
    const errorText = await response.text();
    throw new ApiError(errorText || `HTTP error! status: ${response.status}`, response.status);
  }

  return response.json();
}

/**
 * Get public bookmarks for a user
 */
export async function getPublicBookmarks(
  baseUrl: string,
  username: string,
  tags?: string,
  after?: string
): Promise<BookmarksResponse> {
  let url = `${baseUrl}/profile/${encodeURIComponent(username)}/bookmarks`;
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
    headers: {
      'Accept': 'application/json',
    },
  });
  if (!response.ok) {
    const errorText = await response.text();
    throw new ApiError(errorText || `HTTP error! status: ${response.status}`, response.status);
  }

  const data = await response.json();

  if (!data.collection) {
    throw new ApiError('Bookmarks collection not found.', 500);
  }

  const transformFileObject = (file: { '@iri'?: string; contentUrl: string | null; size: number; mime: string } | null): FileObject | null => {
    if (!file) return null;
    
    // Use @iri if available, otherwise fallback to contentUrl
    const iri = file['@iri'] || file.contentUrl;
    if (!iri) return null;
    
    try {
      const path = new URL(iri).pathname;
      const id = path.split('/').pop() || '';
      return {
        '@iri': iri,
        id,
        contentUrl: file.contentUrl,
        size: file.size,
        mime: file.mime,
      };
    } catch {
      // Fallback: extract ID from IRI path if it's not a valid URL
      const path = iri.includes('/') ? iri.split('/').pop() || '' : iri;
      return {
        '@iri': iri,
        id: path,
        contentUrl: file.contentUrl,
        size: file.size,
        mime: file.mime,
      };
    }
  };

  // Transform tags within each bookmark
  // Public bookmarks use ApiTagProfile which has simpler structure (no isPublic, no meta)
  const bookmarks: Bookmark[] = data.collection.map((bookmark: ApiBookmarkProfile) => {
    const { tags: rawTags, ...bookmarkWithoutTags } = bookmark;
    const transformedTags = Array.isArray(rawTags)
    ? rawTags.map((tag: ApiTagProfile) => {
      // ApiTagProfile doesn't have isPublic or meta, so we need to transform it manually
      return {
        '@iri': tag['@iri'],
        name: tag.name,
        slug: tag.slug,
        isPublic: true, // Public tags are always public
        pinned: false, // Public tags don't have pinned info
        layout: 'default', // Public tags don't have layout info
        icon: null, // Public tags don't have icon info
      };
    })
    : [];

    return {
      ...bookmarkWithoutTags,
      tags: transformedTags,
      // Public bookmarks don't have domain or isPublic fields, set defaults
      domain: bookmark.domain || new URL(bookmark.url).hostname,
      isPublic: true,
      // Owner is simplified in public bookmarks
      owner: bookmark.owner || { '@iri': bookmark['@iri'], username, isPublic: true },
      // Transform mainImage and archive to include id field
      mainImage: transformFileObject(bookmark.mainImage || null),
      archive: transformFileObject(bookmark.archive || null),
    };
  });

  return {
    collection: bookmarks,
    nextPage: data.nextPage,
    prevPage: data.prevPage,
    total: data.total,
  };
}

/**
 * Get public tags for a user
 */
export async function getPublicTags(baseUrl: string, username: string): Promise<Tag[]> {
  const response = await fetch(`${baseUrl}/profile/${encodeURIComponent(username)}/tags`, {
    method: 'GET',
    headers: {
      'Accept': 'application/json',
    },
  });

  if (!response.ok) {
    const errorText = await response.text();
    throw new ApiError(errorText || `HTTP error! status: ${response.status}`, response.status);
  }

  const data = await response.json();
  if (!data.collection) {
    throw new ApiError('Tags collection not found.', 500);
  }

  // Transform tags from API format
  // Public tags use ApiTagProfile which has simpler structure (no isPublic, no meta)
  const tags = data.collection.map((tag: ApiTagProfile) => ({
    '@iri': tag['@iri'],
    name: tag.name,
    slug: tag.slug,
    isPublic: true, // Public tags are always public
    pinned: false, // Public tags don't have pinned info
    layout: 'default', // Public tags don't have layout info
    icon: null, // Public tags don't have icon info
  }));

  // Sort tags in natural order (case-insensitive)
  tags.sort((a: Tag, b: Tag) =>
    a.name.localeCompare(b.name, undefined, {
      numeric: true,
      sensitivity: 'base',
    })
  );

  return tags;
}

/**
 * Get a single public bookmark
 */
export async function getPublicBookmark(baseUrl: string, username: string, id: string): Promise<Bookmark | null> {
  const response = await fetch(`${baseUrl}/profile/${encodeURIComponent(username)}/bookmarks/${encodeURIComponent(id)}`, {
    method: 'GET',
    headers: {
      'Accept': 'application/json',
    },
  });

  if (!response.ok) {
    if (response.status === 404) {
      return null;
    }
    const errorText = await response.text();
    throw new ApiError(errorText || `HTTP error! status: ${response.status}`, response.status);
  }

  const bookmark: ApiBookmarkProfile = await response.json();

  const transformFileObject = (file: { '@iri'?: string; contentUrl: string | null; size: number; mime: string } | null): FileObject | null => {
    if (!file) return null;
    
    // Use @iri if available, otherwise fallback to contentUrl
    const iri = file['@iri'] || file.contentUrl;
    if (!iri) return null;
    
    try {
      const path = new URL(iri).pathname;
      const id = path.split('/').pop() || '';
      return {
        '@iri': iri,
        id,
        contentUrl: file.contentUrl,
        size: file.size,
        mime: file.mime,
      };
    } catch {
      // Fallback: extract ID from IRI path if it's not a valid URL
      const path = iri.includes('/') ? iri.split('/').pop() || '' : iri;
      return {
        '@iri': iri,
        id: path,
        contentUrl: file.contentUrl,
        size: file.size,
        mime: file.mime,
      };
    }
  };

  // Transform tags within the bookmark
  // Public bookmarks use ApiTagProfile which has simpler structure
  const { tags: rawTags, ...bookmarkWithoutTags } = bookmark;
  const transformedTags = Array.isArray(rawTags)
    ? rawTags.map((tag: ApiTagProfile) => ({
        '@iri': tag['@iri'],
        name: tag.name,
        slug: tag.slug,
        isPublic: true, // Public tags are always public
        pinned: false, // Public tags don't have pinned info
        layout: 'default', // Public tags don't have layout info
        icon: null, // Public tags don't have icon info
      }))
    : [];

  return {
    ...bookmarkWithoutTags,
    tags: transformedTags,
    // Public bookmarks don't have domain or isPublic fields, set defaults
    domain: bookmark.domain || new URL(bookmark.url).hostname,
    isPublic: true,
    // Owner is simplified in public bookmarks
    owner: bookmark.owner || { '@iri': bookmark['@iri'], username, isPublic: true },
    // Transform mainImage and archive to include id field
    mainImage: transformFileObject(bookmark.mainImage || null),
    archive: transformFileObject(bookmark.archive || null),
  };
}

