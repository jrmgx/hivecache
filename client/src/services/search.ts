/**
 * Search service using Fuse.js for fuzzy search
 */

import Fuse from 'fuse.js';
import type { Bookmark } from '../types';
import type { IFuseOptions } from 'fuse.js';

export const SEARCH_RESULT_LIMIT = 120;

/**
 * Search configuration for Fuse.js
 */
const fuseOptions: IFuseOptions<Bookmark> = {
  keys: [
    { name: 'title', weight: 1.0 },
    { name: 'url', weight: 0.5 },
    { name: 'domain', weight: 0.25 },
    { name: 'tags.name', weight: 0.75 },
  ],
  threshold: 0.2,
  includeScore: false,
  ignoreDiacritics: true,
  minMatchCharLength: 2,
  ignoreLocation: true,
};

let fuseCache: { list: Bookmark[]; fuse: Fuse<Bookmark> } | null = null;

function fuseForList(list: Bookmark[]): Fuse<Bookmark> {
  if (fuseCache !== null && fuseCache.list === list) {
    return fuseCache.fuse;
  }
  const fuse = new Fuse(list, fuseOptions);
  fuseCache = { list, fuse };
  return fuse;
}

/**
 * Search through bookmarks using Fuse.js
 * @param query Search query string
 * @param bookmarks Array of bookmarks to search through
 * @param selectedTagSlugs Optional array of tag slugs to filter by (mandatory matching - all tags must be present)
 * @returns Array of matching bookmarks sorted by relevance (capped at SEARCH_RESULT_LIMIT)
 */
export function searchBookmarks(query: string, bookmarks: Bookmark[], selectedTagSlugs: string[] = []): Bookmark[] {
  if (!query.trim() || bookmarks.length === 0) {
    return [];
  }

  let filteredBookmarks = bookmarks;
  if (selectedTagSlugs.length > 0) {
    filteredBookmarks = bookmarks.filter((bookmark) => {
      const bookmarkTagSlugs = bookmark.tags.map((tag) => tag.slug);
      return selectedTagSlugs.every((slug) => bookmarkTagSlugs.includes(slug));
    });
  }

  if (filteredBookmarks.length === 0) {
    return [];
  }

  const fuse =
    filteredBookmarks === bookmarks ? fuseForList(filteredBookmarks) : new Fuse(filteredBookmarks, fuseOptions);
  const results = fuse.search(query, { limit: SEARCH_RESULT_LIMIT });

  return results.map((result) => result.item);
}
