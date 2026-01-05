import { Outlet, useSearchParams, useNavigate, useLocation } from 'react-router-dom';
import { useState, useEffect } from 'react';
import { Sidebar } from '../Sidebar/Sidebar';
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
  const isTagsPage = location.pathname === '/tags';
  const bookmarkMatch = location.pathname.match(/^\/bookmarks\/([^/]+)$/);
  const isBookmarkPage = !!bookmarkMatch;
  const bookmarkId = bookmarkMatch ? bookmarkMatch[1] : null;
  const isHomePage = !isTagsPage && !isBookmarkPage;

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
      navigate(`/?${newParams.toString()}`);
    } else {
      setSearchParams(newParams);
    }
  };

  const handleNavigateBack = () => {
    const params = updateTagParams(selectedTagSlugs, new URLSearchParams());
    navigate(`/?${params.toString()}`);
  };

  const handleNavigateToHome = () => {
    const params = updateTagParams(selectedTagSlugs, new URLSearchParams());
    navigate(`/?${params.toString()}`);
  };

  const handleNavigateToTags = () => {
    const params = updateTagParams(selectedTagSlugs, new URLSearchParams());
    navigate(`/tags${params.toString() ? `?${params.toString()}` : ''}`);
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
      navigate(`/?${params.toString()}`);
    } catch (err) {
      console.error('Failed to delete bookmark:', err);
      alert('Failed to delete bookmark. Please try again.');
    } finally {
      setIsDeleting(false);
    }
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
        <Sidebar
          tags={tags}
          isLoadingTags={isLoadingTags}
          selectedTagSlugs={selectedTagSlugs}
          onTagToggle={handleTagToggle}
          onNavigateBack={handleNavigateBack}
          onNavigateToHome={handleNavigateToHome}
          onNavigateToTags={handleNavigateToTags}
          onNavigateToEdit={handleNavigateToEdit}
          onDeleteBookmark={handleDeleteBookmark}
          isDeleting={isDeleting}
          bookmarkId={bookmarkId}
          isBookmarkPage={isBookmarkPage}
          isTagsPage={isTagsPage}
          isHomePage={isHomePage}
        />
        <div className="container-fluid sidebar-left">
          <Outlet />
        </div>
      </main>
    </>
  );
};

