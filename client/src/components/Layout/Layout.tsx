import { Outlet, useSearchParams, useNavigate, useLocation, useParams } from 'react-router-dom';
import { useState, useEffect } from 'react';
import { Sidebar } from '../Sidebar/Sidebar';
import { MeSection } from '../Sidebar/sections/MeSection';
import { ProfileSection } from '../Sidebar/sections/ProfileSection';
import { SocialSection } from '../Sidebar/sections/SocialSection';
import { SettingsSection } from '../Sidebar/sections/SettingsSection';
import { EditBookmarkSidebarSection } from '../Sidebar/sections/EditBookmarkSidebarSection';
import { getTags, deleteBookmark } from '../../services/api';
import { getPublicTags } from '../../services/publicApi';
import { useProfileContext } from '../../hooks/useProfileContext';
import { isAuthenticated } from '../../services/auth';
import { toggleTag, updateTagParams } from '../../utils/tags';
import type { Tag as TagType } from '../../types';

export const Layout = () => {
  const location = useLocation();
  const navigate = useNavigate();
  const params = useParams();
  const [searchParams, setSearchParams] = useSearchParams();
  const [tags, setTags] = useState<TagType[]>([]);
  const [isLoadingTags, setIsLoadingTags] = useState(true);
  const [isDeleting, setIsDeleting] = useState(false);

  // Detect if we're in profile mode
  const isProfileMode = location.pathname.startsWith('/social/') &&
    location.pathname !== '/social/timeline' &&
    !location.pathname.startsWith('/social/tag/') &&
    !location.pathname.startsWith('/social/instance/');
  const profileIdentifier = params.profileIdentifier;
  const profileContext = useProfileContext(isProfileMode && profileIdentifier ? profileIdentifier : '');

  // Route detection
  const isTagsPage = isProfileMode
    ? location.pathname.endsWith('/tags')
    : location.pathname === '/me/tags';
  const isTimelinePage = !isProfileMode && location.pathname === '/social/timeline';
  const isSocialTagPage = !isProfileMode && location.pathname.startsWith('/social/tag/');
  const isInstancePage = !isProfileMode && (location.pathname === '/social/instance/this' || location.pathname === '/social/instance/other');
  const bookmarkMatch = location.pathname.match(/^\/(?:me|social\/[^/]+)\/bookmarks\/([^/]+)$/);
  const isBookmarkPage = !!bookmarkMatch;
  const bookmarkId = bookmarkMatch ? bookmarkMatch[1] : null;

  // Tag state
  const tagQueryString = searchParams.get('tags') || '';
  const selectedTagSlugs = tagQueryString ? tagQueryString.split(',').filter(Boolean) : [];

  useEffect(() => {
    const loadTags = async () => {
      setIsLoadingTags(true);
      try {
        if (isProfileMode && profileContext.baseUrl && profileContext.username) {
          // Load public tags for profile
          const tagsData = await getPublicTags(profileContext.baseUrl, profileContext.username);
          setTags(tagsData);
        } else if (!isProfileMode) {
          // Load user's own tags (only if authenticated)
          if (isAuthenticated()) {
            const tagsData = await getTags();
            setTags(tagsData);
          } else {
            setTags([]);
          }
        }
      } catch (err: unknown) {
        console.error('Failed to load tags:', err);
        setTags([]);
      } finally {
        setIsLoadingTags(false);
      }
    };

    if (!isProfileMode || (isProfileMode && profileContext.baseUrl && profileContext.username && !profileContext.isLoading)) {
      loadTags();
    }

    // Listen for tag update events to reload tags in sidebar (only for own tags)
    if (!isProfileMode && isAuthenticated()) {
      const handleTagsUpdated = () => {
        loadTags();
      };

      window.addEventListener('tagsUpdated', handleTagsUpdated);
      return () => {
        window.removeEventListener('tagsUpdated', handleTagsUpdated);
      };
    }
  }, [isProfileMode, profileContext.baseUrl, profileContext.username, profileContext.isLoading]);

  // Navigation handlers
  const handleTagToggle = (slug: string) => {
    const newSelectedSlugs = toggleTag(slug, selectedTagSlugs);
    const newParams = updateTagParams(newSelectedSlugs, searchParams);

    if (isProfileMode) {
      if (isTagsPage) {
        navigate(`/social/${profileIdentifier}?${newParams.toString()}`);
      } else {
        setSearchParams(newParams);
      }
    } else {
      if (isTagsPage) {
        navigate(`/me?${newParams.toString()}`);
      } else {
        setSearchParams(newParams);
      }
    }
  };

  const handleNavigateBack = () => {
    const params = updateTagParams(selectedTagSlugs, new URLSearchParams());
    // Preserve search query when navigating back
    const searchQuery = searchParams.get('search') || '';
    if (searchQuery.trim()) {
      params.set('search', searchQuery);
    }
    if (isProfileMode) {
      navigate(`/social/${profileIdentifier}?${params.toString()}`);
    } else {
      navigate(`/me?${params.toString()}`);
    }
  };

  const handleClearTags = () => {
    const params = updateTagParams([], searchParams);
    if (isTagsPage || isTimelinePage || isSocialTagPage || isInstancePage) {
      // If on tags page, timeline page, social tag page, or instance page, navigate back to home page
      if (isProfileMode) {
        navigate(`/social/${profileIdentifier}?${params.toString()}`);
      } else {
        navigate(`/me?${params.toString()}`);
      }
    } else {
      setSearchParams(params);
    }
  };

  const handleNavigateToTags = () => {
    const params = updateTagParams(selectedTagSlugs, new URLSearchParams());
    if (isProfileMode) {
      navigate(`/social/${profileIdentifier}/tags${params.toString() ? `?${params.toString()}` : ''}`);
    } else {
      navigate(`/me/tags${params.toString() ? `?${params.toString()}` : ''}`);
    }
  };

  const handleNavigateToEdit = () => {
    if (bookmarkId) {
      const params = updateTagParams(selectedTagSlugs, searchParams);
      params.set('edit', 'true');
      setSearchParams(params);
    }
  };

  const handleDeleteBookmark = async () => {
    if (!bookmarkId || isProfileMode) return; // Don't allow deletion in profile mode

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

  if (isLoadingTags || (isProfileMode && profileContext.isLoading)) {
    // Don't render sections while loading tags or profile context
  } else if (isProfileMode) {
    // Profile mode: always show ProfileSection with profile name as title
    sections.push(
      <ProfileSection
        key="profile"
        profileUsername={profileContext.username || profileIdentifier || ''}
        tags={tags}
        selectedTagSlugs={selectedTagSlugs}
        onTagToggle={handleTagToggle}
        onNavigateToTags={handleNavigateToTags}
        onClearTags={handleClearTags}
        onNavigateBack={isBookmarkPage ? handleNavigateBack : undefined}
        isBookmarkPage={isBookmarkPage}
        isTagsPage={isTagsPage}
      />
    );
  } else if (isBookmarkPage) {
    // Own bookmarks: show full edit section
    sections.push(
      <EditBookmarkSidebarSection
        key="bookmark"
        onNavigateBack={handleNavigateBack}
        onNavigateToEdit={handleNavigateToEdit}
        onDeleteBookmark={handleDeleteBookmark}
        isDeleting={isDeleting}
      />
    );
  } else {
    // Own profile home page or tags page: show all sections
    // Keep MeSection visible even on tags page
    sections.push(
      <MeSection
        key="me"
        tags={tags}
        selectedTagSlugs={selectedTagSlugs}
        onTagToggle={handleTagToggle}
        onNavigateToTags={handleNavigateToTags}
        onClearTags={handleClearTags}
        isTagsPage={isTagsPage}
      />
    );
    sections.push(<SocialSection key="social" />);
    sections.push(<SettingsSection key="settings" />);
  }

  return (
    <>
      <nav className="navbar navbar-expand-md navbar-dark fixed-top bg-primary navbar-height">
        <div className="container-fluid">
          <button
            className="text-white navbar-brand border-0 bg-transparent p-0"
            style={{ cursor: 'pointer' }}
            onClick={(e) => {
              e.preventDefault();
              window.dispatchEvent(new Event('refreshCurrentPage'));
            }}
          >
            HiveCache
          </button>
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
              <h5
                className="offcanvas-title"
                id="offcanvasResponsiveLabel"
                style={{ cursor: 'pointer' }}
                onClick={() => {
                  window.dispatchEvent(new Event('refreshCurrentPage'));
                }}
              >
                HiveCache
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

