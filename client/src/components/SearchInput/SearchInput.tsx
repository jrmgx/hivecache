import React from 'react';
import { Tag } from '../Tag/Tag';
import type { Tag as TagType } from '../../types';

interface SearchInputProps {
  value: string;
  onChange: (query: string) => void;
  onClear: () => void;
  disabled: boolean;
  placeholder?: string;
  selectedTags?: TagType[];
  selectedTagSlugs?: string[];
  onTagToggle?: (slug: string) => void;
}

export const SearchInput = ({
  value,
  onChange,
  onClear,
  disabled,
  placeholder = 'Search bookmarks...',
  selectedTags = [],
  selectedTagSlugs = [],
  onTagToggle,
}: SearchInputProps) => {

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    onChange(e.target.value);
  };

  const handleSearchToggle = (e: React.MouseEvent) => {
    e.preventDefault();
    onClear();
  };

  const isSearchActive = value.length > 0;

  return (
    <div className="d-flex align-items-center flex-wrap gap-2 mb-2 mt-3">
      {/* Display selected tags and search button on the same line before the search input */}
      {(selectedTags.length > 0 || isSearchActive) && (
        <div className="d-flex align-items-center gap-2" style={{ flexShrink: 0 }}>
          {selectedTags.length > 0 && (
            <>
              {selectedTags.map((tag) => (
                <div key={tag.slug} style={{ flexShrink: 0 }}>
                  <Tag
                    tag={tag}
                    selectedTagSlugs={selectedTagSlugs}
                    onToggle={onTagToggle}
                    className="flex-grow-0"
                  />
                </div>
              ))}
            </>
          )}
          {isSearchActive && (
            <div className="d-flex align-items-center position-relative flex-grow-0" style={{ flexShrink: 0 }}>
              <button
                type="button"
                className="btn btn-outline-secondary text-start flex-grow-0 active border-0"
                onClick={handleSearchToggle}
              >
                Search
              </button>
            </div>
          )}
        </div>
      )}
      <div className="position-relative" style={{ flexGrow: 1, flexShrink: 1, flexBasis: 0, minWidth: '200px' }}>
        <input
          type="text"
          className="form-control w-100"
          placeholder={placeholder}
          value={value}
          onChange={handleChange}
          disabled={disabled}
          aria-label="Search bookmarks"
        />
      </div>
    </div>
  );
};

