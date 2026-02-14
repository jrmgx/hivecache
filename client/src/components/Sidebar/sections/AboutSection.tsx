import { SidebarSection } from '../SidebarSection';

export const AboutSection = () => {
  return (
    <SidebarSection
      title="HiveCache?"
      storageKey="sidebar-section-about-collapsed"
      defaultCollapsed={true}
      persistState={false}
    >
      <div className="d-flex align-items-center position-relative flex-grow-1 mb-0">
        <a
          href="https://hivecache.net"
          target="_blank"
          rel="noopener noreferrer"
          className="btn border-0 text-start flex-grow-1 text-decoration-none" style={{"fontSize": "small"}}>
          HiveCache is a decentralized social bookmarking service based on ActivityPub.
        </a>
      </div>
      <div className="d-flex align-items-center position-relative flex-grow-1 mb-2">
        <a
          href="https://hivecache.net"
          target="_blank"
          rel="noopener noreferrer"
          className="btn btn-outline-secondary border-0 text-start flex-grow-1 text-decoration-none"
        >
          ➜ Learn More
        </a>
      </div>
    </SidebarSection>
  );
};
