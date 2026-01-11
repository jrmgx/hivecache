<?php

namespace App\Config;

enum RouteAction: string
{
    // API actions
    case Collection = 'collection';
    case Diff = 'diff';
    case Create = 'create';
    case Get = 'get';
    case History = 'history';
    case Patch = 'patch';
    case Delete = 'delete';

    // ActivityPub actions
    case SharedInbox = 'shared_inbox';
    case Inbox = 'inbox';
    case Outbox = 'outbox';
    case WellKnown = 'well_known';
}
