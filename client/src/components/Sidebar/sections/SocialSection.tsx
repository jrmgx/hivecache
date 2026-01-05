import { SidebarSection } from '../SidebarSection';
import { SidebarAction } from '../SidebarAction';

export const SocialSection = () => {
  return (
    <SidebarSection
      title="Social"
      storageKey="sidebar-section-social-collapsed"
    >
      <SidebarAction
        label="Timeline"
        onClick={() => {}}
      />
      <SidebarAction
        label="@jerome"
        onClick={() => {}}
      />
      <SidebarAction
        label="PHP"
        onClick={() => {}}
      />
    </SidebarSection>
  );
};

