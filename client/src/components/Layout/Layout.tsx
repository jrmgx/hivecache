import { Outlet, useSearchParams, useNavigate, useLocation } from 'react-router-dom';
import { useState, useEffect } from 'react';
import { Sidebar } from '../Sidebar/Sidebar';
import { MeSection } from '../Sidebar/sections/MeSection';
// import { SocialSection } from '../Sidebar/sections/SocialSection';
// import { SettingsSection } from '../Sidebar/sections/SettingsSection';
import { EditBookmarkSidebarSection } from '../Sidebar/sections/EditBookmarkSidebarSection';
import { TagListSidebarSection } from '../Sidebar/sections/TagListSidebarSection';
import { getTags, deleteBookmark } from '../../services/api';
import { toggleTag, updateTagParams } from '../../utils/tags';
import type { Tag as TagType } from '../../types';

export const Layout = () => {
  const location = useLocation();
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const [tags, setTags] = useState<TagType[]>([]);
  const [isLoadingTags, setIsLoadingTags] = useState(true);
  const [isDeleting, setIsDeleting] = useState(false);

  // Route detection
  const isTagsPage = location.pathname === '/me/tags';
  const bookmarkMatch = location.pathname.match(/^\/me\/bookmarks\/([^/]+)$/);
  const isBookmarkPage = !!bookmarkMatch;
  const bookmarkId = bookmarkMatch ? bookmarkMatch[1] : null;

  // Tag state
  const tagQueryString = searchParams.get('tags') || '';
  const selectedTagSlugs = tagQueryString ? tagQueryString.split(',').filter(Boolean) : [];

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

    if (isTagsPage) {
      navigate(`/me?${newParams.toString()}`);
    } else {
      setSearchParams(newParams);
    }
  };

  const handleNavigateBack = () => {
    const params = updateTagParams(selectedTagSlugs, new URLSearchParams());
    navigate(`/me?${params.toString()}`);
  };

  const handleNavigateToHome = () => {
    const params = updateTagParams(selectedTagSlugs, new URLSearchParams());
    navigate(`/me?${params.toString()}`);
  };

  const handleClearTags = () => {
    const params = updateTagParams([], searchParams);
    setSearchParams(params);
  };

  const handleNavigateToTags = () => {
    const params = updateTagParams(selectedTagSlugs, new URLSearchParams());
    navigate(`/me/tags${params.toString() ? `?${params.toString()}` : ''}`);
  };

  const handleNavigateToEdit = () => {
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
      window.dispatchEvent(new Event('bookmarksUpdated'));
      const params = updateTagParams(selectedTagSlugs, new URLSearchParams());
      navigate(`/me?${params.toString()}`);
    } catch (err) {
      console.error('Failed to delete bookmark:', err);
      alert('Failed to delete bookmark. Please try again.');
    } finally {
      setIsDeleting(false);
    }
  };

  // Determine which sections to show based on route
  const sections: React.ReactNode[] = [];

  if (isLoadingTags) {
    // Don't render sections while loading tags
  } else if (isBookmarkPage) {
    sections.push(
      <EditBookmarkSidebarSection
        key="bookmark"
        onNavigateBack={handleNavigateBack}
        onNavigateToEdit={handleNavigateToEdit}
        onDeleteBookmark={handleDeleteBookmark}
        isDeleting={isDeleting}
      />
    );
  } else if (isTagsPage) {
    sections.push(
      <TagListSidebarSection
        key="tags"
        onNavigateToHome={handleNavigateToHome}
      />
    );
  } else {
    // Home page: show main sections
    sections.push(
      <MeSection
        key="me"
        tags={tags}
        selectedTagSlugs={selectedTagSlugs}
        onTagToggle={handleTagToggle}
        onNavigateToTags={handleNavigateToTags}
        onClearTags={handleClearTags}
      />
    );
    //sections.push(<SocialSection key="social" />);
    //sections.push(<SettingsSection key="settings" />);
  }

  return (
    <>
      <nav className="navbar navbar-expand-md navbar-dark fixed-top bg-primary navbar-height">
        <div className="container-fluid">
          <a className="text-white navbar-brand" href="/me">
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
              <Sidebar sections={sections} />
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

