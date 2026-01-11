<?php

namespace App\Config;

enum RouteType: string
{
    // Activity Pub
    case ActivityPub = 'activitypub_';

    // Me
    case Me = 'api_users_me_';
    case MeBookmarks = 'api_users_me_bookmarks_';
    case MeBookmarksIndex = 'api_users_me_bookmarks_index_';
    case MeTags = 'api_users_me_tags_';
    case MeFileObjects = 'api_users_me_files_';
    case MeFollowing = 'api_users_following_';
    case MeFollowers = 'api_users_followers_';

    // Profile
    case ProfileBookmarks = 'api_users_profile_bookmarks_';
    case ProfileTags = 'api_users_profile_tags_';
    case Profile = 'api_users_profile_';

    // Specials
    case Register = 'api_register_';

    public function isMe(): bool
    {
        return match ($this) {
            RouteType::Me,
            RouteType::MeBookmarks,
            RouteType::MeBookmarksIndex,
            RouteType::MeTags,
            RouteType::MeFileObjects => true,
            default => false,
        };
    }
}
