import { Link, useParams, useSearchParams } from 'react-router-dom';
import { updateTagParams } from '../../utils/tags';
import type { Bookmark } from '../../types';

interface BookmarkImageProps {
  bookmark: Bookmark;
  imageRef?: (el: HTMLImageElement | null) => void;
}

export const BookmarkImage = ({ bookmark, imageRef }: BookmarkImageProps) => {
  const { profileIdentifier: urlProfileIdentifier } = useParams<{ profileIdentifier?: string }>();
  const [searchParams] = useSearchParams();
  const imageUrl = bookmark.mainImage?.contentUrl;

  if (!imageUrl) {
    return null;
  }

  // Extract profileIdentifier from bookmark account if not in URL (for timeline)
  let profileIdentifier = urlProfileIdentifier;
  if (!profileIdentifier) {
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
  const basePath = profileIdentifier
    ? `/social/${profileIdentifier}/bookmarks/${bookmark.id}`
    : `/me/bookmarks/${bookmark.id}`;

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

