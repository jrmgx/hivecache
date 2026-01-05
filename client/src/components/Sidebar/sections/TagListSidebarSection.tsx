import { SidebarSection } from '../SidebarSection';
import { SidebarAction } from '../SidebarAction';

interface TagListSidebarSectionProps {
  onNavigateToHome?: () => void;
}

export const TagListSidebarSection = ({
  onNavigateToHome,
}: TagListSidebarSectionProps) => {
  return (
    <SidebarSection
      title="Tags"
      storageKey="sidebar-section-tags-collapsed"
    >
      {onNavigateToHome && (
        <SidebarAction icon="arrow-left" label="Back" onClick={onNavigateToHome} />
      )}
    </SidebarSection>
  );
};

