/**
 * Utility functions for URL resolution and other common operations
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
    } catch {
        return url;
    }
}

