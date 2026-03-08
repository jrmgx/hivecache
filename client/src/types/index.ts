/**
 * Type definitions for HiveCache client
 * Re-exports all types from shared package
 */

export * from '@shared';
import type { Bookmark } from '@shared';

export interface BookmarkWithAccount extends Bookmark {
  account?: { username: string; instance?: string; '@iri': string };
  instance?: string;
}
