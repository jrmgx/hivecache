/**
 * Unified type definitions matching the OpenAPI specification exactly
 * All API response types include @iri field as per IRI schema
 */

// ============================================================================
// Base IRI Interface
// ============================================================================

export interface IRI {
  '@iri': string;
}

// ============================================================================
// Meta Types
// ============================================================================

/**
 * Flexible meta type for Tag/User meta objects
 * In practice, client-o-* values may be string, boolean, or number
 */
export interface ApiTagMeta {
  [key: string]: string | boolean | number | null | undefined;
}

// ============================================================================
// Tag Types
// ============================================================================

/**
 * TagOwner - API response format for owned tags
 * Extends IRI schema
 */
export interface ApiTag extends IRI {
  name: string;
  slug: string;
  isPublic: boolean;
  meta?: ApiTagMeta;
}

/**
 * TagProfile - API response format for public tags
 * Extends IRI schema
 */
export interface ApiTagProfile extends IRI {
  name: string;
  slug: string;
}

/**
 * Tag - Transformed tag with extracted metadata
 * Used internally by clients after transformation
 */
export interface Tag extends IRI {
  name: string;
  slug: string;
  isPublic: boolean;
  pinned: boolean;
  layout: string;
  icon: string | null;
}

/**
 * TagCreate - Request payload for creating tags
 */
export interface TagCreate {
  name: string;
  isPublic?: boolean;
  meta?: ApiTagMeta;
}

/**
 * TagUpdate - Request payload for updating tags
 */
export interface TagUpdate {
  name?: string;
  meta?: ApiTagMeta;
}

/**
 * TagCollection - API response for tag collections
 * Note: Tag collections do not include pagination fields
 */
export interface TagCollection {
  collection: ApiTag[];
  total: number;
}

// ============================================================================
// FileObject Types
// ============================================================================

/**
 * FileObject - API response format
 * Extends IRI schema
 */
export interface FileObject extends IRI {
  id: string;
  contentUrl: string | null;
  size: number;
  mime: string;
}

// ============================================================================
// User Types
// ============================================================================

/**
 * UserOwner - API response format for owned user profile
 * Extends IRI schema
 */
export interface UserOwner extends IRI {
  email: string;
  username: string;
  isPublic: boolean;
  meta?: ApiTagMeta;
}

/**
 * UserProfile - API response format for public user profile
 * Extends IRI schema
 */
export interface UserProfile extends IRI {
  username: string;
}

/**
 * User - Simplified user type (used in Bookmark)
 */
export interface User {
  '@iri': string;
  username: string;
  isPublic: boolean;
}

/**
 * UserCreate - Request payload for creating users
 */
export interface UserCreate {
  email: string;
  username: string;
  password: string;
  isPublic?: boolean;
  meta?: ApiTagMeta;
}

/**
 * UserUpdate - Request payload for updating users
 */
export interface UserUpdate {
  username?: string;
  email?: string;
  password?: string;
  meta?: ApiTagMeta;
}

// ============================================================================
// Bookmark Types
// ============================================================================

/**
 * BookmarkOwner - API response format for owned bookmarks
 * Extends IRI schema
 * Note: domain field exists in entity but missing from OpenAPI spec - included here
 */
export interface BookmarkOwner extends IRI {
  id: string;
  createdAt: string; // ISO date-time string
  title: string;
  url: string;
  domain: string; // From entity, not in OpenAPI spec
  isPublic: boolean;
  tags: ApiTag[];
  owner: UserOwner;
  mainImage: FileObject | null;
  archive: FileObject | null;
  pdf: FileObject | null;
}

/**
 * BookmarkProfile - API response format for public bookmarks
 * Extends IRI schema
 */
export interface BookmarkProfile extends IRI {
  id: string;
  createdAt: string; // ISO date-time string
  title: string;
  url: string;
  tags: ApiTagProfile[];
  mainImage: FileObject | null;
  archive: FileObject | null;
  pdf: FileObject | null;
}

/**
 * Bookmark - Transformed bookmark with transformed tags
 * Used internally by clients after transformation
 */
export interface Bookmark extends IRI {
  id: string;
  createdAt: string;
  title: string;
  url: string;
  domain: string;
  isPublic: boolean;
  tags: Tag[];
  owner: User;
  mainImage: FileObject | null;
  pdf: FileObject | null;
  archive: FileObject | null;
}

/**
 * BookmarkCreate - Request payload for creating bookmarks
 */
export interface BookmarkCreate {
  title: string;
  url: string;
  isPublic?: boolean;
  tags?: string[]; // Array of tag IRIs
  mainImage?: string | null; // IRI reference to FileObject
  archive?: string | null; // IRI reference to FileObject
}

/**
 * BookmarkUpdate - Request payload for updating bookmarks
 */
export interface BookmarkUpdate {
  title?: string;
  isPublic?: boolean;
  tags?: string[]; // Array of tag IRIs
  mainImage?: string | null; // IRI reference to FileObject
  archive?: string | null; // IRI reference to FileObject
}

/**
 * BookmarkCollection - API response for bookmark collections
 */
export interface BookmarkCollection {
  collection: BookmarkOwner[];
  prevPage: string | null;
  nextPage: string | null;
  total: number | null;
}

/**
 * BookmarksResponse - Transformed bookmark collection
 * Used internally by clients after transformation
 */
export interface BookmarksResponse {
  collection: Bookmark[];
  prevPage: string | null;
  nextPage: string | null;
  total: number | null;
}

// ============================================================================
// Authentication Types
// ============================================================================

/**
 * AuthRequest - Request payload for authentication
 */
export interface AuthRequest {
  username: string;
  password: string;
}

/**
 * AuthResponse - Response from authentication endpoint
 */
export interface AuthResponse {
  token: string;
}

// ============================================================================
// Layout Constants
// ============================================================================

export const LAYOUT_DEFAULT = 'default';
export const LAYOUT_EMBEDDED = 'embedded';
export const LAYOUT_IMAGE = 'image';

