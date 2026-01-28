import { useNavigate } from 'react-router-dom';
import { TagList } from '../../TagList/TagList';
import { SidebarSection } from '../SidebarSection';
import { SidebarAction } from '../SidebarAction';
import type { Tag as TagType } from '../../../types';

interface ProfileSectionProps {
  profileUsername: string;
  tags: TagType[];
  selectedTagSlugs: string[];
  onTagToggle?: (slug: string) => void;
  onNavigateToTags?: () => void;
  onClearTags?: () => void;
  // Bookmark page actions
  onNavigateBack?: () => void;
  // Whether we're on bookmark or tags page
  isBookmarkPage?: boolean;
  isTagsPage?: boolean;
}

export const ProfileSection = ({
  profileUsername,
  tags,
  selectedTagSlugs,
  onTagToggle,
  onNavigateToTags,
  onClearTags,
  onNavigateBack,
  isBookmarkPage = false,
  isTagsPage = false,
}: ProfileSectionProps) => {
  const navigate = useNavigate();
  const pinnedTags = tags.filter((tag) => tag.pinned);
  const isHomepageActive = selectedTagSlugs.length === 0;

  const handleNavigateToMe = () => {
    navigate(-1);
  };

  const handleProfileClick = () => {
    if (isBookmarkPage && onNavigateBack) {
      onNavigateBack();
    } else if (onClearTags) {
      onClearTags();
    }
  };

  return (
    <SidebarSection
      title={`@${profileUsername}`}
      storageKey={`sidebar-section-profile-${profileUsername}-collapsed`}
    >
      {/* Show profile and tags navigation for home, tags, and bookmark pages */}
      {(onClearTags || (isBookmarkPage && onNavigateBack)) && (
        <SidebarAction
          label="Profile"
          onClick={handleProfileClick}
          active={isHomepageActive && !isTagsPage && !isBookmarkPage}
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
          label="Tags"
          onClick={onNavigateToTags}
          active={isTagsPage}
        />
      )}
      <SidebarAction
        label={`Follow @${profileUsername}`}
        onClick={() => {
          // TODO
        }}
      />
      <SidebarAction
        icon="arrow-left"
        label="Back"
        onClick={handleNavigateToMe}
      />
    </SidebarSection>
  );
};

