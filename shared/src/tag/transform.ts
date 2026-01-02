/**
 * Tag transformation functions for converting between API format and internal format
 */

import type { ApiTag, Tag, TagCreate, TagUpdate, ApiTagMeta } from '../types';
import { META_PREFIX, LAYOUT_DEFAULT } from '../constants';

/**
 * Transforms an API tag to an internal Tag with extracted metadata
 * Extracts pinned, layout, and icon from meta object using META_PREFIX
 *
 * @param apiTag The tag from the API
 * @returns Transformed tag with icon, pinned, and layout extracted from meta
 */
export function transformTagFromApi(apiTag: ApiTag): Tag {
  const meta = apiTag.meta || {};
  const iconValue = meta[`${META_PREFIX}icon`];
  const icon = iconValue != null && iconValue !== false && iconValue !== '' && String(iconValue).trim() !== ''
    ? String(iconValue)
    : null;

  return {
    '@iri': apiTag['@iri'],
    name: apiTag.name,
    slug: apiTag.slug,
    isPublic: apiTag.isPublic ?? false,
    pinned: Boolean(meta[`${META_PREFIX}pinned`] ?? false),
    layout: String(meta[`${META_PREFIX}layout`] ?? LAYOUT_DEFAULT),
    icon,
  };
}

/**
 * Transforms an internal Tag to API format with meta object
 * Stores pinned, layout, and icon in meta object using META_PREFIX
 *
 * @param tag The internal tag to transform
 * @returns API tag request format with meta object
 */
export function transformTagToApi(tag: Tag): TagCreate | TagUpdate {
  const meta: ApiTagMeta = {};

  meta[`${META_PREFIX}pinned`] = tag.pinned;
  meta[`${META_PREFIX}layout`] = tag.layout;
  meta[`${META_PREFIX}icon`] = tag.icon;

  return {
    name: tag.name,
    isPublic: tag.isPublic,
    ...(Object.keys(meta).length > 0 ? { meta } : {}),
  };
}

/**
 * Formats tag name as a string (for use in text-only contexts like select options)
 * Includes icon and public indicator (green ✦)
 *
 * @param tag The tag to format
 * @returns Formatted tag name string
 */
export function formatTagName(tag: Tag): string {
  const icon = tag.icon ? `${tag.icon} ` : '';
  const publicIndicator = tag.isPublic ? ' ✦' : '';
  return `${icon}${tag.name}${publicIndicator}`;
}

