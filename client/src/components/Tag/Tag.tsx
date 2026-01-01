import type { Tag as TagType } from '../../types';

interface TagProps {
  tag: TagType;
  selectedTagSlugs: string[];
  onToggle?: (slug: string) => void;
  className?: string;
}

export const Tag = ({
  tag,
  selectedTagSlugs,
  onToggle,
  className
}: TagProps) => {
  const isSelected = selectedTagSlugs.includes(tag.slug);

  const handleClick = (e: React.MouseEvent) => {
    e.preventDefault();
    if (onToggle) {
      onToggle(tag.slug);
    }
  };

  const baseClasses = 'd-flex align-items-center position-relative flex-grow-1';
  const containerClasses = className
    ? `${baseClasses} ${className}`.trim()
    : baseClasses;

  const shouldGrow = !className || !className.includes('flex-grow-0');
  const buttonClasses = `btn btn-outline-secondary border-0 text-start ${shouldGrow ? 'flex-grow-1' : ''} ${isSelected ? 'active' : ''}`;

  return (
    <>
      <div className={containerClasses}>
        <button
          type="button"
          className={buttonClasses}
          onClick={handleClick}
        >
          {tag.icon && `${tag.icon} `}
          {tag.name}
        </button>
      </div>
    </>
  );
};

