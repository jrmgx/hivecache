<?php

/** @noinspection HttpUrlsUsage */

namespace App\ActivityPub\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;

final class DeleteTombstoneActivity
{
    /** @var array<mixed> */
    #[SerializedName('@context')]
    public array $context = [
        Constant::CONTEXT_URL, [
            // Fully compatible with mastodon TODO this could be simplified and stays compatible
            'ostatus' => 'http://ostatus.org#',
            'atomUri' => 'ostatus:atomUri',
        ],
    ];
    public string $type = 'Delete';
    public string $id;
    public string $actor;
    /** @var array<string> */
    public array $to = [Constant::PUBLIC_URL];
    public TombstoneObject $object;
    public ?\stdClass $signature = null;
}
