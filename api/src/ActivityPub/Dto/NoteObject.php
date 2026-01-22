<?php

namespace App\ActivityPub\Dto;

final class NoteObject
{
    /* {
        "id":"https://activitypub.academy/users/braulus_aelamun/statuses/115903743533604527",
        "type":"Note",
        "summary":null,
        "inReplyTo":null,
        "published":"2026-01-01T00:00:00Z",
        "url":"https://activitypub.academy/@braulus_aelamun/115903743533604527",
        "attributedTo":"https://activitypub.academy/users/braulus_aelamun",
        "to":["https://www.w3.org/ns/activitystreams#Public"],
        "cc":["https://activitypub.academy/users/braulus_aelamun/followers"],
        "sensitive":false,
        "atomUri":"https://activitypub.academy/users/braulus_aelamun/statuses/115903743533604527",
        "inReplyToAtomUri":null,
        "conversation":"tag:activitypub.academy,2026-01-01:objectId=223005:objectType=Conversation",
        "content":"<p><a href=\"https://startpage.com\" target=\"_blank\" rel=\"nofollow noopener noreferrer\"><span class=\"invisible\">https://</span><span class=\"\">startpage.com</span><span class=\"invisible\"></span></a> <a href=\"https://activitypub.academy/tags/start\" class=\"mention hashtag\" rel=\"tag\">#<span>start</span></a> <a href=\"https://activitypub.academy/tags/page\" class=\"mention hashtag\" rel=\"tag\">#<span>page</span></a></p>",
        "contentMap":{
            "en":"<p><a href=\"https://startpage.com\" target=\"_blank\" rel=\"nofollow noopener noreferrer\"><span class=\"invisible\">https://</span><span class=\"\">startpage.com</span><span class=\"invisible\"></span></a> <a href=\"https://activitypub.academy/tags/start\" class=\"mention hashtag\" rel=\"tag\">#<span>start</span></a> <a href=\"https://activitypub.academy/tags/page\" class=\"mention hashtag\" rel=\"tag\">#<span>page</span></a></p>"
        },
        "attachment":[
            {
                "type":"Document",
                "mediaType":"image/jpeg",
                "url":"https://activitypub.academy/system/media_attachments/files/115/903/742/588/033/413/original/c3d294bfcf67ad88.jpg",
                "name":"Alt text",
                "blurhash":"...",
                "focalPoint":[0,0],
                "width":1427,
                "height":962
            }
        ],
        "tag":[
            {
                "type":"Hashtag",
                "href":"https://activitypub.academy/tags/start",
                "name":"#start"
            }
        ],
        "replies":{
            "id":"https://activitypub.academy/users/braulus_aelamun/statuses/115903743533604527/replies",
            "type":"Collection",
            "first":{
                "type":"CollectionPage",
                "next":"https://activitypub.academy/users/braulus_aelamun/statuses/115903743533604527/replies?only_other_accounts=true&page=true",
                "partOf":"https://activitypub.academy/users/braulus_aelamun/statuses/115903743533604527/replies",
                "items":[]
            }
        }
    } */
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
