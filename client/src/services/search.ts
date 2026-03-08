/**
 * Search service using Fuse.js for fuzzy search
 */

import Fuse from 'fuse.js';
import type { Bookmark } from '../types';
import type { IFuseOptions } from 'fuse.js';

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
  threshold: 0.2, // Moderate fuzziness (0.0 = exact match, 1.0 = match anything)
  includeScore: true,
  ignoreDiacritics: true,
  minMatchCharLength: 2,
  ignoreLocation: true,
};

/**
 * Search through bookmarks using Fuse.js
 * @param query Search query string
 * @param bookmarks Array of bookmarks to search through
 * @param selectedTagSlugs Optional array of tag slugs to filter by (mandatory matching - all tags must be present)
 * @returns Array of matching bookmarks sorted by relevance
 */
export function searchBookmarks(query: string, bookmarks: Bookmark[], selectedTagSlugs: string[] = []): Bookmark[] {
  if (!query.trim() || bookmarks.length === 0) {
    return [];
  }

  // Filter bookmarks by selected tags if any are selected (mandatory matching)
  let filteredBookmarks = bookmarks;
  if (selectedTagSlugs.length > 0) {
    filteredBookmarks = bookmarks.filter((bookmark) => {
      const bookmarkTagSlugs = bookmark.tags.map((tag) => tag.slug);
      // Check if bookmark has all selected tags (mandatory matching)
      return selectedTagSlugs.every((slug) => bookmarkTagSlugs.includes(slug));
    });
  }

  if (filteredBookmarks.length === 0) {
    return [];
  }

  const fuse = new Fuse(filteredBookmarks, fuseOptions);
  const results = fuse.search(query);

  // Return bookmarks sorted by relevance (lower score = better match)
  return results.map((result) => result.item);
}

