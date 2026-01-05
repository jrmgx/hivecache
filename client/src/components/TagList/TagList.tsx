import { Tag } from '../Tag/Tag';
import type { Tag as TagType } from '../../types';

interface TagListProps {
  tags: TagType[];
  selectedTagSlugs: string[];
  pinnedTags?: TagType[];
  children?: React.ReactNode;
  onTagToggle?: (slug: string) => void;
}

const sortTags = (tags: TagType[]): TagType[] => {
  return [...tags].sort((a, b) => {
    return a.name.localeCompare(b.name, undefined, { sensitivity: 'base' });
  });
};

export const TagList = ({
  tags,
  selectedTagSlugs,
  pinnedTags,
  children,
  onTagToggle
}: TagListProps) => {

  const computedPinnedTags = pinnedTags ?? tags.filter((tag) => tag.pinned);
  const sortedPinnedTags = sortTags(computedPinnedTags);

  // Get selected tags that are not in the favorites list
  const pinnedTagSlugs = new Set(computedPinnedTags.map((tag) => tag.slug));
  const selectedTagsNotInFavorites = tags.filter(
    (tag) => selectedTagSlugs.includes(tag.slug) && !pinnedTagSlugs.has(tag.slug)
  );
  const sortedSelectedTags = sortTags(selectedTagsNotInFavorites);

  return (
    <>
      {sortedPinnedTags.length > 0 && (
        <div>
          <div className="mb-2 fw-bold">Favorites</div>
          {sortedPinnedTags.map((tag) => (
            <Tag
              key={tag.slug}
              tag={tag}
              selectedTagSlugs={selectedTagSlugs}
              onToggle={onTagToggle}
              className='mb-2'
            />
          ))}
        </div>
      )}
      {sortedSelectedTags.length > 0 && (
        <div>
          <div className="mb-2 fw-bold">Selected</div>
          {sortedSelectedTags.map((tag) => (
            <Tag
              key={tag.slug}
              tag={tag}
              selectedTagSlugs={selectedTagSlugs}
              onToggle={onTagToggle}
              className='mb-2'
            />
          ))}
        </div>
      )}
      {children}
    </>
  );
};

