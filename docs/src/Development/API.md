# API Development Guide

The HiveCache API is built with Symfony and follows RESTful principles with ActivityPub protocol support.

> [!NOTE]
> The API is designed for max 1000 users per instance to encourage decentralization

## Technical Stack

- Framework: Symfony
- Database: PostgreSQL
- Authentication: JWT (Lexik JWT Bundle)
- Serialization: Symfony Serializer with custom normalizers/denormalizers
- API Documentation: Manual OpenAPI specification

## Serialization groups

Serialization groups control which properties are included when serializing or deserializing entities. They follow a consistent naming convention across the API.

### Naming convention

```
{entity}:{action}:{visibility}
```

- **entity** — Lowercase snake_case matching the resource (e.g. `bookmark`, `user`, `account`, `tag`, `file_object`, `following`, `follower`, `bookmark_index`)
- **action** — `show` (output), `create` (input), `update` (input), or `read` (output, used for read-only resources)
- **visibility** — `public` or `private` (when the resource has different views for owner vs others)

### Output groups (serialization)

| Group                         | Entity               | Usage                                          |
| ----------------------------- | -------------------- | ---------------------------------------------- |
| `bookmark:show:private`       | Bookmark             | Owner view — includes `isPublic`               |
| `bookmark:show:public`        | Bookmark             | Public view — excludes owner-only fields       |
| `user:show:private`           | User                 | Owner profile view                             |
| `account:show:public`         | Account              | ActivityPub account (always public when shown) |
| `tag:show:public`             | UserTag, InstanceTag | Public tag — name, slug                        |
| `tag:show:private`            | UserTag              | Owner view — includes `meta`, `isPublic`       |
| `file_object:read`            | FileObject           | Standalone file object or embedded in bookmark |
| `following:show:public`       | Following            | ActivityPub following                          |
| `follower:show:public`        | Follower             | ActivityPub follower                           |
| `bookmark_index:show:private` | BookmarkIndexAction  | Index action (owner only)                      |
| `note:show:private`           | Note                 | Owner view — notes are never public            |

### Input groups (deserialization)

| Group             | Entity         | Usage           |
| ----------------- | -------------- | --------------- |
| `bookmark:create` | BookmarkApiDto | Create bookmark |
| `bookmark:update` | BookmarkApiDto | Update bookmark |
| `user:create`     | User           | Registration    |
| `user:update`     | User           | Profile update  |
| `tag:create`      | UserTagApiDto  | Create tag      |
| `tag:update`      | UserTagApiDto  | Update tag      |

### Cross-entity usage

Entities can include properties from related entities by listing multiple groups. For example, `Account` uses `bookmark:show:public`, `bookmark:show:private`, `user:show:private`, and `account:show:public` so it serializes correctly when embedded in bookmarks or user responses.


## IRI Normalizer/Denormalizer

HiveCache uses a custom IRI (Internationalized Resource Identifier) normalization system for API serialization.

### IRI Normalizer

When returning an object from the API, we add an `@iri` property to it so the client has the information for their requests.

When returning a list of objects, we add those same `@iri` to each of them, plus we add extra information:
- Pagination information if relevant
- Number of total objects if relevant

Example response:

```json
{
  "total": 100,
  "prevPage": "https://hivecache.net/users/me/bookmarks?page=1",
  "nextPage": "https://hivecache.net/users/me/bookmarks?page=3",
  "collection": [
    {
      "@iri": "https://hivecache.net/users/me/bookmarks/01234567-89ab-cdef-0123-456789abcdef",
      "title": "Example Bookmark",
      "url": "https://example.com",
      "properties": "..."
    }
  ]
}
```

#### Normalizer Chain

The normalizer chain processes objects in this order:

1. Entrypoint: File Objects - `FileObjectNormalizer` adds `contentUrl`
2. Entrypoint: Bookmark Objects - `BookmarkNormalizer` filters out private tags
3. All Objects - `IriNormalizer` adds `@iri` on objects
4. Final - `serializer.normalizer.object` serializes the object

### IRI Denormalizer

When the client sends an object with relations to the API, it is required that those relations are valid `@iri` strings.

Example request:

```json
{
  "title": "My Bookmark",
  "url": "https://example.com",
  "mainImage": "https://hivecache.net/file-objects/abc123",
  "tags": [
    "https://hivecache.net/users/me/tags/php",
    "https://hivecache.net/users/me/tags/web-development"
  ]
}
```

The denormalizer validates and resolves these IRIs to their corresponding entities.

## PDF Archiving (Removed)

> [!NOTE]
> At first, this project had PDF archiving, but it has been removed. Here's why:
>
> - The `gz` archive that we make is readable in any web browser - you just have to unzip it and open the resulting file
> - PDF is not an open format
> - The infrastructure to build PDF was too heavy related to the fact that this project should be as light as possible
>   so it can run everywhere at no cost

## API Structure

The API follows RESTful conventions:

- `GET /users/me/bookmarks` - List user's bookmarks
- `POST /users/me/bookmarks` - Create a bookmark
- `GET /users/me/bookmarks/{id}` - Get a specific bookmark
- `PUT /users/me/bookmarks/{id}` - Update a bookmark
- `DELETE /users/me/bookmarks/{id}` - Delete a bookmark

Similar patterns exist for tags, following relationships, and other resources.

## OpenAPI Specification

The API includes an OpenAPI specification at `/api/openapi.json` that documents all available endpoints,
request/response schemas, and authentication requirements.

Please regenerate it when updating the schema: `castor api:openapi` you should commit this file.
