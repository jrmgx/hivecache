# Extension Development Guide

> [!NOTE]
> Extension and client share some code and best practices. Please read both documentations.

The HiveCache browser extension allows users to quickly capture and bookmark web pages directly from their browser.

## Technical Stack

- Language: TypeScript
- Build Tool: esbuild
- Package Manager: Yarn
- Browser APIs: Chrome Extension Manifest V3

## Requirements

You will need:
- Node.js (v16 or higher)
- Yarn

## Project Structure

This project uses TypeScript, all source files are in the `src/` directory:
- Edit `.ts` files in `src/`
- Run `yarn build` to compile to `.js` files
- The compiled `.js` files are what the browser extension uses

## Installation

Install dependencies:

```bash
castor extension:install
```

## Building the Extension

Build the extension:

```bash
castor extension:build
```

## Development Workflow

- Build once: `castor extension:build`
- Watch mode (auto-rebuild on changes): `castor extension:watch`
- Clean compiled files: `castor extension:clean`

## Loading the Extension in Your Browser

### Chrome/Chromium

1. Go to `chrome://extensions/`
2. Enable "Developer mode" (toggle in the top right)
3. Click "Load unpacked"
4. Select the `extension/` directory

### Firefox

1. Go to `about:debugging`
2. Click "This Firefox" in the left sidebar
3. Click "Load Temporary Add-on"
4. Select `manifest.json` from the `extension/` directory

## Shared Code Architecture

This extension shares code with the web client through the `shared/` directory at the workspace root.
The shared package contains:

- Unified API client (`shared/src/api/client.ts`) - Single API client used by both extension and client
- Type definitions (`shared/src/types/`) - Types matching the OpenAPI specification exactly
- Storage adapters (`shared/src/storage/`) - Abstracted storage for browser.storage (extension) and localStorage (client)
- Tag transformations (`shared/src/tag/transform.ts`) - Functions to transform tags between API and internal formats
- Utilities (`shared/src/utils/`) - Shared utility functions

### Storage Adapter Pattern

The extension uses the `browserStorage` adapter which wraps `chrome.storage.local` for token and configuration storage.
The API client is configured with this adapter in `src/api.ts`:

```typescript
const adapter = createBrowserStorageAdapter();
const apiClient = createApiClient({
  baseUrl: apiHost,
  storage: adapter,
  enableCache: true,
});
```

### Build Process

The build process uses esbuild to bundle TypeScript files.
TypeScript path aliases (`@shared/*`) are configured in `tsconfig.json` to import from the shared package.
Esbuild resolves these imports automatically when bundling.

## Extension Components

- Popup (`popup.html`, `src/popup.ts`) - The main UI when clicking the extension icon
- Options (`options.html`, `src/options.ts`) - Configuration page for instance URL and authentication
- Background (`background.js`, `src/background.ts`) - Background service worker
- Content Script (`content.js`, `src/content.ts`) - Injected into web pages to extract metadata and archive pages

## Manifest

The extension uses Manifest V3. Key features:
- `activeTab` permission for accessing current tab
- `storage` permission for saving configuration
- `scripting` permission for content script injection
- Content scripts run on all pages (`<all_urls>`)
