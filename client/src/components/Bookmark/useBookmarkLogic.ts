import { useState } from 'react';
import type { Bookmark as BookmarkType } from '../../types';
import { shareBookmark } from '../../utils/share';

interface UseBookmarkLogicProps {
  bookmark: BookmarkType;
  onTagToggle?: (slug: string) => void;
  onShow?: (id: string) => void;
  onTagsSave?: () => void;
}

export const useBookmarkLogic = ({
  bookmark,
  onTagToggle,
  onShow,
  onTagsSave,
}: UseBookmarkLogicProps) => {
  const [showEditTagsModal, setShowEditTagsModal] = useState(false);

  const handleTagClick = (slug: string) => {
    if (onTagToggle) {
      onTagToggle(slug);
    }
  };

  const handleShow = (e: React.MouseEvent) => {
    e.preventDefault();
    if (onShow) {
      onShow(bookmark.id);
    }
  };

  const handleShare = (e: React.MouseEvent) => {
    e.preventDefault();
    shareBookmark(bookmark);
  };

  const handleEditTags = (e: React.MouseEvent) => {
    e.preventDefault();
    setShowEditTagsModal(true);
  };

  const handleTagsSave = () => {
    setShowEditTagsModal(false);
    if (onTagsSave) {
      onTagsSave();
    }
  };

  const handleTagsClose = () => {
    setShowEditTagsModal(false);
  };

  const sortedTags = (Array.isArray(bookmark.tags) ? bookmark.tags : []).sort((a, b) => {
    return a.name.localeCompare(b.name, undefined, { sensitivity: 'base' });
  });

  return {
    sortedTags,
    showEditTagsModal,
    handleTagClick,
    handleShow,
    handleShare,
    handleEditTags,
    handleTagsSave,
    handleTagsClose,
  };
};
