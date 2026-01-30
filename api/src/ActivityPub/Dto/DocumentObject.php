<?php

namespace App\ActivityPub\Dto;

/**
 * @see NoteObject.json
 */
final class DocumentObject
{
    public string $type = 'Document';
    public string $url;
    public string $mediaType;
    public ?string $name = null;
    public ?string $blurhash = null;
    /** @var array<int> */
    public array $focalPoint = [0, 0];
    public ?int $width = null;
    public ?int $height = null;
}
