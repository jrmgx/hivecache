<?php

namespace App\ActivityPub\Dto;

/**
 * @see NoteObject.json
 */
final class NoteObject
{
    public string $type = 'Note';
    public string $id;

    public string $published;
    public string $url;
    public string $attributedTo;
    public string $atomUri { get => $this->id; }
    public ?string $conversation = null; // TODO what is it?
    public string $content;

    /** @var array<string> */
    public array $to = [Constant::PUBLIC_URL];
    /** @var array<string> */
    public array $cc;
    /** @var array<string, string> */
    public array $contentMap { get => ['en' => $this->content]; }
    /** @var array<int, DocumentObject> */
    public array $attachment;
    /** @var array<int, HashtagObject> */
    public array $tag = [];
    public ?Collection $replies = null;

    public ?string $summary = null;
    public ?string $inReplyTo = null;
    public bool $sensitive = false;
    public ?string $inReplyToAtomUri = null;
}
