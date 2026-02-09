# HiveCache

A decentralized social bookmarking service based on the ActivityPub protocol.

**Bookmark** and **Save/Preserve** what's important to you.
Decentralized and encrypted*. (*wip)

## Overview

HiveCache is a decentralized social bookmarking service that combines bookmarking with web archiving.
Each bookmark includes a snapshot of the page at a specific point in time,
ensuring you'll always have access to the version you bookmarked even if the original disappears.

Key features: automatic page archiving, privacy controls, flexible tagging, and ActivityPub-based federation.
See the [User Guide](./docs/src/UserGuide.md) for details.

## Development Roadmap

### Current Status

- ✅ Simple API with browser extension and web client
- ✅ ActivityPub protocol minimal implementation
- Version history view for bookmarks
- Server administration and moderation tools

### Future Features

- End-to-end encryption for private bookmarks
- Enhanced archiving (videos and other media)

## Documentation

### User Documentation

Learn how to use HiveCache:

- [User Guide](./docs/src/UserGuide.md) - Comprehensive guide covering what HiveCache is, how it works, and basic concepts
- [Web Client](./docs/src/Client.md) - How to use the web interface
- [Web Extension](./docs/src/WebExtension.md) - How to install and use the browser extension
- [Limitations](./docs/src/Limitations.md) - Important limitations and known issues

### Developer Documentation

Technical documentation and development guides:

- [Development Setup](./docs/src/Development/Setup.md) - Setting up your local development environment
- [API Development](./docs/src/Development/API.md) - API architecture, IRI normalization, and technical details
- [Client Development](./docs/src/Development/Client.md) - Web client development guide (React, Vite, TypeScript)
- [Extension Development](./docs/src/Development/Extension.md) - Browser extension development guide
- [ActivityPub Implementation](./docs/src/Development/ActivityPub.md) - How ActivityPub is implemented in HiveCache
- [Production Deployment](./docs/src/Development/Deployment.md) - Guide for deploying to production

## Quick Start

### For Users

1. Set up an account on a HiveCache instance (or run your own)
2. Install the [browser extension](./docs/src/WebExtension.md)
3. Configure the extension with your instance URL and credentials
4. Start bookmarking!

### For Developers

1. Follow the [Development Setup](./docs/src/Development/Setup.md) guide
2. Run `castor start` to launch the local environment
3. Access the application at `https://hivecache.test`
4. Discover all the developer commands by running `castor`

## License

MIT

## Attributions

-
