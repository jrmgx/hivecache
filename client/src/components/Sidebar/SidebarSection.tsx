import { useState, useEffect } from 'react';

interface SidebarSectionProps {
  title: string;
  children: React.ReactNode;
  defaultCollapsed?: boolean;
  storageKey: string;
}

export const SidebarSection = ({
  title,
  children,
  defaultCollapsed = false,
  storageKey,
}: SidebarSectionProps) => {
  const [isCollapsed, setIsCollapsed] = useState(defaultCollapsed);

  useEffect(() => {
    const stored = localStorage.getItem(storageKey);
    if (stored !== null) {
      setIsCollapsed(stored === 'true');
    }
  }, [storageKey]);

  const toggleCollapse = () => {
    const newState = !isCollapsed;
    setIsCollapsed(newState);
    localStorage.setItem(storageKey, String(newState));
  };

  const collapseId = `collapse-${storageKey}`;

  return (
    <div className="mb-3">
      <button
        className="btn btn-link text-decoration-none p-0 mb-2 fw-bold d-flex align-items-center w-100 text-start"
        type="button"
        onClick={toggleCollapse}
        aria-expanded={!isCollapsed}
        aria-controls={collapseId}
      >
        <span className="flex-grow-1">{title}</span>
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="16"
          height="16"
          fill="currentColor"
          viewBox="0 0 16 16"
          className={`ms-2 transition-transform ${isCollapsed ? '' : 'rotate-180'}`}
          style={{
            transform: isCollapsed ? 'rotate(0deg)' : 'rotate(180deg)',
            transition: 'transform 0.2s ease',
          }}
        >
          <path
            fillRule="evenodd"
            d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"
          />
        </svg>
      </button>
      <div
        className={`collapse ${!isCollapsed ? 'show' : ''}`}
        id={collapseId}
      >
        {children}
      </div>
    </div>
  );
};

