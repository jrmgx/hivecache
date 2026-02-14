<?php

namespace App\ActivityPub\Dto;

final class PersonActorPublicKey
{
    public string $owner;
    public string $id;
    public string $publicKeyPem;
}
