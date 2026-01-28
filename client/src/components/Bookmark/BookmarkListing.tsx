import { Icon } from '../Icon/Icon';
import { EditBookmarkTags } from '../EditBookmarkTags/EditBookmarkTags';
import { EditBookmark } from '../EditBookmark/EditBookmark';
import { TagName } from '../TagName/TagName';
import type { Bookmark as BookmarkType } from '../../types';
import { formatDate } from '../../utils/date';
import { useBookmarkLogic } from './useBookmarkLogic';

interface BookmarkProps {
  bookmark: BookmarkType;
  selectedTagSlugs: string[];
  onTagToggle?: (slug: string) => void;
  onShow?: (id: string) => void;
  onTagsSave?: () => void;
  showEditModal?: boolean;
  onEditSave?: (updatedBookmark: BookmarkType) => void;
  onEditClose?: () => void;
  hideShowButton?: boolean;
  hideAddTagButton?: boolean;
  isProfileMode?: boolean;
}

export const BookmarkListing = ({
  bookmark,
  selectedTagSlugs,
  onTagToggle,
  onShow,
  onTagsSave,
  showEditModal,
  onEditSave,
  onEditClose,
  hideShowButton,
  hideAddTagButton,
  isProfileMode = false
}: BookmarkProps) => {
  const {
    sortedTags,
    showEditTagsModal,
    handleTagClick,
    handleShow,
    handleShare,
    handleEditTags,
    handleTagsSave,
    handleTagsClose,
  } = useBookmarkLogic({
    bookmark,
    onTagToggle,
    onShow,
    onTagsSave,
  });

  const handleRecapture = (e: React.MouseEvent) => {
    e.preventDefault();
    // Placeholder for future recapture implementation
  };

  return (
    <div className="my-2">
      <div id={`bookmark-${bookmark.id}`} className="card">
        <div className="card-body card-body-tight">
          <div className="d-flex justify-content-between align-items-center mb-1">
            <div className="flex-grow-1">
              <a
                target="_blank"
                className="text-decoration-none bookmark-title-nop"
                href={bookmark.url}
                rel="noopener noreferrer"
                title={bookmark.title}
              >
                {bookmark.title}
                <small
                  style={{"position": "relative", "top": "-2px"}}
                  className="badge ms-2 rounded-pill text-bg-light border fw-light"
                  title={bookmark.domain}
                >
                  {bookmark.domain}
                </small>
              </a>
            </div>
            <div className="fs-small text-body-secondary ms-2">
              {formatDate(bookmark.createdAt)}
              {bookmark.isPublic && !isProfileMode && (
                <span className="text-success ms-1">✦</span>
              )}
            </div>
          </div>

          <div className="d-flex justify-content-between align-items-center">
            <div className="flex-grow-1">
              {sortedTags.map((tag) => {
                const isSelected = selectedTagSlugs.includes(tag.slug);
                return (
                  <button
                    key={tag.slug}
                    type="button"
                    className={`btn btn-outline-secondary btn-xs me-1 mb-1 ${isSelected ? 'active' : ''}`}
                    onClick={() => handleTagClick(tag.slug)}
                  >
                    <TagName tag={tag} showPublicIndicator={!isProfileMode} />
                  </button>
                );
              })}
              {!hideAddTagButton && (
                <button
                  className="btn btn-outline-primary btn-xs mb-1"
                  type="button"
                  onClick={handleEditTags}
                  aria-label="Edit tags"
                >
                  #
                </button>
              )}
            </div>
            <div className="ms-2">
              {!hideShowButton && (
                <button
                  className="btn btn-outline-secondary border-0"
                  onClick={handleShow}
                  aria-label="Show bookmark"
                >
                  <Icon name="eye" />
                </button>
              )}
              {isProfileMode && (
                <button
                  className="btn btn-outline-secondary border-0"
                  onClick={handleRecapture}
                  aria-label="Recapture bookmark"
                >
                  <Icon name="save" />
                </button>
              )}
              <button
                className="btn btn-outline-secondary border-0"
                onClick={handleShare}
                aria-label="Share bookmark"
              >
                <Icon name="share-fat" />
              </button>
            </div>
          </div>
        </div>
      </div>
      <EditBookmarkTags
        bookmark={showEditTagsModal ? bookmark : null}
        onSave={handleTagsSave}
        onClose={handleTagsClose}
      />
      {showEditModal && onEditSave && onEditClose && (
        <EditBookmark
          bookmark={bookmark}
          onSave={onEditSave}
          onClose={onEditClose}
        />
      )}
    </div>
  );
};
