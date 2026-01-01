/**
 * Type definitions and interfaces for the extension
 * Re-exports shared types and adds extension-specific types
 */

// Re-export shared types
export type {
  ApiTag,
  Tag,
  TagCollection,
  FileObject,
  BookmarkCreate as BookmarkPayload,
  AuthRequest,
  AuthResponse,
} from '@shared';

// ============================================================================
// Extension-specific Types
// ============================================================================

// Message Interfaces (for runtime communication)
export interface InlineCSSMessage {
  action: 'inlineCSS';
}

export interface CompressHTMLMessage {
  action: 'compressHTML';
  html: string;
}

export interface ArchivePageMessage {
  action: 'archivePage';
}

export type MessageRequest = CompressHTMLMessage | InlineCSSMessage | ArchivePageMessage;

export interface ArchivePageResponse extends MessageResponse {
  fileObjectId?: string;
}

export interface MessageResponse {
  success: boolean;
  error?: string;
}

// Page Data Interfaces
export interface PageData {
  title: string;
  url: string;
  description: string | null;
  image: string | null; // Contains og:image if available, otherwise favicon as fallback, or null if neither exists
}

// Internal Processing Interfaces
export interface ImportMatch {
  fullMatch: string;
  url: string;
}

export interface BackgroundImageMatch {
  url: string;
  fullMatch: string;
}
