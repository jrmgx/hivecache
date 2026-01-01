import { useState, useEffect, useRef, useCallback } from 'react';
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.bootstrap5.css';
import { ErrorAlert } from '../ErrorAlert/ErrorAlert';
import { updateBookmarkTags, getTags, createTag, ApiError } from '../../services/api';
import type { Bookmark as BookmarkType, Tag as TagType } from '../../types';

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
  const [saveErrorStatus, setSaveErrorStatus] = useState<number | null>(null);
  const [isLoadingTags, setIsLoadingTags] = useState(false);
  const availableTagsRef = useRef<TagType[]>([]);
  const initializedBookmarkIdRef = useRef<string | null>(null);

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
    // Destroy tom-select instance when closing
    if (tomSelectInstanceRef.current) {
      tomSelectInstanceRef.current.destroy();
      tomSelectInstanceRef.current = null;
    }
    initializedBookmarkIdRef.current = null;
    onClose();
  }, [onClose]);

  // Load available tags and initialize tom-select
  useEffect(() => {
    if (!bookmark) {
      return;
    }

    const loadTagsAndInit = async () => {
      setIsLoadingTags(true);
      try {
        const tags = await getTags();
        availableTagsRef.current = tags;

        // Now initialize tom-select after tags are loaded
        if (!selectRef.current) {
          return;
        }

        // Only recreate if bookmark changed, not if tags were added
        const bookmarkChanged = initializedBookmarkIdRef.current !== bookmark.id;
        if (!bookmarkChanged && tomSelectInstanceRef.current) {
          // Instance already exists for this bookmark, skip recreation
          setIsLoadingTags(false);
          return;
        }

        // Destroy existing instance if any
        if (tomSelectInstanceRef.current) {
          tomSelectInstanceRef.current.destroy();
          tomSelectInstanceRef.current = null;
        }

        // Set initial selected values (tag slugs)
        const selectedSlugs = bookmark.tags.map((tag: TagType) => tag.slug);
        selectRef.current.innerHTML = '';
        selectedSlugs.forEach((slug: string) => {
          const option = document.createElement('option');
          option.value = slug;
          option.selected = true;
          const tag = bookmark.tags.find((t: TagType) => t.slug === slug);
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
          create: (async (input: string, callback: (item?: { value: string; text: string }) => void) => {
            const tagName = input.trim();
            if (!tagName) {
              callback(undefined);
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
              // Add to available tags ref
              availableTagsRef.current = [...availableTagsRef.current, newTag];
              // Create the option object
              const newOption = {
                value: newTag.slug,
                text: `${newTag.icon ? `${newTag.icon} ` : ''}${newTag.name}`,
              };
              // Add option to tom-select first (use the tomSelect instance from closure)
              tomSelect.addOption(newOption, true);
              // Call callback with the option - tom-select will automatically add it to selected values
              callback(newOption);
            } catch (err: unknown) {
              console.error('Failed to create tag:', err);
              callback(undefined);
            }
          }) as any,
          maxItems: null,
          valueField: 'value',
          labelField: 'text',
          searchField: ['text'],
          options: tags.map(tag => ({
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
        initializedBookmarkIdRef.current = bookmark.id;
      } catch (err: unknown) {
        console.error('Failed to load tags:', err);
        availableTagsRef.current = [];
      } finally {
        setIsLoadingTags(false);
      }
    };

    loadTagsAndInit();

    return () => {
      if (tomSelectInstanceRef.current) {
        tomSelectInstanceRef.current.destroy();
        tomSelectInstanceRef.current = null;
      }
      initializedBookmarkIdRef.current = null;
    };
  }, [bookmark?.id]);

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
    if (!bookmark || !tomSelectInstanceRef.current) return;

    setIsSaving(true);
    setSaveError(null);
    setSaveErrorStatus(null);

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
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to update bookmark tags';
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
              <ErrorAlert error={saveError} statusCode={saveErrorStatus} />

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

