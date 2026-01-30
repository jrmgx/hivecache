import { SidebarSection } from '../SidebarSection';
import { SidebarAction } from '../SidebarAction';

interface EditBookmarkSidebarSectionProps {
  onNavigateBack?: () => void;
  onNavigateToEdit?: () => void;
  onDeleteBookmark?: () => void;
  isDeleting?: boolean;
}

export const EditBookmarkSidebarSection = ({
  onNavigateBack,
  onNavigateToEdit,
  onDeleteBookmark,
  isDeleting = false,
}: EditBookmarkSidebarSectionProps) => {
  return (
    <SidebarSection
      title="Bookmark"
      storageKey="sidebar-section-bookmark-collapsed"
    >
      {onNavigateToEdit && (
        <SidebarAction icon="pencil" label="Edit" onClick={onNavigateToEdit} />
      )}
      {onDeleteBookmark && (
        <SidebarAction
        icon="trash"
        label={isDeleting ? 'Deleting...' : 'Delete'}
        onClick={onDeleteBookmark}
        disabled={isDeleting}
        />
      )}
      {onNavigateBack && (
        <SidebarAction icon="arrow-left" label="Back" onClick={onNavigateBack} />
      )}
    </SidebarSection>
  );
};

