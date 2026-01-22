## What is HiveCache?

<img src="./assets/icon.svg" alt="HiveCache Logo" style="width: 100%" />

HiveCache is a decentralized social bookmarking service based on ActivityPub.
Each bookmark includes a snapshot of the page at a specific point in time, ensuring you'll always have access to the version you bookmarked even if the original disappears.

<style>
.screenshot-gallery {
    width: 100%;
    margin: 3rem 0 2rem 0;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 2rem;
}

.screenshot-image {
    border-radius: 3px;
    box-shadow: 0 3px 12px 0 rgba(0,0,0,0.15);
    transition: all 0.2s ease-out;
}

@media (min-width: 650px) {
    .screenshot-image:hover {
        transform: scale(1.33) translateY(10px);
        box-shadow: 0 6px 18px 0 rgba(0,0,0,0.2);
    }
}

@media (max-width: 650px) {
    .screenshot-gallery {
        flex-direction: column;
    }
}
</style>
<div class="screenshot-gallery">
  <a href="./assets/HiveCache_home.png" target="_blank"><img src="./assets/HiveCache_home.jpg" alt="HiveCache Home" class="screenshot-image screenshot-1" /></a>
  <a href="./assets/HiveCache_layout_images.png" target="_blank"><img src="./assets/HiveCache_layout_images.jpg" alt="HiveCache Images Layout" class="screenshot-image screenshot-2" /></a>
  <a href="./assets/HiveCache_layout_videos.png" target="_blank"><img src="./assets/HiveCache_layout_videos.jpg" alt="HiveCache Videos Layout" class="screenshot-image screenshot-3" /></a>
</div>

## Philosophy

HiveCache promotes human curation over algorithmic feeds, preserves web content for future reference,
and empowers users with control over their data through decentralization.

## Key Features

- **Easy**: At its core, HiveCache is an easy Bookmarking service
- **Tags**: Flexible tagging with custom layouts
- **Archive**: Save pages and archive them as `gz` files (readable in any browser)
- **Privacy Controls**: Public and private bookmarks (end-to-end encryption coming soon)
- **Social**: Follow users and discover bookmarks across instances
- **Decentralized**: Uses ActivityPub protocol for federation between instances

## Next Step

[Read the User Guide →](./UserGuide.md)