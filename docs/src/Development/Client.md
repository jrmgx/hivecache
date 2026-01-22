# Client Development Guide

> [!NOTE]
> Client and extension share some code and best practices. Please read both documentations.

The HiveCache web client is a React-based application for managing bookmarks
and interacting with the decentralized HiveCache network.

## Technical Stack

- Framework: React
- Build Tool: Vite
- Language: TypeScript
- Package Manager: Yarn

## Requirements

You will need:
- Node.js (v16 or higher)
- Yarn

## Installation

Install dependencies:

```bash
castor client:install
```

## Development Server

Start the development server:

```bash
castor client:watch
```

The application will be available at `http://localhost:5173` (or the next available port).

## Building for Production

Build the production bundle:

```bash
castor client:build
```

The built files will be in the `dist/` directory.


## Shared Code Architecture

This client shares code with the browser extension through the `shared/` directory at the workspace root.
The shared package contains:

- Unified API client (`shared/src/api/client.ts`) - Single API client used by both extension and client
- Type definitions (`shared/src/types/`) - Types matching the OpenAPI specification exactly (including `@iri` fields)
- Storage adapters (`shared/src/storage/`) - Abstracted storage for browser.storage (extension) and localStorage (client)
- Tag transformations (`shared/src/tag/transform.ts`) - Functions to transform tags between API and internal formats
- Utilities (`shared/src/utils/`) - Shared utility functions like URL resolution and cursor extraction

### Storage Adapter Pattern

The client uses the `localStorage` adapter which wraps `localStorage` for token storage.
The API client is configured with this adapter in `src/services/api.ts`:

```typescript
const apiClient = createApiClient({
  baseUrl: BASE_URL,
  storage: createLocalStorageAdapter(),
  enableCache: true,
});
```

### Build Process

The build process uses Vite with React plugin.
TypeScript path aliases (`@shared/*`) are configured in `tsconfig.app.json`,
and Vite's resolve alias is configured in `vite.config.ts` to import from the shared package.
Both TypeScript and Vite resolve these imports correctly.

## Project Structure

- `src/components/` - React components
- `src/pages/` - Page components
- `src/services/` - API and service layer
- `src/hooks/` - Custom React hooks
- `src/utils/` - Utility functions
- `src/types/` - TypeScript type definitions
