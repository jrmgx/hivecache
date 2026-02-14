<?php

namespace App\ActivityPub\Dto;

final class WebFinger
{
    public string $subject;
    /** @var array<string> */
    public array $aliases = [];
    /** @var array<WebFingerLink> */
    public array $links = [];
}
