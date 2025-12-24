# BookmarkHive Web Client

The React-based web client for BookmarkHive, a decentralized social bookmarking service.

## Development

### Requirements

You will need:
- Node.js (v16 or higher)
- yarn

### Installation

Installing dependencies:
```bash
yarn install
```

### Development Server

Start the development server:
```bash
yarn dev
```

The application will be available at `http://localhost:5173` (or the next available port).

### Building for Production

Build the production bundle:
```bash
yarn build
```

The built files will be in the `dist/` directory.

### Preview Production Build

Preview the production build locally:
```bash
yarn preview
```

## Shared Code

This client shares code with the browser extension through the `shared/` directory at the workspace root. The shared package contains:

- **Unified API client** (`shared/src/api/client.ts`) - Single API client used by both extension and client
- **Type definitions** (`shared/src/types/`) - Types matching the OpenAPI specification exactly (including `@iri` fields)
- **Storage adapters** (`shared/src/storage/`) - Abstracted storage for browser.storage (extension) and localStorage (client)
- **Tag transformations** (`shared/src/tag/transform.ts`) - Functions to transform tags between API and internal formats
- **Utilities** (`shared/src/utils/`) - Shared utility functions like URL resolution and cursor extraction

### Storage Adapter Pattern

The client uses the `localStorage` adapter which wraps `localStorage` for token storage. The API client is configured with this adapter in `src/services/api.ts`:

```typescript
const apiClient = createApiClient({
  baseUrl: BASE_URL,
  storage: createLocalStorageAdapter(),
  enableCache: true,
});
```

### Build Process

The build process uses Vite with React plugin. TypeScript path aliases (`@shared/*`) are configured in `tsconfig.app.json`, and Vite's resolve alias is configured in `vite.config.ts` to import from the shared package. Both TypeScript and Vite resolve these imports correctly.

### Environment Variables

- `VITE_API_BASE_URL` - Base URL for the API (defaults to `https://bookmarkhive.test`)

Set this in a `.env` file or your environment:

```bash
VITE_API_BASE_URL=https://bookmarkhive.com/api
```
