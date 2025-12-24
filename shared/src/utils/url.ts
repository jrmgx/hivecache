/**
 * URL utility functions
 */

/**
 * Resolves a relative URL to an absolute URL
 * @param url The URL to resolve (can be relative or absolute)
 * @param base The base URL to resolve against
 * @returns The resolved absolute URL, or the original URL if resolution fails
 */
export function resolveURL(url: string, base: string): string {
  try {
    return new URL(url, base).href;
  } catch (e) {
    return url;
  }
}

/**
 * Extract cursor from pagination URL
 * Extracts the 'after' query parameter from pagination URLs
 *
 * @param url The pagination URL (can be absolute or relative)
 * @returns The cursor value (after parameter) or undefined if not found
 */
export function getCursorFromUrl(url: string | null): string | undefined {
  if (!url) return undefined;
  try {
    // Handle relative URLs (e.g., /api/users/me/bookmarks?after=...)
    const urlObj = url.startsWith('http')
      ? new URL(url)
      : new URL(url, typeof window !== 'undefined' ? window.location.origin : 'https://bookmarkhive.test');
    return urlObj.searchParams.get('after') || undefined;
  } catch {
    return undefined;
  }
}

