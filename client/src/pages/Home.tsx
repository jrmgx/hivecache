import { useState, useEffect, useRef, useCallback, useMemo } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { Tag } from '../components/Tag/Tag';
import { Bookmark } from '../components/Bookmark/Bookmark';
import { BookmarkListing } from '../components/Bookmark/BookmarkListing';
import { Masonry } from '../components/Masonry/Masonry';
import { ErrorAlert } from '../components/ErrorAlert/ErrorAlert';
import { SearchInput } from '../components/SearchInput/SearchInput';
import { getBookmarks, getTags, getCursorFromUrl, ApiError } from '../services/api';
import { indexAllBookmarks, getIndexedBookmarks, syncBookmarkIndex, isSearchAvailable } from '../services/bookmarkIndex';
import { searchBookmarks } from '../services/search';
import { toggleTag, updateTagParams } from '../utils/tags';
import type { Bookmark as BookmarkType, Tag as TagType } from '../types';
import { LAYOUT_DEFAULT, LAYOUT_IMAGE, LAYOUT_LISTING } from '../types';

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

  // Search and indexing state
  const [indexedBookmarks, setIndexedBookmarks] = useState<BookmarkType[]>([]);
  const [isIndexing, setIsIndexing] = useState(false);
  const [indexingProgress, setIndexingProgress] = useState(0);
  // Initialize search query from URL params
  const initialSearchQuery = searchParams.get('search') || '';
  const [searchQuery, setSearchQuery] = useState(initialSearchQuery);
  const [debouncedSearchQuery, setDebouncedSearchQuery] = useState(initialSearchQuery);
  const [searchResults, setSearchResults] = useState<BookmarkType[] | null>(null);
  const [searchAvailable, setSearchAvailable] = useState(false);
  const hasAttemptedLoadRef = useRef(false);
  const indexedBookmarksRef = useRef<BookmarkType[]>([]);
  const isUpdatingFromUserInputRef = useRef(false);
  const lastSyncedSearchParamRef = useRef<string>(initialSearchQuery);

  const tagQueryString = searchParams.get('tags') || '';
  const selectedTagSlugs = useMemo(
    () => (tagQueryString ? tagQueryString.split(',').filter(Boolean) : []),
    [tagQueryString]
  );
  const selectedTags = tags.filter((tag) => selectedTagSlugs.includes(tag.slug));
  const layout = layoutForTags(selectedTags);
  const isLayoutImage = layout === LAYOUT_IMAGE;
  const isLayoutListing = layout === LAYOUT_LISTING;

  // Sync search query with URL params (e.g., when navigating back)
  useEffect(() => {
    // Skip if we're updating from user input to avoid redundant updates
    if (isUpdatingFromUserInputRef.current) {
      isUpdatingFromUserInputRef.current = false;
      // Update ref to track what we just set
      const searchParam = searchParams.get('search') || '';
      lastSyncedSearchParamRef.current = searchParam;
      return;
    }
    const searchParam = searchParams.get('search') || '';
    // Only sync if the param actually changed (to avoid unnecessary updates)
    if (searchParam !== lastSyncedSearchParamRef.current) {
      setSearchQuery(searchParam);
      // Also update debounced query immediately when syncing from URL (no debounce delay)
      setDebouncedSearchQuery(searchParam);
      lastSyncedSearchParamRef.current = searchParam;
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams]); // Sync when URL params change (e.g., browser back button)

  // Determine which bookmarks to display
  const displayBookmarks = debouncedSearchQuery.trim() ? (searchResults || []) : bookmarks;

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

  // Ensure search query is synced from URL on mount (in case component remounts)
  useEffect(() => {
    const searchParam = searchParams.get('search') || '';
    if (searchParam && searchQuery !== searchParam) {
      setSearchQuery(searchParam);
      setDebouncedSearchQuery(searchParam);
      lastSyncedSearchParamRef.current = searchParam;
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []); // Only run on mount

  // Check if search is available on mount
  useEffect(() => {
    setSearchAvailable(isSearchAvailable());
  }, []);

  // Load indexed bookmarks from IndexedDB when search is available and we don't have them yet
  useEffect(() => {
    if (!searchAvailable || hasAttemptedLoadRef.current) {
      return;
    }

    const loadIndexedBookmarks = async () => {
      hasAttemptedLoadRef.current = true;
      const stored = await getIndexedBookmarks();
      if (stored && stored.length > 0) {
        indexedBookmarksRef.current = stored;
        setIndexedBookmarks(stored);
      }
    };

    loadIndexedBookmarks();
  }, [searchAvailable]);

  // Index all bookmarks in the background
  const startIndexing = useCallback(async () => {
    // Skip indexing if IndexedDB is not available
    if (!isSearchAvailable()) {
      return;
    }

    // Check if already indexed
    const existingIndex = await getIndexedBookmarks();
    if (existingIndex && existingIndex.length > 0) {
      indexedBookmarksRef.current = existingIndex;
      setIndexedBookmarks(existingIndex);
      // Try to sync in the background
      syncBookmarkIndex().catch((err) => {
        console.error('Failed to sync bookmark index:', err);
      });
      return;
    }

    setIsIndexing(true);
    setIndexingProgress(0);

    try {
      const indexed = await indexAllBookmarks((progress) => {
        setIndexingProgress(progress);
      });
      indexedBookmarksRef.current = indexed;
      setIndexedBookmarks(indexed);
    } catch (err: unknown) {
      console.error('Failed to index bookmarks:', err);
      // Don't show error to user, indexing is background operation
    } finally {
      setIsIndexing(false);
    }
  }, []);

  // Listen for bookmarks updated event to refresh the list
  // Note: We no longer invalidate the index, sync will handle updates
  useEffect(() => {
    const handleBookmarksUpdated = async (event: Event) => {
      const customEvent = event as CustomEvent<{ updatedBookmark?: BookmarkType }>;
      const updatedBookmark = customEvent.detail?.updatedBookmark;

      if (updatedBookmark) {
        // Update only the specific bookmark to avoid UI jumps
        setBookmarks((prevBookmarks) =>
          prevBookmarks.map((b) => (b.id === updatedBookmark.id ? updatedBookmark : b))
        );
        // Also update indexed bookmarks if they exist
        if (indexedBookmarks.length > 0) {
          setIndexedBookmarks((prevIndexed) => {
            const updated = prevIndexed.map((b) => (b.id === updatedBookmark.id ? updatedBookmark : b));
            indexedBookmarksRef.current = updated;
            return updated;
          });
        }
      } else {
        // Fallback: if no bookmark data provided, reload all (for other update scenarios)
        loadData();
      }

      // Wait a bit for the server to process the update and create the index action
      // Then try to sync the index in the background
      setTimeout(async () => {
        try {
          const synced = await syncBookmarkIndex();
          if (synced) {
          // Reload indexed bookmarks if sync was successful
          const updatedIndex = await getIndexedBookmarks();
          if (updatedIndex) {
            indexedBookmarksRef.current = updatedIndex;
            setIndexedBookmarks(updatedIndex);
          }
          }
        } catch (err) {
          console.error('Failed to sync bookmark index after update:', err);
        }
      }, 1000); // Wait 1 second for server to process
    };

    // Listen for refresh current page event (triggered by logo click)
    const handleRefreshCurrentPage = () => {
      loadData();
    };

    window.addEventListener('bookmarksUpdated', handleBookmarksUpdated);
    window.addEventListener('refreshCurrentPage', handleRefreshCurrentPage);
    return () => {
      window.removeEventListener('bookmarksUpdated', handleBookmarksUpdated);
      window.removeEventListener('refreshCurrentPage', handleRefreshCurrentPage);
    };
  }, [loadData]);

  // Start indexing after initial data load
  useEffect(() => {
    if (!isLoading && bookmarks.length > 0) {
      startIndexing();
    }
  }, [isLoading, startIndexing]);

  // Sync check - sync index once per hour
  useEffect(() => {
    if (!isSearchAvailable()) {
      return;
    }

    const checkAndSync = async () => {
      const existingIndex = await getIndexedBookmarks();
      if (existingIndex && existingIndex.length > 0) {
        // Try to sync
        syncBookmarkIndex().catch((err) => {
          console.error('Failed to sync bookmark index:', err);
        });
      }
    };

    // Run sync check on mount
    checkAndSync();

    const dailyInterval = setInterval(checkAndSync, 60 * 60 * 1000); // 1 hour

    return () => {
      clearInterval(dailyInterval);
    };
  }, []);

  // Debounce search query
  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearchQuery(searchQuery);
    }, 300); // 300ms debounce delay

    return () => {
      clearTimeout(timer);
    };
  }, [searchQuery]);

  // Update ref whenever indexedBookmarks changes
  useEffect(() => {
    indexedBookmarksRef.current = indexedBookmarks;
  }, [indexedBookmarks]);

  // Handle search query changes (using debounced query)
  useEffect(() => {
    if (!searchAvailable) {
      setSearchResults(null);
      return;
    }

    if (!debouncedSearchQuery.trim()) {
      setSearchResults(null);
      return;
    }

    // Use ref to avoid dependency on indexedBookmarks array
    const currentIndexed = indexedBookmarksRef.current;
    if (currentIndexed.length === 0) {
      setSearchResults([]);
      return;
    }

    const results = searchBookmarks(debouncedSearchQuery, currentIndexed, selectedTagSlugs);
    setSearchResults(results);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [debouncedSearchQuery, searchAvailable, selectedTagSlugs]); // indexedBookmarks intentionally omitted to avoid infinite loop

  // Re-run search when indexedBookmarks becomes available (using length to avoid infinite loop)
  useEffect(() => {
    if (!searchAvailable || !debouncedSearchQuery.trim() || indexedBookmarks.length === 0) {
      return;
    }

    // Use ref to get latest indexed bookmarks
    const currentIndexed = indexedBookmarksRef.current;
    if (currentIndexed.length === 0) {
      return;
    }

    const results = searchBookmarks(debouncedSearchQuery, currentIndexed, selectedTagSlugs);
    setSearchResults(results);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [indexedBookmarks.length, debouncedSearchQuery, selectedTagSlugs]); // Re-run when indexed bookmarks become available or search query changes

  const handleSearchChange = (query: string) => {
    isUpdatingFromUserInputRef.current = true;
    setSearchQuery(query);
    // Update URL params with search query
    const newParams = new URLSearchParams(searchParams);
    if (query.trim()) {
      newParams.set('search', query);
    } else {
      newParams.delete('search');
    }
    setSearchParams(newParams, { replace: true });
  };

  const handleSearchClear = () => {
    isUpdatingFromUserInputRef.current = true;
    setSearchQuery('');
    setSearchResults(null);
    // Remove search from URL params
    const newParams = new URLSearchParams(searchParams);
    newParams.delete('search');
    setSearchParams(newParams, { replace: true });
  };

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
    // Preserve search query when navigating to bookmark show page
    if (searchQuery.trim()) {
      params.set('search', searchQuery);
    }
    navigate(`/me/bookmarks/${id}${params.toString() ? `?${params.toString()}` : ''}`);
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
      ) : (
        <>
          {/* Search Input */}
          {searchAvailable && (
            <SearchInput
              value={searchQuery}
              onChange={handleSearchChange}
              onClear={handleSearchClear}
              disabled={isIndexing}
              placeholder={
                isIndexing
                  ? `Indexing... ${indexingProgress}%`
                  : 'Search bookmarks...'
              }
              selectedTags={selectedTags}
              selectedTagSlugs={selectedTagSlugs}
              onTagToggle={handleTagToggle}
            />
          )}

          {/* Bookmark List */}
          {displayBookmarks.length > 0 ? (
            <>
              {isLayoutImage ? (
                <Masonry bookmarks={displayBookmarks} />
              ) : isLayoutListing ? (
                <div>
                  {displayBookmarks.map((bookmark) => (
                    <BookmarkListing
                      key={bookmark.id}
                      bookmark={bookmark}
                      selectedTagSlugs={selectedTagSlugs}
                      onTagToggle={handleTagToggle}
                      onShow={handleShow}
                    />
                  ))}
                </div>
              ) : (
                <div className="row gx-3">
                  {displayBookmarks.map((bookmark) => (
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
              {/* Show pagination only when not searching */}
              {!debouncedSearchQuery.trim() && nextPage && (
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
                <strong>
                  {debouncedSearchQuery.trim()
                    ? 'No bookmarks found matching your search!'
                    : 'No bookmark matching!'}
                </strong>
              </div>
              {!debouncedSearchQuery.trim() && (
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
              )}
            </div>
          )}
        </>
      )}

      <div className="mt-1">&nbsp;</div>
    </>
  );
};

