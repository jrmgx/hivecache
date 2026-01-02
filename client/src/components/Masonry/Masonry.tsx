import { useState, useRef, useEffect } from 'react';
import type { Bookmark } from '../../types';

interface MasonryProps {
  bookmarks: Bookmark[];
}

const getNumColumns = () => {
  const width = window.innerWidth;
  if (width < 768) return 1;
  if (width < 1200) return 2;
  if (width < 1400) return 3;
  return 4;
};

const getColClass = (numColumns: number) => {
  if (numColumns === 1) return 'col-12';
  if (numColumns === 2) return 'col-6';
  if (numColumns === 3) return 'col-4';
  return 'col-3'; // 4 columns
};

export const Masonry = ({ bookmarks }: MasonryProps) => {
  const numColumns = getNumColumns();
  const colClass = getColClass(numColumns);
  const [imageHeights, setImageHeights] = useState<Map<string, number>>(new Map());
  const imageRefs = useRef<Map<string, HTMLImageElement>>(new Map());

  // Calculate which column each bookmark should go to based on current heights
  const getBookmarkColumns = (): Map<string, number> => {
    const assignments = new Map<string, number>();
    const currentHeights = Array(numColumns).fill(0);

    bookmarks.forEach((bookmark) => {
      // Find column with smallest height
      const minHeight = Math.min(...currentHeights);
      const colIndex = currentHeights.indexOf(minHeight);
      assignments.set(bookmark.id, colIndex);

      // Update height for this column (use measured height or estimate)
      const height = imageHeights.get(bookmark.id) || 300; // Estimate 300px if not loaded yet
      currentHeights[colIndex] += height + 16; // 16px for my-2 margin
    });

    return assignments;
  };

  const bookmarkColumns = getBookmarkColumns();

  // Update image heights when they load
  useEffect(() => {
    const updateHeight = (bookmarkId: string, img: HTMLImageElement) => {
      if (img.offsetHeight > 0) {
        setImageHeights((prev) => {
          const next = new Map(prev);
          next.set(bookmarkId, img.offsetHeight);
          return next;
        });
      }
    };

    bookmarks.forEach((bookmark) => {
      const img = imageRefs.current.get(bookmark.id);
      if (img) {
        if (img.complete && img.offsetHeight > 0) {
          updateHeight(bookmark.id, img);
        } else {
          const handleLoad = () => {
            updateHeight(bookmark.id, img);
            img.removeEventListener('load', handleLoad);
            img.removeEventListener('error', handleLoad);
          };
          img.addEventListener('load', handleLoad);
          img.addEventListener('error', handleLoad);
        }
      }
    });
  }, [bookmarks]);

  return (
    <div className="row gx-3">
      {Array.from({ length: numColumns }, (_, colIndex) => (
        <div key={colIndex} className={colClass}>
          {bookmarks
            .filter((bookmark) => bookmarkColumns.get(bookmark.id) === colIndex)
            .map((bookmark) => (
              <div key={bookmark.id} className="my-2">
                <a
                  id={`bookmark-${bookmark.id}`}
                  target="_blank"
                  href={bookmark.url}
                  rel="noopener noreferrer"
                  style={{ position: 'relative', display: 'block' }}
                >
                  {bookmark.isPublic && (
                    <span className="bookmark-public-indicator">✦</span>
                  )}
                  <img
                    ref={(el) => {
                      if (el) {
                        imageRefs.current.set(bookmark.id, el);
                      } else {
                        imageRefs.current.delete(bookmark.id);
                      }
                    }}
                    className="w-100"
                    src={bookmark.mainImage?.contentUrl ?? undefined}
                    alt={bookmark.title}
                  />
                </a>
              </div>
            ))}
        </div>
      ))}
    </div>
  );
};

