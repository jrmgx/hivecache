<?php

namespace App\ActivityPub\Dto;

final class DocumentObject
{
    /* {
        "type":"Document",
        "mediaType":"image/jpeg",
        "url":"https://activitypub.academy/system/media_attachments/files/115/903/742/588/033/413/original/c3d294bfcf67ad88.jpg",
        "name":"Alt text",
        "blurhash":"UONwvQ?bD%-p.TRPxuM{E3RjfkayD*t7Rjog",
        "focalPoint":[0, 0],
        "width":1427,
        "height":962
    } */
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
