<?php

/** @noinspection HttpUrlsUsage */

namespace App\ActivityPub\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;

final class PersonActor
{
    /** @var array<mixed> */
    #[SerializedName('@context')]
    public array $context = [
        Constant::CONTEXT_URL,
        Constant::SECURITY_URL, [
            'schema' => 'http://schema.org#',
            'PropertyValue' => 'schema:PropertyValue',
            'value' => 'schema:value',
        ],
    ];
    public string $type = 'Person';
    public string $id;
    public string $name;
    public string $preferredUsername;
    public string $inbox;
    public string $outbox;
    public string $url;
    public string $published;
    public string $following;
    public string $followers;
    public PersonActorPublicKey $publicKey;
    public PersonActorEndpoints $endpoints;
}
