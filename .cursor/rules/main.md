# Base rules

Those projects are already in advanced development stage; please explore the code and try to use existing Classes, methods, components.
Use the same way of doing thing, most of the new feature will be variation of existing one.
Refactor often.

IMPORTANT:
 - Do not be too verbose
 - Do not add comment everywhere
 - Prefer good naming over commenting

## Project structure

This project has multiple subprojects, and each has to be treated with specific rules

 - `api`: PHP/symfony API project, find it in the `/api` directory. Check its rule in [api.md](./api.md)
 - `client`: React project in typescript, meant to connect to the api. Find it in the `/client` directory. Check its rule in [client.md](./client.md)
 - `extension`: Web browser extension in typescript, meant to push data to the api. Find it in the `/extension` directory. Check its rule in [extension.md](./extension.md)
 - `shared`: Common base in typescript for client and extension project. Find it in the /shared directory. It is mostly an API connector with other common helper.
 - `docs`: Hugo project, it is the official user-facing documentation as a website. Find it in the `/docs` directory. Check its rule in [docs.md](./docs.md)

Some other parts are:
 - `images`: list of common assets for the global project
 - `infrastructure`: docker file defining the infrastructure
 - `tools`: API related code quality tools
 - `castor`: Set of castor commands to drive all those parts. See `/.castor` directory

## Specific environement

Those project use docker: it is driven via Castor scripts, so when you need to execute a project command, you can not do it on the host.
As an example; given: `bin/console clear:cache` you must prefix it with `castor --no-it`; it gives `castor --no-it bin/console clear:cache` and will run as expected.
