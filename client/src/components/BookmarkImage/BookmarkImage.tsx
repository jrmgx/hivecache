import { Link, useParams, useSearchParams, useLocation } from 'react-router-dom';
import { updateTagParams } from '../../utils/tags';
import type { Bookmark } from '../../types';

interface BookmarkImageProps {
  bookmark: Bookmark;
  imageRef?: (el: HTMLImageElement | null) => void;
}

export const BookmarkImage = ({ bookmark, imageRef }: BookmarkImageProps) => {
  const { profileIdentifier: urlProfileIdentifier } = useParams<{ profileIdentifier?: string }>();
  const [searchParams] = useSearchParams();
  const location = useLocation();
  const imageUrl = bookmark.mainImage?.contentUrl;

  if (!imageUrl) {
    return null;
  }

  // Check if we're on a /me route or related routes that should use /me/bookmarks
  const isMeRoute = location.pathname.startsWith('/me') ||
    location.pathname === '/social/timeline' ||
    location.pathname.startsWith('/social/tag/') ||
    location.pathname.startsWith('/social/instance/');

  // Extract profileIdentifier from bookmark account if not in URL (for timeline)
  // But only if we're NOT on a /me route
  let profileIdentifier = urlProfileIdentifier;
  if (!profileIdentifier && !isMeRoute) {
    const account = (bookmark as any).account;
    if (account) {
      const username = account.username;
      const instance = account.instance || (bookmark as any).instance;
      if (username && instance) {
        profileIdentifier = `${username}@${instance}`;
      } else if (username) {
        profileIdentifier = username;
      }
    }
  }

  // Get current selected tags from URL
  const tagQueryString = searchParams.get('tags') || '';
  const selectedTagSlugs = tagQueryString ? tagQueryString.split(',').filter(Boolean) : [];

  // Build URL with tags preserved
  // Always use /me/bookmarks if on /me route, otherwise use profileIdentifier if available
  const basePath = isMeRoute || !profileIdentifier
    ? `/me/bookmarks/${bookmark.id}`
    : `/social/${profileIdentifier}/bookmarks/${bookmark.id}`;

  const params = updateTagParams(selectedTagSlugs, new URLSearchParams());
  const to = `${basePath}${params.toString() ? `?${params.toString()}` : ''}`;

  return (
      <Link
        id={`bookmark-${bookmark.id}`}
        to={to}
        style={{ position: 'relative', display: 'block' }}
      >
        {bookmark.isPublic && !profileIdentifier && (
          <span className="bookmark-public-indicator">✦</span>
        )}
        <img
          ref={imageRef}
          className="w-100"
          src={imageUrl}
          alt={bookmark.title}
        />
      </Link>
  );
};

