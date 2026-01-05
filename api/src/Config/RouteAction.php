<?php

namespace App\Config;

enum RouteAction: string
{
    case Collection = 'collection';
    case Diff = 'diff';
    case Create = 'create';
    case Get = 'get';
    case History = 'history';
    case Patch = 'patch';
    case Delete = 'delete';
}
