import type { Tag as TagType } from '../../types';
import { formatTagName as formatTagNameShared } from '@shared';

interface TagNameProps {
  tag: TagType;
  showIcon?: boolean;
}

/**
 * Component for displaying tag name with optional icon and public indicator
 * Shows a green ✦ symbol after the name if the tag is public
 */
export const TagName = ({ tag, showIcon = true }: TagNameProps) => {
  return (
    <>
      {showIcon && tag.icon && `${tag.icon} `}
      {tag.name}
      {tag.isPublic && <span className="text-success ms-1">✦</span>}
    </>
  );
};

/**
 * Formats tag name as a string (for use in text-only contexts like select options)
 * Includes icon and public indicator
 * Re-exports the shared formatTagName function for convenience
 */
export const formatTagName = formatTagNameShared;

