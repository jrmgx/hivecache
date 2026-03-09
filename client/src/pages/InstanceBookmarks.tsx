import { useState, useEffect, useRef, useCallback } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { Bookmark } from '../components/Bookmark/Bookmark';
import { BookmarkListing } from '../components/Bookmark/BookmarkListing';
import { Masonry } from '../components/Masonry/Masonry';
import { ErrorAlert } from '../components/ErrorAlert/ErrorAlert';
import { getInstanceBookmarks, getCursorFromUrl, ApiError } from '../services/api';
import { toggleTag, updateTagParams } from '../utils/tags';
import type { Bookmark as BookmarkType, BookmarkWithAccount } from '../types';
import { LAYOUT_DEFAULT, LAYOUT_IMAGE, LAYOUT_LISTING } from '../types';

export const InstanceBookmarks = () => {
  const navigate = useNavigate();
  const { type } = useParams<{ type: string }>();
  const [searchParams, setSearchParams] = useSearchParams();
  const instanceType = (type === 'this' || type === 'other') ? type : 'this';
  const tagQueryString = searchParams.get('tags') || '';
  const selectedTagSlugs = tagQueryString ? tagQueryString.split(',').filter(Boolean) : [];

  const [bookmarks, setBookmarks] = useState<BookmarkType[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [errorStatus, setErrorStatus] = useState<number | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [nextPage, setNextPage] = useState<string | null>(null);
  const observerTarget = useRef<HTMLDivElement>(null);

  const loadData = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    setErrorStatus(null);
    setBookmarks([]);
    setNextPage(null);
    try {
      const bookmarksResponse = await getInstanceBookmarks(instanceType, undefined, tagQueryString || undefined);
      setBookmarks(bookmarksResponse.collection);
      setNextPage(bookmarksResponse.nextPage);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to load timeline';
      const status = err instanceof ApiError ? err.status : null;
      setError(message);
      setErrorStatus(status);
      setBookmarks([]);
    } finally {
      setIsLoading(false);
    }
  }, [instanceType, tagQueryString]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const loadMoreBookmarks = useCallback(async () => {
    if (!nextPage || isLoadingMore) return;

    setIsLoadingMore(true);
    try {
      const cursor = getCursorFromUrl(nextPage);
      const response = await getInstanceBookmarks(instanceType, cursor, tagQueryString || undefined);
      setBookmarks((prev) => [...prev, ...response.collection]);
      setNextPage(response.nextPage);
    } catch (err: unknown) {
      console.error('Failed to load more bookmarks:', err);
    } finally {
      setIsLoadingMore(false);
    }
  }, [nextPage, isLoadingMore, instanceType, tagQueryString]);

  const handleTagToggle = useCallback((slug: string) => {
    const newSelectedSlugs = toggleTag(slug, selectedTagSlugs);
    const newParams = updateTagParams(newSelectedSlugs, searchParams);
    setSearchParams(newParams);
  }, [selectedTagSlugs, searchParams, setSearchParams]);

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

  const handleShow = (id: string, bookmark: BookmarkType) => {
    // Extract username@instance from bookmark account
    // Timeline bookmarks have account field with username and instance
    const account = (bookmark as BookmarkWithAccount).account;
    let profileIdentifier = '';

    if (account) {
      const username = account.username;
      const instance = account.instance || (bookmark as BookmarkWithAccount).instance;

      if (username && instance) {
        profileIdentifier = `${username}@${instance}`;
      } else if (username) {
        // Fallback: use username only if no instance
        profileIdentifier = username;
      }
    }

    if (profileIdentifier) {
      navigate(`/social/${profileIdentifier}/bookmarks/${id}`);
    } else {
      // Fallback to me version if we can't determine profile
      navigate(`/me/bookmarks/${id}`);
    }
  };

  const layout: string = LAYOUT_DEFAULT;
  const isLayoutImage = layout === LAYOUT_IMAGE;
  const isLayoutListing = layout === LAYOUT_LISTING;

  return (
    <>
      <ErrorAlert error={error} statusCode={errorStatus} />

      {isLoading ? (
        <div className="text-center pt-5">
          <div className="spinner-border" role="status">
            <span className="visually-hidden">Loading...</span>
          </div>
        </div>
      ) : (
        <>
          {/* Bookmark List */}
          {bookmarks.length > 0 ? (
            <>
              {isLayoutImage ? (
                <Masonry bookmarks={bookmarks} selectedTagSlugs={selectedTagSlugs} onTagToggle={handleTagToggle} />
              ) : isLayoutListing ? (
                <div>
                  {bookmarks.map((bookmark) => (
                    <BookmarkListing
                      key={bookmark.id}
                      bookmark={bookmark}
                      selectedTagSlugs={selectedTagSlugs}
                      onTagToggle={handleTagToggle}
                      onShow={() => handleShow(bookmark.id, bookmark)}
                      hideAddTagButton={true}
                      isProfileMode={true}
                    />
                  ))}
                </div>
              ) : (
                <div className="row gx-3">
                  {bookmarks.map((bookmark) => (
                    <Bookmark
                      key={bookmark.id}
                      bookmark={bookmark}
                      layout={layout}
                      selectedTagSlugs={selectedTagSlugs}
                      onTagToggle={handleTagToggle}
                      onShow={() => handleShow(bookmark.id, bookmark)}
                      hideAddTagButton={true}
                      isProfileMode={true}
                    />
                  ))}
                </div>
              )}
              {/* Pagination */}
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
                <strong>No bookmarks</strong>
              </div>
            </div>
          )}
        </>
      )}

      <div className="mt-1">&nbsp;</div>
    </>
  );
};
