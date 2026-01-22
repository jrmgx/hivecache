import { useNavigate, useLocation } from 'react-router-dom';
import { SidebarSection } from '../SidebarSection';
import { SidebarAction } from '../SidebarAction';

export const SocialSection = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const isTimelineActive = location.pathname === '/social/timeline';
  const isInstanceThisActive = location.pathname === '/social/instance/this';
  const isInstanceOtherActive = location.pathname === '/social/instance/other';

  return (
    <SidebarSection
      title="Social"
      storageKey="sidebar-section-social-collapsed"
    >
      <SidebarAction
        label="Your Timeline"
        onClick={() => {
          navigate('/social/timeline');
        }}
        active={isTimelineActive}
      />
      {/* <SidebarAction
        label="@one"
        onClick={() => {
          navigate('/social/one@hivecache.test');
        }}
      /> */}
      {/* <SidebarAction
        label="#php"
        onClick={() => {
          navigate('/social/tag/php');
        }}
        active={isPhpTagActive}
      /> */}
      <SidebarAction
        label="This Server"
        onClick={() => {
          navigate('/social/instance/this');
        }}
        active={isInstanceThisActive}
      />
      {/* <SidebarAction
        label="Tags"
        onClick={() => {
          // TODO: Implement
        }}
      /> */}
      {/* <SidebarAction
        label="Trending"
        onClick={() => {
          // TODO: Implement
        }}
      /> */}
      <SidebarAction
        label="Other Server"
        onClick={() => {
          navigate('/social/instance/other');
        }}
        active={isInstanceOtherActive}
      />
    </SidebarSection>
  );
};

