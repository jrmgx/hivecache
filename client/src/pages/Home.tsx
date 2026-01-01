import { useState, useEffect, useRef, useCallback } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { Tag } from '../components/Tag/Tag';
import { Bookmark } from '../components/Bookmark/Bookmark';
import { Masonry } from '../components/Masonry/Masonry';
import { ErrorAlert } from '../components/ErrorAlert/ErrorAlert';
import { getBookmarks, getTags, getCursorFromUrl, ApiError } from '../services/api';
import { toggleTag, updateTagParams } from '../utils/tags';
import type { Bookmark as BookmarkType, Tag as TagType } from '../types';
import { LAYOUT_DEFAULT, LAYOUT_IMAGE } from '../types';

const layoutForTags = (selectedTags: TagType[]): string => {
  for (const tag of selectedTags) {
    if (tag.layout !== LAYOUT_DEFAULT) {
      return tag.layout;
    }
  }
  return LAYOUT_DEFAULT;
};

export const Home = () => {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const [bookmarks, setBookmarks] = useState<BookmarkType[]>([]);
  const [tags, setTags] = useState<TagType[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [errorStatus, setErrorStatus] = useState<number | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [nextPage, setNextPage] = useState<string | null>(null);
  const observerTarget = useRef<HTMLDivElement>(null);
  const tagQueryString = searchParams.get('tags') || '';
  const selectedTagSlugs = tagQueryString ? tagQueryString.split(',').filter(Boolean) : [];
  const selectedTags = tags.filter((tag) => selectedTagSlugs.includes(tag.slug));
  const layout = layoutForTags(selectedTags);
  const isLayoutImage = layout === LAYOUT_IMAGE;

  // Load initial data
  const loadData = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    setErrorStatus(null);
    setBookmarks([]);
    setNextPage(null);
    try {
      const [bookmarksResponse, tagsData] = await Promise.all([
        getBookmarks(tagQueryString),
        getTags(),
      ]);
      setBookmarks(bookmarksResponse.collection);
      setNextPage(bookmarksResponse.nextPage);
      setTags(tagsData);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to load data';
      const status = err instanceof ApiError ? err.status : null;
      setError(message);
      setErrorStatus(status);
      setBookmarks([]);
      setTags([]);
    } finally {
      setIsLoading(false);
    }
  }, [tagQueryString]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  // Listen for bookmarks updated event to refresh the list
  useEffect(() => {
    const handleBookmarksUpdated = () => {
      loadData();
    };

    window.addEventListener('bookmarksUpdated', handleBookmarksUpdated);
    return () => {
      window.removeEventListener('bookmarksUpdated', handleBookmarksUpdated);
    };
  }, [loadData]);

  const loadMoreBookmarks = useCallback(async () => {
    if (!nextPage || isLoadingMore) return;

    setIsLoadingMore(true);
    try {
      const cursor = getCursorFromUrl(nextPage);
      const response = await getBookmarks(tagQueryString, cursor);
      setBookmarks((prev) => [...prev, ...response.collection]);
      setNextPage(response.nextPage);
    } catch (err: unknown) {
      console.error('Failed to load more bookmarks:', err);
    } finally {
      setIsLoadingMore(false);
    }
  }, [nextPage, isLoadingMore, tagQueryString]);

  // Intersection Observer for infinite scroll
  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting && nextPage && !isLoadingMore) {
          loadMoreBookmarks();
        }
      },
      { threshold: 0.1 }
    );

    const currentTarget = observerTarget.current;
    if (currentTarget) {
      observer.observe(currentTarget);
    }

    return () => {
      if (currentTarget) {
        observer.unobserve(currentTarget);
      }
    };
  }, [nextPage, isLoadingMore, loadMoreBookmarks]);

  const handleTagToggle = (slug: string) => {
    const newSelectedSlugs = toggleTag(slug, selectedTagSlugs);
    const newParams = updateTagParams(newSelectedSlugs, searchParams);
    setSearchParams(newParams);
  };

  const handleShow = (id: string) => {
    const params = updateTagParams(selectedTagSlugs, new URLSearchParams());
    navigate(`/bookmarks/${id}${params.toString() ? `?${params.toString()}` : ''}`);
  };

  return (
    <>
      <ErrorAlert error={error} statusCode={errorStatus} />

      {isLoading ? (
        <div className="text-center pt-5">
          <div className="spinner-border" role="status">
            <span className="visually-hidden">Loading...</span>
          </div>
        </div>
      ) : bookmarks.length > 0 ? (
        <>
          {isLayoutImage ? (
            <Masonry bookmarks={bookmarks} />
          ) : (
            <div className="row gx-3">
              {bookmarks.map((bookmark) => (
                <Bookmark
                  key={bookmark.id}
                  bookmark={bookmark}
                  layout={layout}
                  selectedTagSlugs={selectedTagSlugs}
                  onTagToggle={handleTagToggle}
                  onShow={handleShow}
                />
              ))}
            </div>
          )}
          {nextPage && (
            <div ref={observerTarget} className="text-center py-3">
              {isLoadingMore && (
                <div className="spinner-border" role="status">
                  <span className="visually-hidden">Loading more...</span>
                </div>
              )}
            </div>
          )}
        </>
      ) : (
        <div className="row">
          <div className="col-12 text-center pt-5">
            <strong>No bookmark matching!</strong>
          </div>
          <div className="col-12 text-center">
            <div className="d-flex my-2 flex-wrap justify-content-center gap-2 mx-auto" style={{ maxWidth: 'fit-content' }}>
              {tags
                .filter((tag) => selectedTagSlugs.includes(tag.slug))
                .map((tag) => (
                  <Tag
                    key={tag.slug}
                    tag={tag}
                    selectedTagSlugs={selectedTagSlugs}
                    onToggle={handleTagToggle}
                    className="flex-grow-0"
                  />
                ))}
            </div>
          </div>
        </div>
      )}

      <div className="mt-1">&nbsp;</div>
    </>
  );
};

