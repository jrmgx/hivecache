interface SidebarProps {
  sections: React.ReactNode[];
}

export const Sidebar = ({ sections }: SidebarProps) => {
  return (
    <div className="h-100 sidebar">
      <div className="container-fluid">
        <div className="row">
          <div className="col mt-3">
            {sections}
            <div className="sidebar-spacer"></div>
          </div>
        </div>
      </div>
    </div>
  );
};
