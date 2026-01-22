import { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate, useSearchParams } from 'react-router-dom';
import { Icon } from '../components/Icon/Icon';
import { Bookmark } from '../components/Bookmark/Bookmark';
import { ErrorAlert } from '../components/ErrorAlert/ErrorAlert';
import { getPublicBookmark } from '../services/publicApi';
import { ApiError } from '../services/api';
import { useProfileContext } from '../hooks/useProfileContext';
import { toggleTag, updateTagParams } from '../utils/tags';
import type { Bookmark as BookmarkType } from '../types';
import { LAYOUT_DEFAULT } from '../types';

export const PublicShowBookmark = () => {
  const { profileIdentifier, id } = useParams<{ profileIdentifier: string; id: string }>();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const [bookmark, setBookmark] = useState<BookmarkType | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [errorStatus, setErrorStatus] = useState<number | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [archiveUrl, setArchiveUrl] = useState<string | null>(null);
  const [isLoadingArchive, setIsLoadingArchive] = useState(false);
  const iframeRef = useRef<HTMLIFrameElement>(null);

  const profileContext = useProfileContext(profileIdentifier || '');

  const tagQueryString = searchParams.get('tags') || '';
  const selectedTagSlugs = tagQueryString ? tagQueryString.split(',').filter(Boolean) : [];

  useEffect(() => {
    const loadData = async () => {
      if (!id || !profileContext.baseUrl || !profileContext.username || profileContext.isLoading) {
        if (!id) {
          setError('Bookmark ID is required');
          setIsLoading(false);
        }
        return;
      }

      setIsLoading(true);
      setError(null);
      setErrorStatus(null);

      try {
        const bookmarkData = await getPublicBookmark(profileContext.baseUrl, profileContext.username, id);

        if (bookmarkData) {
          setBookmark(bookmarkData);
        } else {
          setError('Bookmark not found');
          setErrorStatus(404);
        }
      } catch (err: unknown) {
        const message = err instanceof Error ? err.message : 'Failed to load bookmark';
        const status = err instanceof ApiError ? err.status : null;
        setError(message);
        setErrorStatus(status);
      } finally {
        setIsLoading(false);
      }
    };

    loadData();
  }, [id, profileContext.baseUrl, profileContext.username, profileContext.isLoading]);

  // Load and decompress archive file
  useEffect(() => {
    const loadArchive = async (targetBookmark: BookmarkType | null) => {
      if (!targetBookmark?.archive?.contentUrl) {
        setArchiveUrl(null);
        return;
      }

      setIsLoadingArchive(true);

      try {
        const archiveFileUrl = targetBookmark.archive.contentUrl;
        if (!archiveFileUrl) {
          setArchiveUrl(null);
          return;
        }

        // Fetch the gzipped file
        const response = await fetch(archiveFileUrl);
        if (!response.ok) {
          throw new Error('Failed to fetch archive');
        }

        // Get the compressed data as ArrayBuffer
        const compressedData = await response.arrayBuffer();

        // Decompress using DecompressionStream API
        const decompressionStream = new DecompressionStream('gzip');
        const stream = new Response(compressedData).body?.pipeThrough(decompressionStream);

        if (!stream) {
          throw new Error('Failed to create decompression stream');
        }

        // Get the decompressed data
        const decompressedResponse = new Response(stream);
        const decompressedText = await decompressedResponse.text();

        // Create a blob URL from the decompressed HTML
        const blob = new Blob([decompressedText], { type: 'text/html' });
        const blobUrl = URL.createObjectURL(blob);

        setArchiveUrl(blobUrl);
      } catch (err) {
        console.error('Failed to load archive:', err);
        setArchiveUrl(null);
      } finally {
        setIsLoadingArchive(false);
      }
    };

    if (bookmark) {
      loadArchive(bookmark);
    }

    // Cleanup: revoke blob URL when component unmounts or bookmark changes
    return () => {
      setArchiveUrl((currentUrl) => {
        if (currentUrl) {
          URL.revokeObjectURL(currentUrl);
        }
        return null;
      });
    };
  }, [bookmark]);

  if (profileContext.isLoading || isLoading) {
    return (
      <div className="text-center pt-5">
        <div className="spinner-border" role="status">
          <span className="visually-hidden">Loading...</span>
        </div>
      </div>
    );
  }

  if (profileContext.error) {
    return (
      <>
        <ErrorAlert error={profileContext.error} statusCode={profileContext.errorStatus} />
      </>
    );
  }

  if (error || !bookmark) {
    const handleGoBack = () => {
      const params = updateTagParams(selectedTagSlugs, new URLSearchParams());
      navigate(`/social/${profileIdentifier}?${params.toString()}`);
    };

    return (
      <>
        <ErrorAlert error={error} statusCode={errorStatus} />
        <div className="text-center pt-5">
          <button className="btn btn-outline-secondary" onClick={handleGoBack}>
            <Icon name="arrow-left" className="me-2" />
            Go back
          </button>
        </div>
      </>
    );
  }

  const handleTagToggle = (slug: string) => {
    const newSelectedSlugs = toggleTag(slug, selectedTagSlugs);
    const newParams = updateTagParams(newSelectedSlugs, new URLSearchParams());
    navigate(`/social/${profileIdentifier}?${newParams.toString()}`);
  };

  const handleShow = () => {
    // Already on the show page, so do nothing
  };

  const handleIframeLoad = () => {
    const frame = iframeRef.current;
    if (frame && frame.contentWindow) {
      try {
        // Set the height of the iframe as the height of the iframe content
        const scrollHeight = frame.contentWindow.document.body.scrollHeight;
        if (scrollHeight > 0) {
          frame.style.height = (scrollHeight + 100) + 'px';
        }
      } catch (error) {
        console.warn('Could not access iframe content:', error);
      }
    }
  };

  return (
    <>
      <ErrorAlert error={error} statusCode={errorStatus} />

      <div className="row gx-3">
        <Bookmark
          bookmark={bookmark}
          layout={LAYOUT_DEFAULT}
          selectedTagSlugs={selectedTagSlugs}
          onTagToggle={handleTagToggle}
          onShow={handleShow}
          hideShowButton={true}
          hideAddTagButton={true}
          isProfileMode={true}
        />
      </div>

      {bookmark.archive && (
        <div className="row my-3">
          <div className="col-12">
            {isLoadingArchive ? (
              <div className="text-center py-5">
                <div className="spinner-border" role="status">
                  <span className="visually-hidden">Loading archive...</span>
                </div>
              </div>
            ) : archiveUrl ? (
              <div className="card overflow-hidden">
                <iframe
                  ref={iframeRef}
                  src={archiveUrl}
                  onLoad={handleIframeLoad}
                  style={{
                    width: '100%',
                    minHeight: '600px',
                    border: 'none',
                    display: 'block',
                  }}
                  title="Archived Content"
                />
              </div>
            ) : (
              <div className="alert alert-warning" role="alert">
                Failed to load archived content.
              </div>
            )}
          </div>
        </div>
      )}
    </>
  );
};

