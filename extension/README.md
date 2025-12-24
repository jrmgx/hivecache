# BookmarkHive web extension

The decentralized social bookmarking service official web extension.

## Development

### Requirements

You will need:
- Node.js (v16 or higher)
- npm

This project uses TypeScript, all source files are in the `src/` directory:
- Edit `.ts` files in `src/`
- Run `npm run build` to compile to `.js` files
- The compiled `.js` files are what the browser extension uses

### Installation

Installing dependencies:
```bash
npm install
```

Building the extension:
```bash
npm run build
```

Loading the extension in your browser:
   - **Chrome**: Go to `chrome://extensions/`, enable "Developer mode", click "Load unpacked", and select this directory
   - **Firefox**: Go to `about:debugging`, click "This Firefox", click "Load Temporary Add-on", and select `manifest.json`

### Work on new feature or bug fixes

- **Build once**: `npm run build`
- **Watch mode** (auto-rebuild on changes): `npm run watch`
- **Clean compiled files**: `npm run clean`

## Shared Code

This extension shares code with the web client through the `shared/` directory at the workspace root. The shared package contains:

- **Unified API client** (`shared/src/api/client.ts`) - Single API client used by both extension and client
- **Type definitions** (`shared/src/types/`) - Types matching the OpenAPI specification exactly
- **Storage adapters** (`shared/src/storage/`) - Abstracted storage for browser.storage (extension) and localStorage (client)
- **Tag transformations** (`shared/src/tag/transform.ts`) - Functions to transform tags between API and internal formats
- **Utilities** (`shared/src/utils/`) - Shared utility functions

### Storage Adapter Pattern

The extension uses the `browserStorage` adapter which wraps `chrome.storage.local` for token and configuration storage. The API client is configured with this adapter in `src/api.ts`:

```typescript
const adapter = createBrowserStorageAdapter();
const apiClient = createApiClient({
  baseUrl: apiHost,
  storage: adapter,
  enableCache: true,
});
```

### Build Process

The build process uses esbuild to bundle TypeScript files. TypeScript path aliases (`@shared/*`) are configured in `tsconfig.json` to import from the shared package. esbuild resolves these imports automatically when bundling.
