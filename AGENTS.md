# HiveCache Agent Instructions

## Base Rules

- Use existing classes, methods, components
- Use the same patterns; new features are usually variations of existing ones
- Refactor often
- Do not be too verbose
- Do not add comments everywhere; prefer good naming
- Find the root cause of bugs, do not try workarounds
- Do not implement defensive code by default


## Project Structure

| Component      | Path              | Description                                                         |
| -------------- | ----------------- | ------------------------------------------------------------------- |
| server         | `/server`         | PHP/Symfony: API, Admin (EasyAdmin), ActivityPub                    |
| client         | `/client`         | React + TypeScript, connects to the API                             |
| extension      | `/extension`      | Browser extension (TypeScript), pushes data to the API              |
| shared         | `/shared`         | Common TypeScript for client and extension (API connector, helpers) |
| docs           | `/docs`           | mdBook documentation                                                |
| images         | `/images`         | Common assets                                                       |
| infrastructure | `/infrastructure` | Docker definitions                                                  |
| tools          | `/tools`          | API code quality tools                                              |
| castor         | `/.castor`        | Castor commands to drive all parts                                  |


## API Documentation

See `server/openapi.json` for the full API documentation.
See [docs/src/Development/API.md](docs/src/Development/API.md) for API development details.


## Environment

Commands run via Docker through Castor. Prefix host commands with `castor --no-it`.
See [docs/src/Development/Setup.md](docs/src/Development/Setup.md) for setup.

Example: `bin/console clear:cache` → `castor --no-it bin/console clear:cache`


## Server (PHP/Symfony)

**Paths:** `server/**/*.php`

- API: `server/src/Api/*` (OpenAPI attributes) — [docs/src/Development/API.md](docs/src/Development/API.md)
- Admin: `server/src/Admin/*` (EasyAdmin) — [docs/src/Development/Admin.md](docs/src/Development/Admin.md)
- ActivityPub: `server/src/ActivityPub/*` — [docs/src/Development/ActivityPub.md](docs/src/Development/ActivityPub.md)

All share the same entities and must follow these rules:

- PHP 8.4 syntax
- Never use `empty` except when it is the only option
- Use positional and named arguments instead of default values
- Use latest Symfony version
- Prefer attributes to config files
- Do not remove debug code unless asked: `$this->client->enableProfiler()`, `dump`, `dd`
- Create repository methods and do not use the `findOneBy` and `findBy` shortcuts

**Testing:** Two servers in test mode:
1. Current code (HttpKernel requests) — run assertions against this
2. `external_ap_server.test` — black box, same code, no DB access; use real HTTP client for ActivityPub server-to-server tests

### Specific Symfony instructions

%TODO

## Client (React/TypeScript)

**Paths:** `client/**/*.ts`, `client/**/*.tsx`

- Use existing components when possible, adapt if needed
- Check if new code can be refactored with existing code
- Use shared code in `/shared` for API calls and common logic
- See [docs/src/Development/Client.md](docs/src/Development/Client.md).


## Extension (Browser Extension)

**Paths:** `extension/**/*.ts`

- Use shared code in `/shared` for API calls and common logic
- See [docs/src/Development/Extension.md](docs/src/Development/Extension.md).


## Docs (mdBook)

**Paths:** `docs/**/*.md`

- This project uses mdBook


## Importer

The importer is a PHP Castor command in `.castor/importer.php`.


## More doc

You can find more specific human based documentation about each component of that project in those files:

| Component                        | Documentation                          |
| -------------------------------- | -------------------------------------- |
| Setup                            | `/docs/src/Development/Setup.md`       |
| ActivityPub (part of the server) | `/docs/src/Development/ActivityPub.md` |
| Admin (part of the server)       | `/docs/src/Development/Admin.md`       |
| API (part of the server)         | `/docs/src/Development/API.md`         |
| Client                           | `/docs/src/Development/Client.md`      |
| Extension                        | `/docs/src/Development/Extension.md`   |
| Deployment                       | `/docs/src/Development/Deployment.md`  |
