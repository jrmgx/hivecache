import { useState, useEffect, useRef, useCallback } from 'react';
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.bootstrap5.css';
import { updateBookmarkTags, getTags, createTag } from '../../services/api';
import type { Bookmark as BookmarkType, Tag as TagType } from '../../types';

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

interface EditBookmarkTagsProps {
  bookmark: BookmarkType | null;
  onSave: () => void;
  onClose: () => void;
}

export const EditBookmarkTags = ({ bookmark, onSave, onClose }: EditBookmarkTagsProps) => {
  const modalRef = useRef<HTMLDivElement>(null);
  const selectRef = useRef<HTMLSelectElement>(null);
  const tomSelectInstanceRef = useRef<TomSelect | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [availableTags, setAvailableTags] = useState<TagType[]>([]);
  const [isLoadingTags, setIsLoadingTags] = useState(false);
  const availableTagsRef = useRef<TagType[]>([]);

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
    // Destroy tom-select instance when closing
    if (tomSelectInstanceRef.current) {
      tomSelectInstanceRef.current.destroy();
      tomSelectInstanceRef.current = null;
    }
    onClose();
  }, [onClose]);

  // Load available tags
  useEffect(() => {
    const loadTags = async () => {
      setIsLoadingTags(true);
      try {
        const tags = await getTags();
        setAvailableTags(tags);
        availableTagsRef.current = tags;
      } catch (err) {
        console.error('Failed to load tags:', err);
        setAvailableTags([]);
        availableTagsRef.current = [];
      } finally {
        setIsLoadingTags(false);
      }
    };

    if (bookmark) {
      loadTags();
    }
  }, [bookmark]);

  // Initialize tom-select when bookmark and tags are available
  useEffect(() => {
    if (!bookmark || !selectRef.current || isLoadingTags) {
      return;
    }

    // Destroy existing instance if any
    if (tomSelectInstanceRef.current) {
      tomSelectInstanceRef.current.destroy();
      tomSelectInstanceRef.current = null;
    }

    // Set initial selected values (tag slugs)
    const selectedSlugs = bookmark.tags.map(tag => tag.slug);
    selectRef.current.innerHTML = '';
    selectedSlugs.forEach(slug => {
      const option = document.createElement('option');
      option.value = slug;
      option.selected = true;
      const tag = bookmark.tags.find(t => t.slug === slug);
      option.textContent = tag ? `${tag.icon ? `${tag.icon} ` : ''}${tag.name}` : slug;
      selectRef.current?.appendChild(option);
    });

    // Initialize tom-select
    const tomSelect = new TomSelect(selectRef.current, {
      plugins: ['remove_button'],
      onItemAdd: () => {
        // Close dropdown after selecting an item (but keep multiple selection enabled)
        setTimeout(() => {
          tomSelect.blur();
        }, 0);
      },
      create: async (input: string, callback: (item: { value: string; text: string } | null) => void) => {
        const tagName = input.trim();
        if (!tagName) {
          callback(null);
          return;
        }

        // Check if tag already exists (use ref to get current tags)
        const normalizedInput = tagName.toLowerCase();
        const existingTag = availableTagsRef.current.find(tag => tag.name.toLowerCase() === normalizedInput);
        if (existingTag) {
          callback({
            value: existingTag.slug,
            text: `${existingTag.icon ? `${existingTag.icon} ` : ''}${existingTag.name}`,
          });
          return;
        }

        try {
          // Create the tag via API
          const newTag = await createTag(tagName);
          // Add to available tags list and ref
          setAvailableTags(prev => {
            const updated = [...prev, newTag];
            availableTagsRef.current = updated;
            return updated;
          });
          // Create the option object
          const newOption = {
            value: newTag.slug,
            text: `${newTag.icon ? `${newTag.icon} ` : ''}${newTag.name}`,
          };
          // Add option to tom-select
          if (tomSelectInstanceRef.current) {
            tomSelectInstanceRef.current.addOption(newOption);
          }
          // Return the new option
          callback(newOption);
        } catch (err) {
          console.error('Failed to create tag:', err);
          callback(null);
        }
      },
      allowEmpty: true,
      maxItems: null,
      valueField: 'value',
      labelField: 'text',
      searchField: 'text',
      options: availableTags.map(tag => ({
        value: tag.slug,
        text: `${tag.icon ? `${tag.icon} ` : ''}${tag.name}`,
      })),
      render: {
        option: (data: any, escape: (str: string) => string) => {
          return `<div>${escape(data.text)}</div>`;
        },
        item: (data: any, escape: (str: string) => string) => {
          return `<div>${escape(data.text)}</div>`;
        },
      },
    });

    tomSelectInstanceRef.current = tomSelect;

    return () => {
      if (tomSelectInstanceRef.current) {
        tomSelectInstanceRef.current.destroy();
        tomSelectInstanceRef.current = null;
      }
    };
  }, [bookmark, availableTags, isLoadingTags]);

  // Show modal when bookmark changes
  useEffect(() => {
    if (bookmark) {
      setSaveError(null);
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
    if (!bookmark || !tomSelectInstanceRef.current) return;

    setIsSaving(true);
    setSaveError(null);

    try {
      // Get selected tag slugs from tom-select
      const selectedSlugs = tomSelectInstanceRef.current.getValue() as string[];

      // Update bookmark tags via API
      await updateBookmarkTags(bookmark.id, selectedSlugs);

      // Dispatch custom events to notify other components
      window.dispatchEvent(new CustomEvent('tagsUpdated'));
      window.dispatchEvent(new CustomEvent('bookmarksUpdated'));
      onSave();
      hideModal();
    } catch (err) {
      setSaveError(err instanceof Error ? err.message : 'Failed to update bookmark tags');
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div
      ref={modalRef}
      className="modal fade"
      id="editBookmarkTags"
      tabIndex={-1}
      aria-labelledby="editBookmarkTagsLabel"
      aria-hidden="true"
    >
      <div className="modal-dialog">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title" id="editBookmarkTagsLabel">
              Edit Tags
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
                <label htmlFor="bookmarkTagsSelect" className="form-label">
                  Tags
                </label>
                <select
                  ref={selectRef}
                  id="bookmarkTagsSelect"
                  className="form-select"
                  multiple
                  disabled={isSaving || isLoadingTags}
                ></select>
                {isLoadingTags && (
                  <div className="form-text">
                    <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    Loading tags...
                  </div>
                )}
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
                disabled={isSaving || isLoadingTags}
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

