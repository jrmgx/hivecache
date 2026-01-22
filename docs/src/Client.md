# Web Client User Guide

The HiveCache web client is an interface for managing your bookmarks.<br>
See the [User Guide](./UserGuide.md) for general concepts and features.

## Features

- Browse all your bookmarks with tag filtering and search
- Edit tags, choose your favorites, add emoji, choose layout
- Edit bookmark titles, tags, and visibility
- See the whole capture history for any given bookmark

### Tag Management

You can go to the tag page to edit a tag, from here:

**Pinned Tags**: Use the Favorite feature to show this tag in the sidebar.
**Tag Layouts**: Choose a layout (default, embedded, or image) to have it associated with that tag.
**Tag Emoji**: Add an emoji to make the tag visually different.

### Timeline

Your timeline shows bookmarks from users you follow, displayed chronologically.
From here you can re-capture bookmarks to save them to your collection.

### Following

In the Server timeline, discover new people and follow them.
Following works across different HiveCache instances.

You can also follow tags (soon)

### Search and Filter

Find your bookmarks, either by tags, or with fuzzy search across title, URL, or domain.

## Accessing the Client

The web client is typically available at your HiveCache instance URL. For example:
- `https://app.hivecache.net`

## Tips

> [!TIP]
> Multiple tag selection: When multiple tags with different layouts are selected, the client uses the layout of the first selected tag (behavior may be unpredictable).

> [!TIP]
> Tag slugs: Tags are matched by normalized slugs (emojis are stripped). See [Limitations](./Limitations.md) for details.
