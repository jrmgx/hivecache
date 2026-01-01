import { Outlet, useSearchParams, useNavigate, useLocation } from 'react-router-dom';
import { useState, useEffect, useRef } from 'react';
import { TagList } from '../TagList/TagList';
import { Icon } from '../Icon/Icon';
import { getTags, deleteBookmark } from '../../services/api';
import { toggleTag, updateTagParams } from '../../utils/tags';
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

export const Layout = () => {
  const location = useLocation();
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const [tags, setTags] = useState<TagType[]>([]);
  const [isLoadingTags, setIsLoadingTags] = useState(true);
  const [isDeleting, setIsDeleting] = useState(false);
  const offcanvasRef = useRef<HTMLDivElement>(null);

  // Route detection
  const isTagsPage = location.pathname === '/tags';
  const bookmarkMatch = location.pathname.match(/^\/bookmarks\/([^/]+)$/);
  const isBookmarkPage = !!bookmarkMatch;
  const bookmarkId = bookmarkMatch ? bookmarkMatch[1] : null;

  // Tag state
  const tagQueryString = searchParams.get('tags') || '';
  const selectedTagSlugs = tagQueryString ? tagQueryString.split(',').filter(Boolean) : [];
  const pinnedTags = tags.filter((tag) => tag.pinned);

  const closeOffcanvas = () => {
    if (offcanvasRef.current && window.bootstrap) {
      const offcanvasInstance = window.bootstrap.Offcanvas.getInstance(offcanvasRef.current);
      if (offcanvasInstance) {
        offcanvasInstance.hide();
      }
    }
  };

  useEffect(() => {
    const loadTags = async () => {
      setIsLoadingTags(true);
      try {
        const tagsData = await getTags();
        setTags(tagsData);
      } catch (err: unknown) {
        console.error('Failed to load tags:', err);
        setTags([]);
      } finally {
        setIsLoadingTags(false);
      }
    };

    loadTags();

    // Listen for tag update events to reload tags in sidebar
    const handleTagsUpdated = () => {
      loadTags();
    };

    window.addEventListener('tagsUpdated', handleTagsUpdated);
    return () => {
      window.removeEventListener('tagsUpdated', handleTagsUpdated);
    };
  }, []);

  // Navigation handlers
  const handleTagToggle = (slug: string) => {
    const newSelectedSlugs = toggleTag(slug, selectedTagSlugs);
    const newParams = updateTagParams(newSelectedSlugs, searchParams);
    closeOffcanvas();

    if (isTagsPage) {
      navigate(`/?${newParams.toString()}`);
    } else {
      setSearchParams(newParams);
    }
  };

  const handleNavigateBack = () => {
    const params = updateTagParams(selectedTagSlugs, new URLSearchParams());
    closeOffcanvas();
    navigate(`/?${params.toString()}`);
  };

  const handleNavigateToHome = () => {
    const params = updateTagParams(selectedTagSlugs, new URLSearchParams());
    closeOffcanvas();
    navigate(`/?${params.toString()}`);
  };

  const handleNavigateToTags = () => {
    const params = updateTagParams(selectedTagSlugs, new URLSearchParams());
    closeOffcanvas();
    navigate(`/tags${params.toString() ? `?${params.toString()}` : ''}`);
  };

  const handleNavigateToEdit = () => {
    closeOffcanvas();
    if (bookmarkId) {
      const params = updateTagParams(selectedTagSlugs, searchParams);
      params.set('edit', 'true');
      setSearchParams(params);
    }
  };

  const handleDeleteBookmark = async () => {
    if (!bookmarkId) return;

    const confirmed = window.confirm('Are you sure you want to delete this bookmark? This action cannot be undone.');
    if (!confirmed) return;

    setIsDeleting(true);
    try {
      await deleteBookmark(bookmarkId);
      closeOffcanvas();
      window.dispatchEvent(new Event('bookmarksUpdated'));
      const params = updateTagParams(selectedTagSlugs, new URLSearchParams());
      navigate(`/?${params.toString()}`);
    } catch (err) {
      console.error('Failed to delete bookmark:', err);
      alert('Failed to delete bookmark. Please try again.');
    } finally {
      setIsDeleting(false);
    }
  };

  // Sidebar content renderers
  const renderBookmarkSidebar = () => (
    <div>
      <div className="mb-2 fw-bold">Bookmark</div>
      <SidebarButton icon="arrow-left" label="Back" onClick={handleNavigateBack} />
      <SidebarButton icon="pencil" label="Edit" onClick={handleNavigateToEdit} />
      <SidebarButton
        icon="trash"
        label={isDeleting ? 'Deleting...' : 'Delete'}
        onClick={handleDeleteBookmark}
        disabled={isDeleting}
      />
    </div>
  );

  const renderTagsSidebar = () => (
    <div>
      <div className="mb-2 fw-bold">All Tags</div>
      <SidebarButton icon="arrow-left" label="Back" onClick={handleNavigateToHome} />
    </div>
  );

  const renderHomeSidebar = () => (
    <TagList
      tags={tags}
      selectedTagSlugs={selectedTagSlugs}
      pinnedTags={pinnedTags}
      onTagToggle={handleTagToggle}
    >
      <div className="d-flex align-items-center mb-2 position-relative">
        <button
          className="btn btn-outline-secondary border-0 text-start flex-grow-1"
          onClick={handleNavigateToTags}
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

  return (
    <>
      <nav className="navbar navbar-expand-md navbar-dark fixed-top bg-primary navbar-height">
        <div className="container-fluid">
          <a className="text-white navbar-brand" href="/">
            BookmarkHive
          </a>
          <button
            className="navbar-toggler bookmark-navbar-toggler"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#offcanvasResponsive"
            aria-controls="offcanvasResponsive"
            aria-expanded="false"
            aria-label="Toggle navigation"
          >
            <span className="text-white navbar-toggler-icon"></span>
          </button>
        </div>
      </nav>
      <main className="d-flex navbar-height-compensate h-100">
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
          </div>
        </div>
        <div className="container-fluid sidebar-left">
          <Outlet />
        </div>
      </main>
    </>
  );
};

