import { useState, useEffect, useRef, useCallback } from 'react';
import { ErrorAlert } from '../ErrorAlert/ErrorAlert';
import { updateTag, deleteTag, ApiError } from '../../services/api';
import type { Tag as TagType } from '../../types';
import { LAYOUT_DEFAULT, LAYOUT_EMBEDDED, LAYOUT_IMAGE } from '../../types';

interface EditTagProps {
  tag: TagType | null;
  onSave: () => void;
  onClose: () => void;
}

export const EditTag = ({ tag, onSave, onClose }: EditTagProps) => {
  const modalRef = useRef<HTMLDivElement>(null);
  const [formData, setFormData] = useState({
    name: '',
    icon: '',
    layout: LAYOUT_DEFAULT,
    pinned: false,
    isPublic: false,
  });
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [saveErrorStatus, setSaveErrorStatus] = useState<number | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [deleteErrorStatus, setDeleteErrorStatus] = useState<number | null>(null);

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
    setFormData({
      name: '',
      icon: '',
      layout: LAYOUT_DEFAULT,
      pinned: false,
      isPublic: false,
    });
    setSaveError(null);
    setSaveErrorStatus(null);
    setDeleteError(null);
    setDeleteErrorStatus(null);
    onClose();
  }, [onClose]);

  // Show modal when tag changes
  useEffect(() => {
    if (tag) {
      setFormData({
        name: tag.name,
        icon: tag.icon || '',
        layout: tag.layout || LAYOUT_DEFAULT,
        pinned: tag.pinned || false,
        isPublic: tag.isPublic || false,
      });
      setSaveError(null);
      showModal();
    }
  }, [tag, showModal]);

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
    if (!tag) return;

    setIsSaving(true);
    setSaveError(null);
    setSaveErrorStatus(null);

    try {
      const updatedTag: TagType = {
        ...tag,
        name: formData.name.trim(),
        icon: formData.icon.trim() || null,
        layout: formData.layout,
        pinned: formData.pinned,
        isPublic: formData.isPublic,
      };

      await updateTag(tag.slug, updatedTag);
      // Dispatch custom event to notify other components (like Layout sidebar) to reload tags
      window.dispatchEvent(new CustomEvent('tagsUpdated'));
      onSave();
      hideModal();
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to update tag';
      const status = err instanceof ApiError ? err.status : null;
      setSaveError(message);
      setSaveErrorStatus(status);
    } finally {
      setIsSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!tag) return;

    const confirmed = window.confirm(
      `Are you sure you want to delete the tag "${tag.name}"? This action cannot be undone.`
    );

    if (!confirmed) return;

    setIsDeleting(true);
    setDeleteError(null);
    setDeleteErrorStatus(null);
    setSaveError(null);
    setSaveErrorStatus(null);

    try {
      await deleteTag(tag.slug);
      // Dispatch custom event to notify other components (like Layout sidebar) to reload tags
      window.dispatchEvent(new CustomEvent('tagsUpdated'));
      window.dispatchEvent(new CustomEvent('bookmarksUpdated'));
      onSave();
      hideModal();
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to delete tag';
      const status = err instanceof ApiError ? err.status : null;
      setDeleteError(message);
      setDeleteErrorStatus(status);
    } finally {
      setIsDeleting(false);
    }
  };

  return (
    <div
      ref={modalRef}
      className="modal fade"
      id="editTag"
      tabIndex={-1}
      aria-labelledby="editTagLabel"
      aria-hidden="true"
    >
      <div className="modal-dialog">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title" id="editTagLabel">
              Edit Tag
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
              <ErrorAlert error={saveError || deleteError} statusCode={saveErrorStatus || deleteErrorStatus} />

              <div className="mb-3">
                <label htmlFor="tagName" className="form-label">
                  Name
                </label>
                <div className="input-group">
                  <input
                    type="text"
                    className="form-control"
                    id="tagIcon"
                    value={formData.icon}
                    onChange={(e) => setFormData({ ...formData, icon: e.target.value })}
                    placeholder="emoji"
                    disabled={isSaving || isDeleting}
                    style={{ width: '3.5rem', minWidth: '3.5rem', maxWidth: '3.5rem', flexShrink: 0 }}
                  />
                  <input
                    type="text"
                    className="form-control"
                    id="tagName"
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    required
                    disabled={isSaving || isDeleting}
                  />
                </div>
              </div>

              <div className="mb-3">
                <div className="form-check">
                  <input
                    className="form-check-input"
                    type="checkbox"
                    id="tagPinned"
                    checked={formData.pinned}
                    onChange={(e) => setFormData({ ...formData, pinned: e.target.checked })}
                    disabled={isSaving || isDeleting}
                  />
                  <label className="form-check-label" htmlFor="tagPinned">
                    Favorite
                  </label>
                </div>
              </div>

              <div className="mb-3">
                <div className="form-check">
                  <input
                    className="form-check-input"
                    type="checkbox"
                    id="tagIsPublic"
                    checked={formData.isPublic}
                    onChange={(e) => setFormData({ ...formData, isPublic: e.target.checked })}
                    disabled={isSaving || isDeleting}
                  />
                  <label className="form-check-label" htmlFor="tagIsPublic">
                    Public
                  </label>
                </div>
              </div>

              <div className="mb-3">
                <label htmlFor="tagLayout" className="form-label">
                  Layout
                </label>
                <select
                  className="form-select"
                  id="tagLayout"
                  value={formData.layout}
                  onChange={(e) => setFormData({ ...formData, layout: e.target.value })}
                  disabled={isSaving || isDeleting}
                >
                  <option value={LAYOUT_DEFAULT}>Default</option>
                  <option value={LAYOUT_EMBEDDED}>Embedded</option>
                  <option value={LAYOUT_IMAGE}>Image</option>
                </select>
                <div className="form-text">
                  Choose how bookmarks with this tag are displayed
                </div>
              </div>
            </div>
            <div className="modal-footer">
              <button
                type="button"
                className="btn btn-danger"
                onClick={handleDelete}
                disabled={isSaving || isDeleting}
              >
                {isDeleting ? (
                  <>
                    <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    Deleting...
                  </>
                ) : (
                  'Delete'
                )}
              </button>
              <div className="ms-auto">
                <button
                  type="button"
                  className="btn btn-secondary me-2"
                  onClick={hideModal}
                  disabled={isSaving || isDeleting}
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="btn btn-primary"
                  disabled={isSaving || isDeleting}
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
            </div>
          </form>
        </div>
      </div>
    </div>
  );
};

