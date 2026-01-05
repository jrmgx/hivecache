import { Icon } from '../Icon/Icon';
import { closeOffcanvas } from '../../utils/offcanvas';

interface SidebarActionProps {
  icon?: 'pencil' | 'trash' | 'arrow-left' | 'share-fat' | 'play' | 'eye';
  label: string;
  onClick: () => void;
  disabled?: boolean;
  active?: boolean;
}

export const SidebarAction = ({ icon, label, onClick, disabled, active }: SidebarActionProps) => {
  const handleClick = () => {
    onClick();
    closeOffcanvas();
  };

  return (
    <div className="d-flex align-items-center position-relative flex-grow-1 mb-2">
      <button
        type="button"
        className={`btn border-0 text-start flex-grow-1 ${active ? 'btn-secondary' : 'btn-outline-secondary'}`}
        onClick={handleClick}
        disabled={disabled}
      >
        {icon && <Icon name={icon} className="me-2" />}
        {label}
      </button>
    </div>
  );
};

