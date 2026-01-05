import { useState, useEffect, useRef, useCallback } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { Tag } from '../components/Tag/Tag';
import { Bookmark } from '../components/Bookmark/Bookmark';
import { Masonry } from '../components/Masonry/Masonry';
import { ErrorAlert } from '../components/ErrorAlert/ErrorAlert';
import { SearchInput } from '../components/SearchInput/SearchInput';
import { getBookmarks, getTags, getCursorFromUrl, ApiError } from '../services/api';
import { indexAllBookmarks, getIndexedBookmarks, syncBookmarkIndex, isSearchAvailable } from '../services/bookmarkIndex';
import { searchBookmarks } from '../services/search';
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

  // Search and indexing state
  const [indexedBookmarks, setIndexedBookmarks] = useState<BookmarkType[]>([]);
  const [isIndexing, setIsIndexing] = useState(false);
  const [indexingProgress, setIndexingProgress] = useState(0);
  const [searchQuery, setSearchQuery] = useState('');
  const [debouncedSearchQuery, setDebouncedSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<BookmarkType[] | null>(null);
  const [searchAvailable, setSearchAvailable] = useState(false);

  const tagQueryString = searchParams.get('tags') || '';
  const selectedTagSlugs = tagQueryString ? tagQueryString.split(',').filter(Boolean) : [];
  const selectedTags = tags.filter((tag) => selectedTagSlugs.includes(tag.slug));
  const layout = layoutForTags(selectedTags);
  const isLayoutImage = layout === LAYOUT_IMAGE;

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

  // Check if search is available on mount
  useEffect(() => {
    setSearchAvailable(isSearchAvailable());
  }, []);

  // Index all bookmarks in the background
  const startIndexing = useCallback(async () => {
    // Skip indexing if IndexedDB is not available
    if (!isSearchAvailable()) {
      return;
    }

    // Check if already indexed
    const existingIndex = await getIndexedBookmarks();
    if (existingIndex && existingIndex.length > 0) {
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
    const handleBookmarksUpdated = async () => {
      loadData();
      // Wait a bit for the server to process the update and create the index action
      // Then try to sync the index in the background
      setTimeout(async () => {
        try {
          const synced = await syncBookmarkIndex();
          if (synced) {
            // Reload indexed bookmarks if sync was successful
            const updatedIndex = await getIndexedBookmarks();
            if (updatedIndex) {
              setIndexedBookmarks(updatedIndex);
            }
          }
        } catch (err) {
          console.error('Failed to sync bookmark index after update:', err);
        }
      }, 1000); // Wait 1 second for server to process
    };

    window.addEventListener('bookmarksUpdated', handleBookmarksUpdated);
    return () => {
      window.removeEventListener('bookmarksUpdated', handleBookmarksUpdated);
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

    const performSearch = async () => {
      if (indexedBookmarks.length === 0) {
        // Try to load from IndexedDB if available
        const stored = await getIndexedBookmarks();
        if (stored) {
          setIndexedBookmarks(stored);
          const results = searchBookmarks(debouncedSearchQuery, stored);
          setSearchResults(results);
        } else {
          setSearchResults([]);
        }
      } else {
        const results = searchBookmarks(debouncedSearchQuery, indexedBookmarks);
        setSearchResults(results);
      }
    };

    performSearch();
  }, [debouncedSearchQuery, indexedBookmarks, searchAvailable]);

  const handleSearchChange = (query: string) => {
    setSearchQuery(query);
  };

  const handleSearchClear = () => {
    setSearchQuery('');
    setSearchResults(null);
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
            />
          )}

          {/* Bookmark List */}
          {displayBookmarks.length > 0 ? (
            <>
              {isLayoutImage ? (
                <Masonry bookmarks={displayBookmarks} />
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

