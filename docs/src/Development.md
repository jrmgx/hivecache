# Development

This section provides technical documentation and development guides for HiveCache.

### [Setup](./Development/Setup.md)
Get your local development environment up and running.
Learn about Docker requirements, and how to use Castor for managing the development stack.

### [API](./Development/API.md)
Understand the API architecture built with Symfony.
Learn about OpenAPI specification, and how the API handles serialization of bookmarks and related resources.

### [Client](./Development/Client.md)
Develop the React-based web client.
Covers TypeScript, shared code architecture with the extension, storage adapters, and the project structure for building the web interface.

### [Extension](./Development/Extension.md)
Build the browser extension for capturing bookmarks.
Learn about TypeScript, shared code architecture, and how to load and test the extension in Chrome and Firefox.

### [ActivityPub](./Development/ActivityPub.md)
Understand how HiveCache implements ActivityPub protocol for federation.
Learn about the following flow, bookmark capturing flow, re-capturing flow, and the controllers and message handlers involved.

### [Deployment](./Development/Deployment.md)
Deploy HiveCache to production.
Covers Docker-based deployment, database setup, building production images, JWT key pair generation, environment variables, and infrastructure considerations.

### [Swagger](./swagger)
The API is exposed with OpenAPI and a Swagger interface is live so you can play with it.
