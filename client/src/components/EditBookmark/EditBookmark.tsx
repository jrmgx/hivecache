import { useState, useEffect, useRef, useCallback } from 'react';
import { ErrorAlert } from '../ErrorAlert/ErrorAlert';
import { updateBookmark, ApiError } from '../../services/api';
import type { Bookmark as BookmarkType } from '../../types';

interface EditBookmarkProps {
  bookmark: BookmarkType | null;
  onSave: (updatedBookmark: BookmarkType) => void;
  onClose: () => void;
}

export const EditBookmark = ({ bookmark, onSave, onClose }: EditBookmarkProps) => {
  const modalRef = useRef<HTMLDivElement>(null);
  const [title, setTitle] = useState('');
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [saveErrorStatus, setSaveErrorStatus] = useState<number | null>(null);

  const showModal = useCallback(() => {
    if (modalRef.current && window.bootstrap) {
      const modalInstance = window.bootstrap.Modal.getOrCreateInstance(modalRef.current);
      modalInstance.show();
    }
  }, []);

  const hideModal = useCallback(() => {
    if (modalRef.current && window.bootstrap) {
      const modalInstance = window.bootstrap.Modal.getInstance(modalRef.current);
      if (modalInstance) {
        modalInstance.hide();
      }
    }
  }, []);

  const handleModalClose = useCallback(() => {
    setSaveError(null);
    setSaveErrorStatus(null);
    if (bookmark) {
      setTitle(bookmark.title);
    }
    onClose();
  }, [bookmark, onClose]);

  // Update title when bookmark changes
  useEffect(() => {
    if (bookmark) {
      setTitle(bookmark.title);
    }
  }, [bookmark?.title]);

  // Show modal when bookmark changes
  useEffect(() => {
    if (bookmark) {
      setSaveError(null);
      setSaveErrorStatus(null);
      showModal();
    }
  }, [bookmark, showModal]);

  // Handle Bootstrap modal hidden event
  useEffect(() => {
    const modalElement = modalRef.current;
    if (!modalElement) return;

    const handleHidden = () => {
      handleModalClose();
    };

    modalElement.addEventListener('hidden.bs.modal', handleHidden);
    return () => {
      modalElement.removeEventListener('hidden.bs.modal', handleHidden);
    };
  }, [handleModalClose]);

  const handleFormSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!bookmark || !title.trim()) return;

    setIsSaving(true);
    setSaveError(null);
    setSaveErrorStatus(null);

    try {
      // Update bookmark title via API
      const updatedBookmark = await updateBookmark(bookmark.id, { title: title.trim() });

      // Dispatch custom events to notify other components
      window.dispatchEvent(new CustomEvent('bookmarksUpdated'));
      onSave(updatedBookmark);
      hideModal();
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to update bookmark';
      const status = err instanceof ApiError ? err.status : null;
      setSaveError(message);
      setSaveErrorStatus(status);
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div
      ref={modalRef}
      className="modal fade"
      id="editBookmark"
      tabIndex={-1}
      aria-labelledby="editBookmarkLabel"
      aria-hidden="true"
    >
      <div className="modal-dialog">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title" id="editBookmarkLabel">
              Edit Bookmark
            </h5>
            <button
              type="button"
              className="btn-close"
              aria-label="Close"
              onClick={hideModal}
            ></button>
          </div>
          <form onSubmit={handleFormSubmit}>
            <div className="modal-body">
              <ErrorAlert error={saveError} statusCode={saveErrorStatus} />

              <div className="mb-3">
                <label htmlFor="bookmark-title-input" className="form-label">
                  Title
                </label>
                <input
                  type="text"
                  className="form-control"
                  id="bookmark-title-input"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  disabled={isSaving}
                  autoFocus
                />
              </div>
            </div>
            <div className="modal-footer">
              <button
                type="button"
                className="btn btn-secondary"
                onClick={hideModal}
                disabled={isSaving}
              >
                Cancel
              </button>
              <button
                type="submit"
                className="btn btn-primary"
                disabled={isSaving || !title.trim() || (bookmark ? title.trim() === bookmark.title : false)}
              >
                {isSaving ? (
                  <>
                    <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    Saving...
                  </>
                ) : (
                  'Save'
                )}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
};

