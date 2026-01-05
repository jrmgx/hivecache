import { SidebarSection } from '../SidebarSection';
import { SidebarAction } from '../SidebarAction';

export const SettingsSection = () => {
  return (
    <SidebarSection
      title="Account"
      storageKey="sidebar-section-settings-collapsed"
    >
      <SidebarAction
        label="Profile"
        onClick={() => {}}
      />
      <SidebarAction
        label="Settings"
        onClick={() => {}}
      />
    </SidebarSection>
  );
};

