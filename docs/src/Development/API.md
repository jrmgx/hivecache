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
