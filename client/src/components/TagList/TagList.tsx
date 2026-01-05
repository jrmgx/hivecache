import { Tag } from '../Tag/Tag';
import type { Tag as TagType } from '../../types';

interface TagListProps {
  tags: TagType[];
  selectedTagSlugs: string[];
  pinnedTags?: TagType[];
  onTagToggle?: (slug: string) => void;
}

const sortTags = (tags: TagType[]): TagType[] => {
  return [...tags].sort((a, b) => {
    return a.name.localeCompare(b.name, undefined, { sensitivity: 'base' });
  });
};

const renderTagGroup = (
  tagList: TagType[],
  selectedTagSlugs: string[],
  onTagToggle?: (slug: string) => void
) => {
  if (tagList.length === 0) return null;

  return (
    <div>
      {tagList.map((tag) => (
        <Tag
          key={tag.slug}
          tag={tag}
          selectedTagSlugs={selectedTagSlugs}
          onToggle={onTagToggle}
          className='mb-2'
        />
      ))}
    </div>
  );
};

export const TagList = ({
  tags,
  selectedTagSlugs,
  pinnedTags,
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
      {renderTagGroup(sortedPinnedTags, selectedTagSlugs, onTagToggle)}
      {renderTagGroup(sortedSelectedTags, selectedTagSlugs, onTagToggle)}
    </>
  );
};

