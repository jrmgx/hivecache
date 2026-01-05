import { useRef } from 'react';
import { TagList } from '../TagList/TagList';
import { Icon } from '../Icon/Icon';
import type { Tag as TagType } from '../../types';

interface SidebarButtonProps {
  icon?: 'pencil' | 'trash' | 'arrow-left';
  label: string;
  onClick: () => void;
  disabled?: boolean;
}

const SidebarButton = ({ icon, label, onClick, disabled }: SidebarButtonProps) => (
  <div className="d-flex align-items-center position-relative flex-grow-1 mb-2">
    <button
      type="button"
      className="btn btn-outline-secondary border-0 text-start flex-grow-1"
      onClick={onClick}
      disabled={disabled}
    >
      {icon && <Icon name={icon} className="me-2" />}
      {label}
    </button>
  </div>
);

interface SidebarProps {
  tags: TagType[];
  isLoadingTags?: boolean;
  selectedTagSlugs: string[];
  onTagToggle?: (slug: string) => void;
  onNavigateBack?: () => void;
  onNavigateToHome?: () => void;
  onNavigateToTags?: () => void;
  onNavigateToEdit?: () => void;
  onDeleteBookmark?: () => void;
  isDeleting?: boolean;
  bookmarkId?: string | null;
  isBookmarkPage?: boolean;
  isTagsPage?: boolean;
  isHomePage?: boolean;
  standalone?: boolean;
}

export const Sidebar = ({
  tags,
  isLoadingTags = false,
  selectedTagSlugs,
  onTagToggle,
  onNavigateBack,
  onNavigateToHome,
  onNavigateToTags,
  onNavigateToEdit,
  onDeleteBookmark,
  isDeleting = false,
  isBookmarkPage = false,
  isTagsPage = false,
  standalone = false,
}: SidebarProps) => {
  const offcanvasRef = useRef<HTMLDivElement>(null);
  const pinnedTags = tags.filter((tag) => tag.pinned);

  const closeOffcanvas = () => {
    if (offcanvasRef.current && window.bootstrap) {
      const offcanvasInstance = window.bootstrap.Offcanvas.getInstance(offcanvasRef.current);
      if (offcanvasInstance) {
        offcanvasInstance.hide();
      }
    }
  };

  // Sidebar content renderers
  const renderBookmarkSidebar = () => (
    <div>
      <div className="mb-2 fw-bold">Bookmark</div>
      {onNavigateBack && (
        <SidebarButton icon="arrow-left" label="Back" onClick={() => { closeOffcanvas(); onNavigateBack(); }} />
      )}
      {onNavigateToEdit && (
        <SidebarButton icon="pencil" label="Edit" onClick={() => { closeOffcanvas(); onNavigateToEdit(); }} />
      )}
      {onDeleteBookmark && (
        <SidebarButton
          icon="trash"
          label={isDeleting ? 'Deleting...' : 'Delete'}
          onClick={() => { closeOffcanvas(); onDeleteBookmark(); }}
          disabled={isDeleting}
        />
      )}
    </div>
  );

  const renderTagsSidebar = () => (
    <div>
      <div className="mb-2 fw-bold">All Tags</div>
      {onNavigateToHome && (
        <SidebarButton icon="arrow-left" label="Back" onClick={() => { closeOffcanvas(); onNavigateToHome(); }} />
      )}
    </div>
  );

  const renderHomeSidebar = () => (
    <TagList
      tags={tags}
      selectedTagSlugs={selectedTagSlugs}
      pinnedTags={pinnedTags}
      onTagToggle={onTagToggle}
    >
      <div className="d-flex align-items-center mb-2 position-relative">
        <button
          className="btn btn-outline-secondary border-0 text-start flex-grow-1"
          onClick={() => { closeOffcanvas(); onNavigateToTags?.(); }}
        >
          Show all tags{pinnedTags.length === 0 ? <><br />and choose favorite</> : null}
        </button>
      </div>
    </TagList>
  );

  const renderSidebarContent = () => {
    if (isLoadingTags) return null;

    if (isBookmarkPage) return renderBookmarkSidebar();
    if (isTagsPage) return renderTagsSidebar();
    return renderHomeSidebar();
  };

  const sidebarContent = (
    <div className="offcanvas-body h-100 sidebar">
      <div className="container-fluid">
        <div className="row">
          <div className="col mt-3">
            {renderSidebarContent()}
            <div className="sidebar-spacer"></div>
          </div>
        </div>
      </div>
    </div>
  );

  if (standalone) {
    return (
      <div className="h-100">
        <div className="offcanvas-md offcanvas-start h-100 show" style={{ visibility: 'visible', transform: 'none' }}>
          <div className="offcanvas-header">
            <h5 className="offcanvas-title">BookmarkHive</h5>
          </div>
          {sidebarContent}
        </div>
      </div>
    );
  }

  return (
    <div className="h-100">
      <div
        ref={offcanvasRef}
        className="offcanvas-md offcanvas-start h-100"
        tabIndex={-1}
        id="offcanvasResponsive"
        aria-labelledby="offcanvasResponsiveLabel"
      >
        <div className="offcanvas-header">
          <h5 className="offcanvas-title" id="offcanvasResponsiveLabel">
            BookmarkHive
          </h5>
          <button
            type="button"
            className="btn-close"
            data-bs-dismiss="offcanvas"
            data-bs-target="#offcanvasResponsive"
            aria-label="Close"
          ></button>
        </div>
        {sidebarContent}
      </div>
    </div>
  );
};

