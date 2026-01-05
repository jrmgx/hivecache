<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * OpenAPI schema definitions.
 * This class contains reusable schema definitions referenced throughout the API documentation.
 */
#[OA\Schema(
    schema: 'UserOwner',
    description: 'User object with owner-level details',
    type: 'object',
    properties: [
        new OA\Property(property: 'username', type: 'string', description: 'Username'),
        new OA\Property(property: 'isPublic', type: 'boolean', description: 'Whether the profile is public'),
        new OA\Property(property: 'meta', type: 'object', description: 'Additional metadata', additionalProperties: true),
        new OA\Property(property: '@iri', type: 'string', format: 'iri', description: 'IRI of the user resource'),
    ]
)]
#[OA\Schema(
    schema: 'UserProfile',
    description: 'Public user profile',
    type: 'object',
    properties: [
        new OA\Property(property: 'username', type: 'string', description: 'Username'),
        new OA\Property(property: '@iri', type: 'string', format: 'iri', description: 'IRI of the user resource'),
    ]
)]
#[OA\Schema(
    schema: 'UserCreate',
    description: 'User creation data',
    type: 'object',
    required: ['username', 'password'],
    properties: [
        new OA\Property(property: 'username', type: 'string', minLength: 3, maxLength: 32, description: 'Username'),
        new OA\Property(property: 'password', type: 'string', minLength: 8, description: 'Password'),
        new OA\Property(property: 'isPublic', type: 'boolean', description: 'Whether the profile is public', default: false),
        new OA\Property(property: 'meta', type: 'object', description: 'Additional metadata', additionalProperties: true),
    ]
)]
#[OA\Schema(
    schema: 'TagOwner',
    description: 'Tag object with owner-level details',
    type: 'object',
    properties: [
        new OA\Property(property: 'name', type: 'string', description: 'Tag name'),
        new OA\Property(property: 'slug', type: 'string', description: 'Tag slug'),
        new OA\Property(property: 'owner', type: 'object', description: 'Tag owner', properties: [
            new OA\Property(property: 'username', type: 'string'),
            new OA\Property(property: '@iri', type: 'string', format: 'iri'),
        ]),
        new OA\Property(property: 'meta', type: 'object', description: 'Additional metadata', additionalProperties: true),
        new OA\Property(property: 'isPublic', type: 'boolean', description: 'Whether the tag is public'),
        new OA\Property(property: '@iri', type: 'string', format: 'iri', description: 'IRI of the tag resource'),
    ]
)]
#[OA\Schema(
    schema: 'TagProfile',
    description: 'Public tag information',
    type: 'object',
    properties: [
        new OA\Property(property: 'name', type: 'string', description: 'Tag name'),
        new OA\Property(property: 'slug', type: 'string', description: 'Tag slug'),
        new OA\Property(property: '@iri', type: 'string', format: 'iri', description: 'IRI of the tag resource'),
    ]
)]
#[OA\Schema(
    schema: 'BookmarkOwner',
    description: 'Bookmark object with owner-level details',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', description: 'Bookmark ID'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', description: 'Creation timestamp'),
        new OA\Property(property: 'title', type: 'string', description: 'Bookmark title'),
        new OA\Property(property: 'url', type: 'string', format: 'uri', description: 'Bookmark URL'),
        new OA\Property(property: 'domain', type: 'string', description: 'Extracted domain from URL'),
        new OA\Property(property: 'owner', type: 'object', description: 'Bookmark owner', properties: [
            new OA\Property(property: 'username', type: 'string'),
            new OA\Property(property: '@iri', type: 'string', format: 'iri'),
        ]),
        new OA\Property(property: 'isPublic', type: 'boolean', description: 'Whether the bookmark is public'),
        new OA\Property(property: 'tags', type: 'array', description: 'Associated tags', items: new OA\Items(type: 'object')),
        new OA\Property(property: 'mainImage', type: 'object', nullable: true, description: 'Main image file object', ref: '#/components/schemas/FileObject'),
        new OA\Property(property: 'archive', type: 'object', nullable: true, description: 'Archive file object', ref: '#/components/schemas/FileObject'),
        new OA\Property(property: '@iri', type: 'string', format: 'iri', description: 'IRI of the bookmark resource'),
    ]
)]
#[OA\Schema(
    schema: 'BookmarkProfile',
    description: 'Public bookmark information',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', description: 'Bookmark ID'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', description: 'Creation timestamp'),
        new OA\Property(property: 'title', type: 'string', description: 'Bookmark title'),
        new OA\Property(property: 'url', type: 'string', format: 'uri', description: 'Bookmark URL'),
        new OA\Property(property: 'domain', type: 'string', description: 'Extracted domain from URL'),
        new OA\Property(property: 'owner', type: 'object', description: 'Bookmark owner', properties: [
            new OA\Property(property: 'username', type: 'string'),
            new OA\Property(property: '@iri', type: 'string', format: 'iri'),
        ]),
        new OA\Property(property: 'tags', type: 'array', description: 'Associated public tags', items: new OA\Items(type: 'object')),
        new OA\Property(property: 'mainImage', type: 'object', nullable: true, description: 'Main image file object', ref: '#/components/schemas/FileObject'),
        new OA\Property(property: 'archive', type: 'object', nullable: true, description: 'Archive file object', ref: '#/components/schemas/FileObject'),
        new OA\Property(property: '@iri', type: 'string', format: 'iri', description: 'IRI of the bookmark resource'),
    ]
)]
#[OA\Schema(
    schema: 'FileObject',
    description: 'File object representing an uploaded file',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', description: 'File object ID'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', description: 'Creation timestamp'),
        new OA\Property(property: 'contentUrl', type: 'string', format: 'uri', nullable: true, description: 'URL to access the file content'),
        new OA\Property(property: 'size', type: 'integer', description: 'File size in bytes'),
        new OA\Property(property: 'mime', type: 'string', description: 'MIME type of the file'),
        new OA\Property(property: '@iri', type: 'string', format: 'iri', description: 'IRI of the file object resource'),
    ]
)]
#[OA\Schema(
    schema: 'BookmarkIndexAction',
    description: 'Bookmark index action representing a change to a bookmark',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', description: 'Index action ID'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', description: 'Creation timestamp'),
        new OA\Property(
            property: 'type',
            type: 'string',
            enum: ['created', 'updated', 'deleted', 'outdated'],
            description: 'Type of action performed on the bookmark'
        ),
        new OA\Property(property: 'bookmark', type: 'string', format: 'uuid', description: 'The bookmark id associated with this action'),
    ]
)]
class Schemas
{
}
