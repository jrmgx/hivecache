import { useState, useEffect, useRef } from 'react';
import { useParams, useSearchParams, useNavigate } from 'react-router-dom';
import { Tag } from '../components/Tag/Tag';
import { ErrorAlert } from '../components/ErrorAlert/ErrorAlert';
import { getPublicTags } from '../services/publicApi';
import { ApiError } from '../services/api';
import { useProfileContext } from '../hooks/useProfileContext';
import { toggleTag, updateTagParams } from '../utils/tags';
import type { Tag as TagType } from '../types';

export const PublicTags = () => {
  const { profileIdentifier } = useParams<{ profileIdentifier: string }>();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const [tags, setTags] = useState<TagType[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [errorStatus, setErrorStatus] = useState<number | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const gridRef = useRef<HTMLDivElement>(null);
  const [columnCount, setColumnCount] = useState(0);

  const profileContext = useProfileContext(profileIdentifier || '');

  const tagQueryString = searchParams.get('tags') || '';
  const selectedTagSlugs = tagQueryString ? tagQueryString.split(',').filter(Boolean) : [];

  useEffect(() => {
    const updateColumnCount = () => {
      if (gridRef.current) {
        const computedStyle = window.getComputedStyle(gridRef.current);
        const gridTemplateColumns = computedStyle.gridTemplateColumns;
        const columns = gridTemplateColumns.split(' ').length;
        setColumnCount(columns);
      }
    };

    updateColumnCount();
    window.addEventListener('resize', updateColumnCount);
    return () => window.removeEventListener('resize', updateColumnCount);
  }, [tags]);

  useEffect(() => {
    const loadTags = async () => {
      if (!profileContext.baseUrl || !profileContext.username || profileContext.isLoading) {
        return;
      }

      setIsLoading(true);
      setError(null);
      setErrorStatus(null);
      try {
        const tagsData = await getPublicTags(profileContext.baseUrl, profileContext.username);
        setTags(tagsData);
      } catch (err: unknown) {
        const message = err instanceof Error ? err.message : 'Failed to load tags';
        const status = err instanceof ApiError ? err.status : null;
        setError(message);
        setErrorStatus(status);
        setTags([]);
      } finally {
        setIsLoading(false);
      }
    };

    loadTags();
  }, [profileContext.baseUrl, profileContext.username, profileContext.isLoading]);

  const handleTagToggle = (slug: string) => {
    const newSelectedSlugs = toggleTag(slug, selectedTagSlugs);
    const newParams = updateTagParams(newSelectedSlugs, searchParams);
    navigate(`/social/${profileIdentifier}?${newParams.toString()}`);
  };

  // Show profile context errors
  if (profileContext.error) {
    return (
      <>
        <ErrorAlert error={profileContext.error} statusCode={profileContext.errorStatus} />
      </>
    );
  }

  return (
    <>
      <ErrorAlert error={error} statusCode={errorStatus} />

      {profileContext.isLoading || isLoading ? (
        <div className="text-center pt-5">
          <div className="spinner-border" role="status">
            <span className="visually-hidden">Loading...</span>
          </div>
        </div>
      ) : (
        <div ref={gridRef} className="tags-grid my-2">
          {tags.map((tag, index) => {
            const row = columnCount > 0 ? Math.floor(index / columnCount) : 0;
            const col = columnCount > 0 ? index % columnCount : 0;
            const isCheckerboard = (row + col) % 2 === 0;
            return (
              <div
                key={tag.slug}
                className={`tags-grid-item ${isCheckerboard ? 'tags-grid-checker-1' : 'tags-grid-checker-2'}`}
              >
                <Tag tag={tag} selectedTagSlugs={selectedTagSlugs} onToggle={handleTagToggle} />
              </div>
            );
          })}
        </div>
      )}

      <div className="mt-1">&nbsp;</div>
    </>
  );
};

