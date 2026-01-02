import type { Bookmark } from '../../types';

interface BookmarkImageProps {
  bookmark: Bookmark;
}

export const BookmarkImage = ({ bookmark }: BookmarkImageProps) => {
  const imageUrl = bookmark.mainImage?.contentUrl;

  if (!imageUrl) {
    return null;
  }

  return (
    // <div className="col-12 col-md-6 col-xl-6 col-xxl-3 my-2">
      <a id={`bookmark-${bookmark.id}`} target="_blank" href={bookmark.url} rel="noopener noreferrer" style={{ position: 'relative', display: 'block' }}>
        {bookmark.isPublic && (
          <span className="bookmark-public-indicator">✦</span>
        )}
        <img className="w-100" src={imageUrl} alt={bookmark.title} />
      </a>
    // </div>
  );
};

