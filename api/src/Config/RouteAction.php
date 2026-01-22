<?php

namespace App\Config;

/**
 * RouteAction string must NOT contain lowdash "_"
 */
enum RouteAction: string
{
    // API actions
    case Collection = 'collection';
    // case A = 'a';
    case SocialTimeline = 'timeline';
    case SocialTag = 'tag';
    case Diff = 'diff';
    case Create = 'create';
    case Get = 'get';
    case History = 'history';
    case Patch = 'patch';
    case Delete = 'delete';

    // Public API
    case This = 'this';
    case Other = 'other';
    case Trending = 'trending';
    case Tags = 'tags';

    // ActivityPub actions
    case SharedInbox = 'sharedinbox';
    case Inbox = 'inbox';
    case Outbox = 'outbox';
    case WellKnown = 'wellknown';
    case Following = 'following';
    case Follower = 'follower';
}
