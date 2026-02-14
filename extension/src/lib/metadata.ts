/**
 * Page metadata extraction functions
 */

import { PageData } from '../types';
import { findThumbnail } from '@shared';

/**
 * Extracts image URL from the current page
 * Tries findThumbnail, then og:image, then twitter:image, then favicon
 * @returns Image URL or null if none found
 */
function extractImageUrl(): string | null {
    // Try findThumbnail first (for YouTube, etc.)
    const thumbnailUrl = findThumbnail(window.location.href);
    if (thumbnailUrl) {
        return thumbnailUrl;
    }

    // Try og:image
    const ogImage = document.querySelector('meta[property="og:image"]') as HTMLMetaElement | null;
    if (ogImage?.content) {
        try {
            // Convert to absolute URL (handles both relative and absolute URLs)
            // new URL() will use absolute URLs as-is, or resolve relative URLs against the base
            return new URL(ogImage.content, window.location.href).href;
        } catch {
            // If URL parsing fails, return original content (might be invalid but worth trying)
            return ogImage.content;
        }
    }

    // Try twitter:image
    const twitterImage = document.querySelector('meta[name="twitter:image"]') as HTMLMetaElement | null;
    if (twitterImage?.content) {
        try {
            return new URL(twitterImage.content, window.location.href).href;
        } catch {
            return twitterImage.content;
        }
    }

    // Try favicon
    // Note: faviconLink.href is already resolved by the browser (always absolute),
    // but we normalize it with new URL() for consistency
    const faviconLink = document.querySelector('link[rel="icon"]') as HTMLLinkElement | null ||
        document.querySelector('link[rel="shortcut icon"]') as HTMLLinkElement | null ||
        document.querySelector('link[rel="apple-touch-icon"]') as HTMLLinkElement | null;

    if (faviconLink?.href) {
        try {
            return new URL(faviconLink.href, window.location.href).href;
        } catch {
            // Invalid URL, continue to default favicon
        }
    }

    // Fallback to default favicon location
    try {
        return new URL('/favicon.ico', window.location.origin).href;
    } catch {
        return null;
    }
}

/**
 * Validates image URL by checking HTTP status
 * @param imageUrl The image URL to validate
 * @returns Validated image URL or null if validation fails
 */
async function validateImageUrl(imageUrl: string | null): Promise<string | null> {
    if (!imageUrl) {
        return null;
    }

    try {
        const response = await fetch(imageUrl, { method: 'HEAD' });
        if (response.ok) {
            return imageUrl;
        }
        return null;
    } catch {
        // If fetch fails (e.g., CORS issue), try loading as image to validate
        try {
            await new Promise<void>((resolve, reject) => {
                const img = new Image();
                img.onload = () => resolve();
                img.onerror = () => reject(new Error('Image failed to load'));
                img.src = imageUrl;
            });
            return imageUrl;
        } catch {
            return null;
        }
    }
}

/**
 * Extracts metadata from the current page
 * @returns Page metadata object containing title, URL, description, and image (favicon used as fallback if no image found)
 */
export async function extractPageMetadata(): Promise<PageData> {
    const pageData: PageData = {
        title: document.title || '',
        url: window.location.href,
        description: null,
        image: null
    };

    // Get meta description
    const metaDescription = document.querySelector('meta[name="description"]') as HTMLMetaElement | null;
    if (metaDescription) {
        pageData.description = metaDescription.content || null;
    } else {
        // Try og:description as fallback
        const ogDescription = document.querySelector('meta[property="og:description"]') as HTMLMetaElement | null;
        if (ogDescription) {
            pageData.description = ogDescription.content || null;
        }
    }

    // Get and validate image URL
    const imageUrl = extractImageUrl();
    pageData.image = await validateImageUrl(imageUrl);

    // This will tentatively get youtube video duration
    const microformatScript = document.querySelector('#microformat player-microformat-renderer script') as HTMLScriptElement | null;
    if (microformatScript?.textContent) {
        try {
            const json = JSON.parse(microformatScript.textContent);
            if (json && typeof json.duration === 'string') {
                const match = /^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/.exec(json.duration);
                if (match) {
                    const totalSeconds = (parseInt(match[1] || '0', 10) * 3600) + (parseInt(match[2] || '0', 10) * 60) + parseInt(match[3] || '0', 10);
                    const hh = Math.floor(totalSeconds / 3600);
                    const mm = Math.floor((totalSeconds % 3600) / 60);
                    const ss = totalSeconds % 60;
                    console.log(hh);
                    const formatted = hh > 0
                        ? `${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}:${String(ss).padStart(2, '0')}`
                        : `${String(mm).padStart(2, '0')}:${String(ss).padStart(2, '0')}`;
                    pageData.title = `[${formatted}] ${pageData.title}`;
                }
            }
        } catch {
            // Ignore parsing errors
        }
    }

    return pageData;
}
