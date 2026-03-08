import { useState, useEffect, useRef, useMemo } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { Tag } from '../components/Tag/Tag';
import { Icon } from '../components/Icon/Icon';
import { EditTag } from '../components/EditTag/EditTag';
import { ErrorAlert } from '../components/ErrorAlert/ErrorAlert';
import { SearchInput } from '../components/SearchInput/SearchInput';
import { getTags, ApiError } from '../services/api';
import { toggleTag, updateTagParams } from '../utils/tags';
import type { Tag as TagType } from '../types';

export const Tags = () => {

  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const [tags, setTags] = useState<TagType[]>([]);
  const [tagSearchQuery, setTagSearchQuery] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [errorStatus, setErrorStatus] = useState<number | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const gridRef = useRef<HTMLDivElement>(null);
  const [columnCount, setColumnCount] = useState(0);
  const [editingTag, setEditingTag] = useState<TagType | null>(null);

  const tagQueryString = searchParams.get('tags') || '';
  const selectedTagSlugs = tagQueryString ? tagQueryString.split(',').filter(Boolean) : [];

  const filteredTags = useMemo(() => {
    const q = tagSearchQuery.trim().toLowerCase();
    if (!q) return tags;
    return tags.filter(
      (tag) =>
        tag.name.toLowerCase().includes(q) || tag.slug.toLowerCase().includes(q)
    );
  }, [tags, tagSearchQuery]);

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
  }, [filteredTags]);

  useEffect(() => {
    const loadTags = async () => {
      setIsLoading(true);
      setError(null);
      setErrorStatus(null);
      try {
        const tagsData = await getTags();
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
  }, []);

  const handleTagToggle = (slug: string) => {
    const newSelectedSlugs = toggleTag(slug, selectedTagSlugs);
    const newParams = updateTagParams(newSelectedSlugs, searchParams);
    navigate(`/me?${newParams.toString()}`);
  };

  const handleTagEdit = (tag: TagType) => {
    setEditingTag(tag);
  };

  const handleTagSave = async () => {
    // Refresh tags list after save
    try {
      const tagsData = await getTags();
      setTags(tagsData);
      setError(null);
      setErrorStatus(null);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to refresh tags';
      const status = err instanceof ApiError ? err.status : null;
      setError(message);
      setErrorStatus(status);
    }
  };

  const handleTagModalClose = () => {
    setEditingTag(null);
  };

  return (
    <>
      <ErrorAlert error={error} statusCode={errorStatus} />

      {isLoading ? (
        <div className="text-center pt-5">
          <div className="spinner-border" role="status">
            <span className="visually-hidden">Loading...</span>
          </div>
        </div>
      ) : (
        <>
          <SearchInput
            value={tagSearchQuery}
            onChange={setTagSearchQuery}
            onClear={() => setTagSearchQuery('')}
            disabled={false}
            placeholder="Search tags..."
          />
          <div ref={gridRef} className="tags-grid my-2">
            {filteredTags.map((tag, index) => {
            const row = columnCount > 0 ? Math.floor(index / columnCount) : 0;
            const col = columnCount > 0 ? index % columnCount : 0;
            const isCheckerboard = (row + col) % 2 === 0;
            return (
              <div
                key={tag.slug}
                className={`tags-grid-item ${isCheckerboard ? 'tags-grid-checker-1' : 'tags-grid-checker-2'}`}
              >
                <div className="d-flex align-items-center">
                  <Tag
                    tag={tag}
                    selectedTagSlugs={selectedTagSlugs}
                    onToggle={handleTagToggle}
                  />
                  <button
                    className="btn btn-outline-secondary border-0 ms-2"
                    onClick={() => handleTagEdit(tag)}
                    aria-label={`Edit ${tag.name}`}
                  >
                    <Icon name="pencil" />
                  </button>
                </div>
              </div>
            );
          })}
          </div>
        </>
      )}

      <div className="mt-1">&nbsp;</div>

      <EditTag
        tag={editingTag}
        onSave={handleTagSave}
        onClose={handleTagModalClose}
      />
    </>
  );
};

