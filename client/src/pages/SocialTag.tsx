import { useState, useEffect, useRef, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Bookmark } from '../components/Bookmark/Bookmark';
import { BookmarkListing } from '../components/Bookmark/BookmarkListing';
import { Masonry } from '../components/Masonry/Masonry';
import { ErrorAlert } from '../components/ErrorAlert/ErrorAlert';
import { getSocialTagBookmarks, getCursorFromUrl, ApiError } from '../services/api';
import type { Bookmark as BookmarkType } from '../types';
import { LAYOUT_DEFAULT, LAYOUT_IMAGE, LAYOUT_LISTING } from '../types';

export const SocialTag = () => {
  const navigate = useNavigate();
  const { slug } = useParams<{ slug: string }>();
  const [bookmarks, setBookmarks] = useState<BookmarkType[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [errorStatus, setErrorStatus] = useState<number | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [nextPage, setNextPage] = useState<string | null>(null);
  const observerTarget = useRef<HTMLDivElement>(null);

  // Load initial data
  const loadData = useCallback(async () => {
    if (!slug) return;

    setIsLoading(true);
    setError(null);
    setErrorStatus(null);
    setBookmarks([]);
    setNextPage(null);
    try {
      const bookmarksResponse = await getSocialTagBookmarks(slug);
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
  }, [slug]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const loadMoreBookmarks = useCallback(async () => {
    if (!nextPage || isLoadingMore || !slug) return;

    setIsLoadingMore(true);
    try {
      const cursor = getCursorFromUrl(nextPage);
      const response = await getSocialTagBookmarks(slug, cursor);
      setBookmarks((prev) => [...prev, ...response.collection]);
      setNextPage(response.nextPage);
    } catch (err: unknown) {
      console.error('Failed to load more bookmarks:', err);
    } finally {
      setIsLoadingMore(false);
    }
  }, [nextPage, isLoadingMore, slug]);

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
    const account = (bookmark as any).account;
    let profileIdentifier = '';

    if (account) {
      const username = account.username;
      const instance = account.instance || (bookmark as any).instance;

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

  // Use default layout for timeline (no tag filtering)
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
                <Masonry bookmarks={bookmarks} />
              ) : isLayoutListing ? (
                <div>
                  {bookmarks.map((bookmark) => (
                    <BookmarkListing
                      key={bookmark.id}
                      bookmark={bookmark}
                      selectedTagSlugs={[]}
                      onTagToggle={() => {
                        // Timeline doesn't support tag filtering
                      }}
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
                      selectedTagSlugs={[]}
                      onTagToggle={() => {
                        // Timeline doesn't support tag filtering
                      }}
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
                <strong>No bookmarks in timeline!</strong>
              </div>
            </div>
          )}
        </>
      )}

      <div className="mt-1">&nbsp;</div>
    </>
  );
};
