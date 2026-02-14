<?php

namespace App\ActivityPub\Message;

interface SignedMessageInterface
{
    public string $body { get; }
    public string $uri { get; }
    /** @var array<string, mixed> */
    public array $headers { get; }
}
