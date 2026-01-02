import baseX from 'base-x';

export interface EmbedResult {
  type: 'youtube' | 'vimeo' | 'ted' | 'peertube';
  embedUrl: string;
  thumbnailUrl?: string;
}

// Standard BASE58 alphabet (excludes 0, O, I, l to avoid confusion)
const base58 = baseX('123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz');

function extractYouTubeId(url: string): string | null {
  // youtube.com format: https://www.youtube.com/watch?v=VIDEO_ID
  if (/youtube\./i.test(url)) {
    try {
      const urlObj = new URL(url);
      const videoId = urlObj.searchParams.get('v');
      return videoId || null;
    } catch {
      return null;
    }
  }

  // youtu.be format: https://youtu.be/VIDEO_ID
  if (/youtu\.be/i.test(url)) {
    try {
      const urlObj = new URL(url);
      const path = urlObj.pathname.trim();
      return path.startsWith('/') ? path.slice(1) : path;
    } catch {
      return null;
    }
  }

  return null;
}

function extractVimeoId(url: string): string | null {
  const match = url.match(/vimeo\.com\/(\d+)/i);
  return match ? match[1] : null;
}

function convertPeerTubeUrl(url: string): string | null {
  // Match /w/ followed by alphanumeric characters (typically 22 chars for base58 encoded UUID)
  const match = url.match(/\/w\/([\w-]+)/i);
  if (!match) return null;

  const videoId = match[1];

  // Only try conversion if it's exactly 22 characters (base58 encoded UUID format)
  if (videoId.length === 22) {
    try {
      const buffer = base58.decode(videoId);
      const uuidCondensed = Array.from(buffer)
        .map((b) => b.toString(16).padStart(2, '0'))
        .join('');

      const uuidMatch = uuidCondensed.match(
        /^([0-9a-f]{8})([0-9a-f]{4})([0-9a-f]{4})([0-9a-f]{4})([0-9a-f]{12})$/
      );

      if (uuidMatch) {
        const uuid = `${uuidMatch[1]}-${uuidMatch[2]}-${uuidMatch[3]}-${uuidMatch[4]}-${uuidMatch[5]}`;
        return url.replace(/\/w\/[\w-]+/i, `/videos/watch/${uuid}`);
      }
    } catch {
      // Conversion failed, return null
    }
  }

  return null;
}

export function findThumbnail(url: string): string | null {
  if (/youtube\./i.test(url) || /youtu\.be/i.test(url)) {
    const videoId = extractYouTubeId(url);
    if (videoId) {
      return `https://i.ytimg.com/vi_webp/${videoId}/sddefault.webp`;
    }
  }

  return null;
}

export function findEmbed(url: string): EmbedResult | null {
  if (!url) return null;

  // YouTube
  if (/youtube\./i.test(url) || /youtu\.be/i.test(url)) {
    const videoId = extractYouTubeId(url);
    if (videoId) {
      let thumbnailUrl = findThumbnail(url)!;
      return {
        type: 'youtube',
        embedUrl: `https://www.youtube-nocookie.com/embed/${videoId}?autoplay=1&color=white&rel=0`,
        thumbnailUrl,
      };
    }
  }

  // TED Talks
  if (/ted\.com\/talks\/.+/i.test(url)) {
    const embedUrl = url.replace(
      /https?:\/\/(www\.)?ted\.com\/talks\//i,
      'https://embed.ted.com/talks/'
    ) + '?autoplay=1';
    return {
      type: 'ted',
      embedUrl,
    };
  }

  // Vimeo
  if (/vimeo\.com\/\d+/i.test(url)) {
    const videoId = extractVimeoId(url);
    if (videoId) {
      return {
        type: 'vimeo',
        embedUrl: `https://player.vimeo.com/video/${videoId}?autoplay=1`,
      };
    }
  }

  // PeerTube - handle short URL format (/w/VIDEO_ID)
  const shortUrlMatch = url.match(/\/w\/([\w-]+)/i);
  if (shortUrlMatch) {
    // Try to convert to UUID format first
    const converted = convertPeerTubeUrl(url);
    if (converted) {
      // Successfully converted to UUID format
      const embedUrl = converted.replace('/videos/watch/', '/videos/embed/') + '?autoplay=true';
      return {
        type: 'peertube',
        embedUrl,
      };
    } else {
      // Conversion failed, but it's still a PeerTube URL
      // Use the short URL format directly (some instances support this)
      // Extract the base URL and video ID
      try {
        const urlObj = new URL(url);
        const baseUrl = `${urlObj.protocol}//${urlObj.host}`;
        const videoId = shortUrlMatch[1];
        // Try embed format with short ID (some PeerTube instances support this)
        const embedUrl = `${baseUrl}/videos/embed/${videoId}?autoplay=true`;
        return {
          type: 'peertube',
          embedUrl,
        };
      } catch {
        // If URL parsing fails, fall through to next check
      }
    }
  }

  // PeerTube - full UUID format (/videos/watch/UUID)
  if (/\/videos\/watch\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i.test(url)) {
    const embedUrl = url.replace('/videos/watch/', '/videos/embed/') + '?autoplay=true';
    return {
      type: 'peertube',
      embedUrl,
    };
  }

  return null;
}

