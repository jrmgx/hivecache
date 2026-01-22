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
    // Handle relative URLs (e.g., /users/me/bookmarks?after=...)
    // Try to get base URL from localStorage first, then environment variable, then fallback to window.location.origin
    let baseUrl: string | undefined;

    // Try localStorage (for client) - synchronous access
    if (typeof localStorage !== 'undefined') {
      const storedBaseUrl = localStorage.getItem('api_base_url');
      if (storedBaseUrl) {
        baseUrl = storedBaseUrl;
      }
    }

    // Fallback to window.location.origin
    if (!baseUrl && typeof window !== 'undefined') {
      baseUrl = window.location.origin;
    }

    if (!baseUrl) {
      return undefined;
    }

    const urlObj = url.startsWith('http')
      ? new URL(url)
      : new URL(url, baseUrl);
    return urlObj.searchParams.get('after') || undefined;
  } catch {
    return undefined;
  }
}
