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
    // Prefer environment variable, fallback to window.location.origin
    const envBaseUrl = (import.meta as any)?.env?.VITE_API_BASE_URL;
    const baseUrl = envBaseUrl || window.location.origin;

    const urlObj = url.startsWith('http')
      ? new URL(url)
      : new URL(url, baseUrl);
    return urlObj.searchParams.get('after') || undefined;
  } catch {
    return undefined;
  }
}
