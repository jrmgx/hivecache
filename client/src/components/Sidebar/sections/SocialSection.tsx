import { useNavigate, useLocation } from 'react-router-dom';
import { SidebarSection } from '../SidebarSection';
import { SidebarAction } from '../SidebarAction';
import { Tag } from '../../Tag/Tag';

interface SocialSectionProps {
  selectedTagSlugs?: string[];
  onTagToggle?: (slug: string) => void;
}

const slugToTag = (slug: string) => ({
  '@iri': '',
  slug,
  name: slug.replace(/-/g, ' '),
  isPublic: true,
  pinned: false,
  layout: 'default',
  icon: null,
});

export const SocialSection = ({ selectedTagSlugs = [], onTagToggle }: SocialSectionProps) => {
  const navigate = useNavigate();
  const location = useLocation();
  const isTimelineActive = location.pathname === '/social/timeline';
  const isInstanceThisActive = location.pathname === '/social/instance/this';
  const isInstanceOtherActive = location.pathname === '/social/instance/other';
  const showSelectedTags = (isTimelineActive || isInstanceThisActive || isInstanceOtherActive) && selectedTagSlugs.length > 0;

  return (
    <SidebarSection
      title="Social"
      storageKey="sidebar-section-social-collapsed"
    >
      <SidebarAction
        label="Your Timeline"
        onClick={() => navigate('/social/timeline')}
        active={isTimelineActive}
      />
      {isTimelineActive && showSelectedTags && (
        <div className="ms-3 mb-2">
          {selectedTagSlugs.map((slug) => (
            <Tag
              key={slug}
              tag={slugToTag(slug)}
              selectedTagSlugs={selectedTagSlugs}
              onToggle={onTagToggle}
              className="mb-2"
            />
          ))}
        </div>
      )}
      <SidebarAction
        label="This Server"
        onClick={() => navigate('/social/instance/this')}
        active={isInstanceThisActive}
      />
      {isInstanceThisActive && showSelectedTags && (
        <div className="ms-3 mb-2">
          {selectedTagSlugs.map((slug) => (
            <Tag
              key={slug}
              tag={slugToTag(slug)}
              selectedTagSlugs={selectedTagSlugs}
              onToggle={onTagToggle}
              className="mb-2"
            />
          ))}
        </div>
      )}
      <SidebarAction
        label="Other Server"
        onClick={() => navigate('/social/instance/other')}
        active={isInstanceOtherActive}
      />
      {isInstanceOtherActive && showSelectedTags && (
        <div className="ms-3 mb-2">
          {selectedTagSlugs.map((slug) => (
            <Tag
              key={slug}
              tag={slugToTag(slug)}
              selectedTagSlugs={selectedTagSlugs}
              onToggle={onTagToggle}
              className="mb-2"
            />
          ))}
        </div>
      )}
    </SidebarSection>
  );
};

