import { useState, useEffect, useRef } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { Tag } from '../components/Tag/Tag';
import { Icon } from '../components/Icon/Icon';
import { EditTag } from '../components/EditTag/EditTag';
import { getTags } from '../services/api';
import { toggleTag, updateTagParams } from '../utils/tags';
import type { Tag as TagType } from '../types';

export const Tags = () => {

  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const [tags, setTags] = useState<TagType[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const gridRef = useRef<HTMLDivElement>(null);
  const [columnCount, setColumnCount] = useState(0);
  const [editingTag, setEditingTag] = useState<TagType | null>(null);

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
      setIsLoading(true);
      setError(null);
      try {
        const tagsData = await getTags();
        setTags(tagsData);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to load tags');
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
    navigate(`/?${newParams.toString()}`);
  };

  const handleTagEdit = (tag: TagType) => {
    setEditingTag(tag);
  };

  const handleTagSave = async () => {
    // Refresh tags list after save
    try {
      const tagsData = await getTags();
      setTags(tagsData);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to refresh tags');
    }
  };

  const handleTagModalClose = () => {
    setEditingTag(null);
  };

  return (
    <>
      {error && (
        <div className="alert alert-danger" role="alert">
          {error}
        </div>
      )}

      {isLoading ? (
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

