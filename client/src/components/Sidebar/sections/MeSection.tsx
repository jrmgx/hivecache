import { useLocation } from 'react-router-dom';
import { TagList } from '../../TagList/TagList';
import { SidebarSection } from '../SidebarSection';
import { SidebarAction } from '../SidebarAction';
import type { Tag as TagType } from '../../../types';

interface MeSectionProps {
  tags: TagType[];
  selectedTagSlugs: string[];
  onTagToggle?: (slug: string) => void;
  onNavigateToTags?: () => void;
  onClearTags?: () => void;
  isTagsPage?: boolean;
}

export const MeSection = ({
  tags,
  selectedTagSlugs,
  onTagToggle,
  onNavigateToTags,
  onClearTags,
  isTagsPage = false,
}: MeSectionProps) => {
  const location = useLocation();
  const pinnedTags = tags.filter((tag) => tag.pinned);
  const isTimelinePage = location.pathname === '/social/timeline';
  const isSocialTagPage = location.pathname.startsWith('/social/tag/');
  const isInstancePage = location.pathname === '/social/instance/this' || location.pathname === '/social/instance/other';
  const isHomepageActive = selectedTagSlugs.length === 0 && !isTagsPage && !isTimelinePage && !isSocialTagPage && !isInstancePage;

  return (
    <SidebarSection
      title="Me"
      storageKey="sidebar-section-me-collapsed"
    >
      {onClearTags && (
        <SidebarAction
          label="Homepage"
          onClick={onClearTags}
          active={isHomepageActive}
        />
      )}
      <TagList
        tags={tags}
        selectedTagSlugs={selectedTagSlugs}
        pinnedTags={pinnedTags}
        onTagToggle={onTagToggle}
      />
      {onNavigateToTags && (
        <SidebarAction
          label={pinnedTags.length === 0 ? 'Show all tags and choose favorite' : 'Show all tags'}
          onClick={onNavigateToTags}
          active={isTagsPage}
        />
      )}
    </SidebarSection>
  );
};

