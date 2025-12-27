import type { Bookmark } from '../../types';
import { resolveContentUrl } from '../../utils/image';

interface BookmarkImageProps {
  bookmark: Bookmark;
}

export const BookmarkImage = ({ bookmark }: BookmarkImageProps) => {
  const imageUrl = resolveContentUrl(bookmark.mainImage?.contentUrl);

  if (!imageUrl) {
    return null;
  }

  return (
    // <div className="col-12 col-md-6 col-xl-6 col-xxl-3 my-2">
      <a id={`bookmark-${bookmark.id}`} target="_blank" href={bookmark.url} rel="noopener noreferrer">
        <img className="w-100" src={imageUrl} alt={bookmark.title} />
      </a>
    // </div>
  );
};

