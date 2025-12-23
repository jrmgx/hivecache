import { Outlet, useSearchParams, useNavigate, useLocation } from 'react-router-dom';
import { useState, useEffect, useRef } from 'react';
import { TagList } from '../TagList/TagList';
import { getTags } from '../../services/api';
import { toggleTag, updateTagParams } from '../../utils/tags';
import type { Tag as TagType } from '../../types';

declare global {
  interface Window {
    bootstrap?: {
      Offcanvas: {
        getInstance: (element: HTMLElement | string) => { hide: () => void } | null;
      };
    };
  }
}

export const Layout = () => {
  const location = useLocation();
  const isTagsPage = location.pathname === '/tags';
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();
  const [tags, setTags] = useState<TagType[]>([]);
  const [isLoadingTags, setIsLoadingTags] = useState(true);
  const offcanvasRef = useRef<HTMLDivElement>(null);

  const closeOffcanvas = () => {
    if (offcanvasRef.current && window.bootstrap) {
      const offcanvasInstance = window.bootstrap.Offcanvas.getInstance(offcanvasRef.current);
      if (offcanvasInstance) {
        offcanvasInstance.hide();
      }
    }
  };

  const tagQueryString = searchParams.get('tags') || '';
  const selectedTagSlugs = tagQueryString ? tagQueryString.split(',').filter(Boolean) : [];
  const pinnedTags = tags.filter((tag) => tag.pinned);

  useEffect(() => {
    const loadTags = async () => {
      setIsLoadingTags(true);
      try {
        const tagsData = await getTags();
        setTags(tagsData);
      } catch (err) {
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

  const sidebarButton = isTagsPage ? (
    <div className="d-flex align-items-center mb-2 position-relative">
      <button
        className="btn btn-secondary text-start flex-grow-1"
        onClick={() => {
          const params = updateTagParams(selectedTagSlugs, new URLSearchParams());
          closeOffcanvas();
          navigate(`/?${params.toString()}`);
        }}
      >
        Back
      </button>
    </div>
  ) : (
    <div className="d-flex align-items-center mb-2 position-relative">
      <button
        className="btn btn-outline-secondary text-start flex-grow-1"
        onClick={() => {
          const params = updateTagParams(selectedTagSlugs, new URLSearchParams());
          closeOffcanvas();
          navigate(`/tags${params.toString() ? `?${params.toString()}` : ''}`);
        }}
      >
        Show all tags{pinnedTags.length === 0 ? <><br />and choose favorite</> : null}
      </button>
    </div>
  );

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
                    {!isLoadingTags && (
                      <TagList
                        tags={tags}
                        selectedTagSlugs={selectedTagSlugs}
                        pinnedTags={pinnedTags}
                        onTagToggle={handleTagToggle}
                      >
                        {sidebarButton}
                      </TagList>
                    )}

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

