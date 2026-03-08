import type { Tag as TagType } from '../../types';

interface TagNameProps {
  tag: TagType;
  showIcon?: boolean;
  showPublicIndicator?: boolean;
}

/**
 * Component for displaying tag name with optional icon and public indicator
 * Shows a green ✦ symbol after the name if the tag is public
 */
export const TagName = ({ tag, showIcon = true, showPublicIndicator = true }: TagNameProps) => {
  return (
    <>
      {showIcon && tag.icon && `${tag.icon} `}
      {tag.name}
      {tag.isPublic && showPublicIndicator && <span className="text-success ms-1">✦</span>}
    </>
  );
};

