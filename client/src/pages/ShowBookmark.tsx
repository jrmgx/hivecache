import { useState, useEffect, useRef, useCallback } from 'react';
import { useParams, useNavigate, useSearchParams } from 'react-router-dom';
import { Icon } from '../components/Icon/Icon';
import { Bookmark } from '../components/Bookmark/Bookmark';
import { ErrorAlert } from '../components/ErrorAlert/ErrorAlert';
import { getBookmark, getBookmarkHistory, getBookmarkNote, createNote, updateNote, ApiError } from '../services/api';
import { formatDate } from '../utils/date';
import { toggleTag, updateTagParams } from '../utils/tags';
import type { Bookmark as BookmarkType } from '../types';
import type { Note } from '@shared';
import { LAYOUT_DEFAULT } from '../types';

export const ShowBookmark = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const [bookmark, setBookmark] = useState<BookmarkType | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [errorStatus, setErrorStatus] = useState<number | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [archiveUrl, setArchiveUrl] = useState<string | null>(null);
  const [isLoadingArchive, setIsLoadingArchive] = useState(false);
  const [bookmarkHistory, setBookmarkHistory] = useState<BookmarkType[]>([]);
  const [selectedBookmarkId, setSelectedBookmarkId] = useState<string | null>(null);
  const [note, setNote] = useState<Note | null>(null);
  const [noteContent, setNoteContent] = useState('');
  const [isNoteLoading, setIsNoteLoading] = useState(false);
  const [saveStatus, setSaveStatus] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');
  const lastSavedContentRef = useRef<string>('');
  const noteContentRef = useRef('');
  const bookmarkOutdatedRef = useRef(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const saveNoteRef = useRef<() => Promise<void>>(() => Promise.resolve());
  const iframeRef = useRef<HTMLIFrameElement>(null);

  const isEditMode = searchParams.get('edit') === 'true';

  useEffect(() => {
    const loadData = async () => {
      if (!id) {
        setError('Bookmark ID is required');
        setIsLoading(false);
        return;
      }

      setIsLoading(true);
      setError(null);
      setErrorStatus(null);

      try {
        const bookmarkData = await getBookmark(id);

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
  }, [id]);

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

    // Determine which bookmark to load archive for
    const targetBookmark = selectedBookmarkId
      ? bookmarkHistory.find(b => b.id === selectedBookmarkId) || bookmark
      : bookmark;

    if (targetBookmark) {
      loadArchive(targetBookmark);
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
  }, [bookmark, bookmarkHistory, selectedBookmarkId]);

  // Load bookmark history
  useEffect(() => {
    const loadHistory = async () => {
      if (!id) {
        setBookmarkHistory([]);
        return;
      }

      try {
        const historyResponse = await getBookmarkHistory(id);
        setBookmarkHistory(historyResponse.collection || []);
      } catch (err) {
        console.error('Failed to load bookmark history:', err);
        setBookmarkHistory([]);
      }
    };

    loadHistory();
  }, [id]);

  // Load note when bookmark is available
  useEffect(() => {
    const loadNote = async () => {
      if (!id) return;
      setIsNoteLoading(true);
      try {
        const noteData = await getBookmarkNote(id);
        if (noteData) {
          setNote(noteData);
          setNoteContent(noteData.content);
          lastSavedContentRef.current = noteData.content;
        } else {
          setNote(null);
          setNoteContent('');
          lastSavedContentRef.current = '';
        }
      } catch (err) {
        console.error('Failed to load note:', err);
        setNote(null);
        setNoteContent('');
      } finally {
        setIsNoteLoading(false);
      }
    };
    if (bookmark) {
      loadNote();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- only reload when bookmark id changes, not on object reference change
  }, [id, bookmark?.id]);

  const saveNote = useCallback(async () => {
    if (!bookmark || bookmark.outdated || noteContent === lastSavedContentRef.current) return;
    if (!note && !noteContent.trim()) return;
    setSaveStatus('saving');
    try {
      const bookmarkIri = bookmark['@iri'];
      if (note) {
        const updated = await updateNote(note.id, noteContent);
        setNote(updated);
      } else {
        const created = await createNote(bookmarkIri, noteContent);
        setNote(created);
      }
      lastSavedContentRef.current = noteContent;
      setSaveStatus('saved');
      setTimeout(() => setSaveStatus('idle'), 2000);
    } catch (err) {
      console.error('Failed to save note:', err);
      setSaveStatus('error');
    }
  }, [bookmark, note, noteContent]);

  saveNoteRef.current = saveNote;
  noteContentRef.current = noteContent;
  bookmarkOutdatedRef.current = !!bookmark?.outdated;

  useEffect(() => {
    if (!bookmark || bookmark.outdated) return;
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
      debounceRef.current = null;
    }
    if (noteContent !== lastSavedContentRef.current) {
      debounceRef.current = setTimeout(() => saveNoteRef.current(), 3000);
    }
    return () => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
        debounceRef.current = null;
      }
    };
    // saveNote excluded: including it would re-run on every keystroke (it depends on noteContent) and trigger immediate save in cleanup
  }, [noteContent, bookmark]);

  useEffect(() => {
    return () => {
      if (!bookmarkOutdatedRef.current && noteContentRef.current !== lastSavedContentRef.current) {
        saveNoteRef.current();
      }
    };
  }, []);

  useEffect(() => {
    const handleBeforeUnload = (e: BeforeUnloadEvent) => {
      if (noteContent !== lastSavedContentRef.current) {
        e.preventDefault();
      }
    };
    window.addEventListener('beforeunload', handleBeforeUnload);
    return () => window.removeEventListener('beforeunload', handleBeforeUnload);
  }, [noteContent]);

  if (isLoading) {
    return (
      <div className="text-center pt-5">
        <div className="spinner-border" role="status">
          <span className="visually-hidden">Loading...</span>
        </div>
      </div>
    );
  }

  if (error || !bookmark) {
    const tagQueryString = searchParams.get('tags') || '';
    const selectedTagSlugs = tagQueryString ? tagQueryString.split(',').filter(Boolean) : [];
    const searchQuery = searchParams.get('search') || '';

    const handleGoBack = () => {
      const params = updateTagParams(selectedTagSlugs, new URLSearchParams());
      // Preserve search query when navigating back
      if (searchQuery.trim()) {
        params.set('search', searchQuery);
      }
      navigate(`/me?${params.toString()}`);
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

  const tagQueryString = searchParams.get('tags') || '';
  const selectedTagSlugs = tagQueryString ? tagQueryString.split(',').filter(Boolean) : [];
  const searchQuery = searchParams.get('search') || '';

  const handleTagToggle = (slug: string) => {
    const newSelectedSlugs = toggleTag(slug, selectedTagSlugs);
    const newParams = updateTagParams(newSelectedSlugs, new URLSearchParams());
    // Preserve search query when navigating back
    if (searchQuery.trim()) {
      newParams.set('search', searchQuery);
    }
    navigate(`/me?${newParams.toString()}`);
  };

  const handleTagsSave = () => {
    // Reload bookmark to get updated tags
    if (id) {
      getBookmark(id).then((bookmarkData) => {
        if (bookmarkData) {
          setBookmark(bookmarkData);
        }
      });
    }
  };

  const handleShow = () => {
    // Already on the show page, so do nothing
  };

  const handleHistoryButtonClick = (bookmarkId: string | null) => {
    setSelectedBookmarkId(bookmarkId);
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

  const handleEditSave = (updatedBookmark: BookmarkType) => {
    setBookmark(updatedBookmark);
    // Remove edit query parameter
    const newParams = new URLSearchParams(searchParams);
    newParams.delete('edit');
    setSearchParams(newParams);
  };

  const handleEditClose = () => {
    // Remove edit query parameter
    const newParams = new URLSearchParams(searchParams);
    newParams.delete('edit');
    setSearchParams(newParams);
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
          onTagsSave={handleTagsSave}
          showEditModal={isEditMode}
          onEditSave={handleEditSave}
          onEditClose={handleEditClose}
          hideShowButton={true}
        />
        <div
          className={`col-12 col-md my-2 ${bookmark.outdated ? 'pe-none' : ''}`}
          style={{ opacity: bookmark.outdated ? 0.5 : 1 }}
        >
          <div className="card h-100 d-flex flex-column">
            <div className="card-header">
              Personal Note<br></br>
              <small style={{opacity: 0.5}}>
                This will be always private
              </small>
            </div>
            <div className="card-body d-flex flex-column p-0 overflow-hidden flex-grow-1">
              {isNoteLoading ? (
                <div className="flex-grow-1 d-flex align-items-center justify-content-center p-3">
                  <div className="spinner-border spinner-border-sm" role="status">
                    <span className="visually-hidden">Loading note...</span>
                  </div>
                </div>
              ) : (
                <textarea
                  className="form-control border-0 flex-grow-1 resize-none p-3"
                  value={noteContent}
                  onChange={(e) => setNoteContent(e.target.value)}
                  onBlur={() => {
                    if (!bookmark.outdated && noteContent !== lastSavedContentRef.current) {
                      if (debounceRef.current) {
                        clearTimeout(debounceRef.current);
                        debounceRef.current = null;
                      }
                      saveNoteRef.current();
                    }
                  }}
                  placeholder=""
                  style={{ minHeight: '120px' }}
                  aria-label="Note"
                  disabled={!!bookmark.outdated}
                />
              )}
            </div>
            <div className="card-footer text-body-secondary d-flex align-items-center py-1 pe-0">
              <div className="fs-small flex-grow-1">
                {saveStatus === 'saving' && 'Saving…'}
                {saveStatus === 'saved' && 'Saved'}
                {saveStatus === 'error' && 'Error saving'}
                {saveStatus === 'idle' && (note ? 'Saved' : 'No note associated')}
              </div>
              <div>
                <button className="btn btn-outline-secondary border-0 pe-none" disabled tabIndex={-1}>
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width="16" height="16" fill="currentColor"></svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      {bookmarkHistory.length > 0 && (
        <div className='row my-2'>
          <div className="col-12">
            <div className="overflow-x-auto">
              <div className="btn-group flex-nowrap" role="group" aria-label="Basic outlined example" style={{ minWidth: 'max-content' }}>
                <button
                  type="button"
                  className={`btn btn-outline-primary ${selectedBookmarkId === null ? 'active' : ''}`}
                  onClick={() => handleHistoryButtonClick(null)}
                >
                  {formatDate(bookmark.createdAt)}
                </button>
                {bookmarkHistory.map((historyBookmark) => (
                  <button
                    key={historyBookmark.id}
                    type="button"
                    className={`btn btn-outline-primary ${selectedBookmarkId === historyBookmark.id ? 'active' : ''}`}
                    onClick={() => handleHistoryButtonClick(historyBookmark.id)}
                  >
                    {formatDate(historyBookmark.createdAt)}
                  </button>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}

      {(bookmark.archive || bookmarkHistory.some(b => b.archive)) && (
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

