<?php

/** @noinspection HttpUrlsUsage */

namespace App\ActivityPub\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * @see CreateNoteActivity.json
 */
final class CreateNoteActivity
{
    /** @var array<mixed> */
    #[SerializedName('@context')]
    public array $context = [
        Constant::CONTEXT_URL, [
            // Fully compatible with mastodon TODO this could be simplified and stays compatible
            'ostatus' => 'http://ostatus.org#',
            'atomUri' => 'ostatus:atomUri',
            'inReplyToAtomUri' => 'ostatus:inReplyToAtomUri',
            'conversation' => 'ostatus:conversation',
            'sensitive' => 'as:sensitive',
            'toot' => 'http://joinmastodon.org/ns#',
            'votersCount' => 'toot:votersCount',
            'blurhash' => 'toot:blurhash',
            'focalPoint' => [
                '@container' => '@list',
                '@id' => 'toot:focalPoint',
            ],
            'Hashtag' => 'as:Hashtag',
        ],
    ];
    public string $type = 'Create';
    public string $id;
    public string $actor;
    public string $published;
    /** @var array<string> */
    public array $to = [Constant::PUBLIC_URL];
    /** @var array<string> */
    public array $cc;
    public NoteObject $object;
    public ?\stdClass $signature = null;
}
