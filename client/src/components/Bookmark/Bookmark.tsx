import { useState, useEffect } from 'react';
import { Icon } from '../Icon/Icon';
import { PlaceholderImage } from '../PlaceholderImage/PlaceholderImage';
import { EditBookmarkTags } from '../EditBookmarkTags/EditBookmarkTags';
import { EditBookmark } from '../EditBookmark/EditBookmark';
import { TagName } from '../TagName/TagName';
import type { Bookmark as BookmarkType } from '../../types';
import { LAYOUT_EMBEDDED } from '../../types';
import { findEmbed, findThumbnail } from '@shared';
import { formatDate } from '../../utils/date';
import { useBookmarkLogic } from './useBookmarkLogic';

interface BookmarkProps {
  bookmark: BookmarkType;
  layout: string;
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

export const Bookmark = ({
  bookmark,
  layout,
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

  const isEmbedded = layout === LAYOUT_EMBEDDED;
  const embedResult = isEmbedded ? findEmbed(bookmark.url) : null;
  const [embedLoaded, setEmbedLoaded] = useState(false);
  const [imageError, setImageError] = useState(false);
  const [isSmallImage, setIsSmallImage] = useState(false);
  const imageUrl = bookmark.mainImage?.contentUrl || findThumbnail(bookmark.url);

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

  const handleEmbedClick = () => {
    setEmbedLoaded(true);
  };

  const handleImageError = () => {
    setImageError(true);
  };

  const handleImageLoad = (e: React.SyntheticEvent<HTMLImageElement>) => {
    const img = e.currentTarget;
    if (img.naturalWidth <= 76 || img.naturalHeight <= 76) {
      setIsSmallImage(true);
    }
  };

  useEffect(() => {
    setIsSmallImage(false);
  }, [imageUrl]);

  // Determine background image style for normal bookmarks
  const normalBookmarkStyle = !isEmbedded && imageUrl && !imageError
    ? {
        backgroundImage: `url(${imageUrl})`,
        backgroundSize: isSmallImage ? 'auto' : 'cover',
      }
    : undefined;

  return (
    <div className={`col-12 my-2 ${!isEmbedded ? 'col-sm-6 col-md-4 col-xl-3 col-xxl-2' : ''}`}>
      <div id={`bookmark-${bookmark.id}`} className="card h-100">
        {isEmbedded && embedResult ? (
          <div className="card-img-top bookmark-img embed-img">
            {!embedLoaded ? (
              <div
                className={`${embedResult.type}-player embed-preview`}
                style={{
                  backgroundImage: (embedResult.thumbnailUrl || imageUrl)
                    ? `url(${embedResult.thumbnailUrl || imageUrl})`
                    : undefined,
                }}
                onClick={handleEmbedClick}
              >
                <div className="embed-action">
                  <Icon
                    name="play"
                    className="embed-action-icon"
                    width={64}
                    height={64}
                    style={{
                      color: 'white',
                      filter: 'drop-shadow(0 2px 4px rgba(0, 0, 0, 0.5))',
                    }}
                  />
                </div>
              </div>
            ) : (
              <iframe
                className={`${embedResult.type}-player`}
                src={embedResult.embedUrl}
                allowFullScreen
                allow="autoplay; fullscreen; picture-in-picture"
                style={{
                  width: '100%',
                  height: '100%',
                  border: 'none',
                }}
                title={`${embedResult.type} embed`}
              />
            )}
          </div>
        ) : isEmbedded && !embedResult ? (
          <div className="card-img-top bookmark-img embed-img" style={{ position: 'relative' }}>
            <PlaceholderImage type="no-embed" style={{ position: 'absolute', top: 0, left: 0, right: 0, bottom: 0 }} />
            <a target="_blank" className="d-block h-100 w-100" href={bookmark.url} rel="noopener noreferrer" style={{ position: 'relative', zIndex: 1 }}></a>
          </div>
        ) : (
          <div className="card-img-top bookmark-img flex-shrink-0" style={normalBookmarkStyle}>
            {imageUrl && !imageError && (
              <img
                src={imageUrl}
                alt=""
                onError={handleImageError}
                onLoad={handleImageLoad}
                style={{ display: 'none' }}
                aria-hidden="true"
              />
            )}
            {(!imageUrl || imageError) && (
              <PlaceholderImage
                type={imageError ? 'error-image' : 'no-image'}
                style={{ position: 'absolute', top: 0, left: 0, right: 0, bottom: 0 }}
              />
            )}
            <a target="_blank" className="d-block h-100 w-100" href={bookmark.url} rel="noopener noreferrer" style={{ position: 'relative', zIndex: 1 }}></a>
          </div>
        )}

        <div className="card-body position-relative">
          <div className="card-title">
            <a
              target="_blank"
              className="text-decoration-none bookmark-title"
              href={bookmark.url}
              rel="noopener noreferrer"
              title={bookmark.title}
            >
              <small
                className={`badge me-2 rounded-pill text-bg-light border fw-light ${isEmbedded ? '' : 'domain-pill'}`}
                title={bookmark.domain}
              >
                {bookmark.domain}
              </small>
              {bookmark.title}
            </a>
          </div>
          <div className={`${isEmbedded ? 'align-self-end pt-2' : 'pt-1'}`}>
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
        </div>
        <div className="card-footer text-body-secondary d-flex align-items-center py-1 pe-0">
          <div className="fs-small flex-grow-1">
            {formatDate(bookmark.createdAt)}
            {bookmark.isPublic && !isProfileMode && (
              <span className="text-success ms-1">✦</span>
            )}
          </div>
          <div>
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
                onClick={() => {}}
                aria-label="Add bookmark"
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
