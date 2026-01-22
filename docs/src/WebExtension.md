# Web Extension User Guide

The HiveCache browser extension allows you to quickly capture and bookmark web pages directly from your browser.<br>
See the [User Guide](./UserGuide.md) for general concepts.

## Installation

Get the official extensions from your browser store or from https://hivecache.net

## Configuration

Before using the extension, you need to configure it with your HiveCache instance:

1. Click the extension icon in your browser toolbar
2. Click the "Options" link (or right-click the extension icon and select "Options")
3. Enter your HiveCache instance URL (e.g., `https://hivecache.net`)
4. Enter your username and password
5. Click "Login"

The extension will save your authentication token securely and remember your instance URL.

## Usage

### Capturing a Bookmark

1. Navigate to any web page you want to bookmark
2. Click the HiveCache extension icon in your browser toolbar
3. The popup will automatically fill in:
   - Page title
   - Page URL
   - Image (if available)
4. Optionally:
   - Edit the title
   - Add or select tags (you can create new tags by typing)
   - Change the image URL manually
   - Set the bookmark as public (default is private)
5. Click "Save Bookmark"

> [!WARNING]
> YOU MUST Let the popup open while it's still saving otherwise the capture will fail.

The extension uploads the main image (if provided), archives the page content as a `gz` file,
creates the bookmark with your selected tags, and shares it with followers if public.

### Tag Management

Start typing to search existing tags, or type a new tag name and press Enter to create it.
Tags are automatically created if they don't exist.

### Public vs Private Bookmarks

Private bookmarks (default) are only visible to you.
Public bookmarks are shared with your followers via ActivityPub.

> [!IMPORTANT]
> Once a bookmark is set to public, you cannot change it back to private. See [Limitations](./Limitations.md) for details.

### Archive Failed

If page archiving fails, the bookmark will still be created without the archive. This can happen if:
- The page blocks content scripts
- The page requires authentication
- Network issues occur

> [!NOTE]
> The bookmark will still be saved successfully, just without the archived snapshot.
> You can retry a capture later, it will add up to the history.
