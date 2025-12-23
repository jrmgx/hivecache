import { useState, useEffect, useRef, useCallback } from 'react';
import { updateTag } from '../../services/api';
import type { Tag as TagType } from '../../types';
import { LAYOUT_DEFAULT, LAYOUT_EMBEDDED, LAYOUT_IMAGE } from '../../types';

declare global {
  interface Window {
    bootstrap?: {
      Modal: {
        getInstance: (element: HTMLElement | string) => { show: () => void; hide: () => void } | null;
        getOrCreateInstance: (element: HTMLElement | string) => { show: () => void; hide: () => void };
      };
    };
  }
}

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
  });
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

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
    });
    setSaveError(null);
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

    try {
      const updatedTag: TagType = {
        ...tag,
        name: formData.name.trim(),
        icon: formData.icon.trim() || null,
        layout: formData.layout,
        pinned: formData.pinned,
      };

      await updateTag(tag.slug, updatedTag);
      // Dispatch custom event to notify other components (like Layout sidebar) to reload tags
      window.dispatchEvent(new CustomEvent('tagsUpdated'));
      onSave();
      hideModal();
    } catch (err) {
      setSaveError(err instanceof Error ? err.message : 'Failed to update tag');
    } finally {
      setIsSaving(false);
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
              {saveError && (
                <div className="alert alert-danger" role="alert">
                  {saveError}
                </div>
              )}

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
                    placeholder="icon"
                    disabled={isSaving}
                    style={{ width: '3.5rem', minWidth: '3.5rem', maxWidth: '3.5rem', flexShrink: 0 }}
                  />
                  <input
                    type="text"
                    className="form-control"
                    id="tagName"
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    required
                    disabled={isSaving}
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
                    disabled={isSaving}
                  />
                  <label className="form-check-label" htmlFor="tagPinned">
                    Favorite
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
                  disabled={isSaving}
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
                className="btn btn-secondary"
                onClick={hideModal}
                disabled={isSaving}
              >
                Cancel
              </button>
              <button
                type="submit"
                className="btn btn-primary"
                disabled={isSaving}
              >
                {isSaving ? (
                  <>
                    <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    Saving...
                  </>
                ) : (
                  'Save Changes'
                )}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
};

