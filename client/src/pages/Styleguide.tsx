import { useState } from 'react';
import { Tag } from '../components/Tag/Tag';
import { TagList } from '../components/TagList/TagList';
import { Bookmark } from '../components/Bookmark/Bookmark';
import { BookmarkImage } from '../components/BookmarkImage/BookmarkImage';
import { Masonry } from '../components/Masonry/Masonry';
import { PlaceholderImage } from '../components/PlaceholderImage/PlaceholderImage';
import { Icon } from '../components/Icon/Icon';
import { Sidebar } from '../components/Sidebar/Sidebar';
import { MeSection } from '../components/Sidebar/sections/MeSection';
import { SocialSection } from '../components/Sidebar/sections/SocialSection';
import type { Bookmark as BookmarkType, Tag as TagType } from '../types';
import { LAYOUT_DEFAULT, LAYOUT_EMBEDDED, LAYOUT_IMAGE } from '../types';

const getRandomImageUrl = (): string => {
  const rand = Math.floor(Math.random() * 200);
  return `https://picsum.photos/id/${rand}/1200/800`;
};

const getRandomMasonryImageUrl = (): string => {
  const rand = Math.floor(Math.random() * 200);
  const height = Math.floor(Math.random() * (1800 - 500 + 1)) + 500;
  return `https://picsum.photos/id/${rand}/1200/${height}`;
};

const mockTags: TagType[] = [
  {
    '@iri': 'https://bookmarkhive.test/users/me/tags/web-dev',
    slug: 'web-dev',
    name: 'Web Development',
    isPublic: false,
    pinned: true,
    layout: LAYOUT_DEFAULT,
    icon: '🌐',
  },
  {
    '@iri': 'https://bookmarkhive.test/users/me/tags/design',
    slug: 'design',
    name: 'Design',
    isPublic: false,
    pinned: true,
    layout: LAYOUT_DEFAULT,
    icon: '🎨',
  },
  {
    '@iri': 'https://bookmarkhive.test/users/me/tags/react',
    slug: 'react',
    name: 'React',
    isPublic: false,
    pinned: false,
    layout: LAYOUT_DEFAULT,
    icon: null,
  },
  {
    '@iri': 'https://bookmarkhive.test/users/me/tags/typescript',
    slug: 'typescript',
    name: 'TypeScript',
    isPublic: false,
    pinned: false,
    layout: LAYOUT_DEFAULT,
    icon: null,
  },
  {
    '@iri': 'https://bookmarkhive.test/users/me/tags/embedded',
    slug: 'embedded',
    name: 'Videos',
    isPublic: false,
    pinned: false,
    layout: LAYOUT_EMBEDDED,
    icon: '▶️',
  },
  {
    '@iri': 'https://bookmarkhive.test/users/me/tags/images',
    slug: 'images',
    name: 'Images',
    isPublic: false,
    pinned: false,
    layout: LAYOUT_IMAGE,
    icon: '🖼️',
  },
];

const mockBookmarks: BookmarkType[] = [
  {
    '@iri': 'https://bookmarkhive.test/users/me/bookmarks/1',
    id: '1',
    createdAt: new Date().toISOString(),
    title: 'React Documentation',
    url: 'https://react.dev',
    domain: 'react.dev',
    tags: [mockTags[0], mockTags[2]],
    owner: { '@iri': 'https://bookmarkhive.test/users/profile/user1', username: 'user1', isPublic: false },
    mainImage: {
      '@iri': 'https://bookmarkhive.test/users/me/files/1',
      id: '1',
      contentUrl: getRandomImageUrl(),
      size: 50000,
      mime: 'image/jpeg',
    },
    pdf: null,
    archive: null,
    isPublic: false,
  },
  {
    '@iri': 'https://bookmarkhive.test/users/me/bookmarks/5',
    id: '5',
    createdAt: new Date(Date.now() - 345600000).toISOString(),
    title: 'Example YouTube Video',
    url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    domain: 'youtube.com',
    tags: [mockTags[4]],
    owner: { '@iri': 'https://bookmarkhive.test/users/profile/user1', username: 'user1', isPublic: false },
    mainImage: {
      '@iri': 'https://bookmarkhive.test/users/me/files/5',
      id: '5',
      contentUrl: getRandomImageUrl(),
      size: 50000,
      mime: 'image/jpeg',
    },
    pdf: null,
    archive: null,
    isPublic: false,
  },
  {
    '@iri': 'https://bookmarkhive.test/users/me/bookmarks/6',
    id: '6',
    createdAt: new Date(Date.now() - 400000000).toISOString(),
    title: 'This is a very long bookmark title that contains multiple words and will be truncated after three lines to ensure it does not break the layout of the bookmark card component',
    url: 'https://example.com/very-long-title',
    domain: 'example.com',
    tags: [mockTags[0], mockTags[2]],
    owner: { '@iri': 'https://bookmarkhive.test/users/profile/user1', username: 'user1', isPublic: false },
    mainImage: {
      '@iri': 'https://bookmarkhive.test/users/me/files/6',
      id: '6',
      contentUrl: getRandomImageUrl(),
      size: 80000,
      mime: 'image/jpeg',
    },
    pdf: null,
    archive: null,
    isPublic: false,
  },
  {
    '@iri': 'https://bookmarkhive.test/users/me/bookmarks/7',
    id: '7',
    createdAt: new Date(Date.now() - 450000000).toISOString(),
    title: 'averylongtitleforabookmmarkthatwillbeproblematicifnothandledcorrectlybutimsureitwillbenice',
    url: 'https://example.com/no-spaces-title',
    domain: 'example.com',
    tags: [mockTags[1]],
    owner: { '@iri': 'https://bookmarkhive.test/users/profile/user1', username: 'user1', isPublic: false },
    mainImage: {
      '@iri': 'https://bookmarkhive.test/users/me/files/7',
      id: '7',
      contentUrl: getRandomImageUrl(),
      size: 90000,
      mime: 'image/jpeg',
    },
    pdf: null,
    archive: null,
    isPublic: false,
  },
  {
    '@iri': 'https://bookmarkhive.test/users/me/bookmarks/2',
    id: '2',
    createdAt: new Date(Date.now() - 86400000).toISOString(),
    title: 'TypeScript Handbook',
    url: 'https://www.typescriptlang.org/docs/',
    domain: 'typescriptlang.org',
    tags: [mockTags[0], mockTags[3]],
    owner: { '@iri': 'https://bookmarkhive.test/users/profile/user1', username: 'user1', isPublic: false },
    mainImage: {
      '@iri': 'https://bookmarkhive.test/users/me/files/2',
      id: '2',
      contentUrl: getRandomImageUrl(),
      size: 60000,
      mime: 'image/jpeg',
    },
    pdf: null,
    archive: null,
    isPublic: false,
  },
  {
    '@iri': 'https://bookmarkhive.test/users/me/bookmarks/3',
    id: '3',
    createdAt: new Date(Date.now() - 172800000).toISOString(),
    title: 'Design System Examples',
    url: 'https://example.com/design',
    domain: 'example.com',
    tags: [mockTags[1]],
    owner: { '@iri': 'https://bookmarkhive.test/users/profile/user1', username: 'user1', isPublic: false },
    mainImage: {
      '@iri': 'https://bookmarkhive.test/users/me/files/3',
      id: '3',
      contentUrl: getRandomImageUrl(),
      size: 70000,
      mime: 'image/jpeg',
    },
    pdf: null,
    archive: null,
    isPublic: false,
  },
  {
    '@iri': 'https://bookmarkhive.test/users/me/bookmarks/4',
    id: '4',
    createdAt: new Date(Date.now() - 259200000).toISOString(),
    title: 'Beautiful Image Gallery',
    url: 'https://example.com/gallery',
    domain: 'example.com',
    tags: [mockTags[5]],
    owner: { '@iri': 'https://bookmarkhive.test/users/profile/user1', username: 'user1', isPublic: false },
    mainImage: {
      '@iri': 'https://bookmarkhive.test/users/me/files/4',
      id: '4',
      contentUrl: getRandomImageUrl(),
      size: 120000,
      mime: 'image/jpeg',
    },
    pdf: null,
    archive: null,
    isPublic: false,
  },
];

// Generate 20 mock bookmarks for masonery section
const masonryBookmarks: BookmarkType[] = Array.from({ length: 20 }, (_, i) => ({
  '@iri': `/bookmarks/masonry-${i + 1}`,
  id: `masonry-${i + 1}`,
  createdAt: new Date(Date.now() - i * 86400000).toISOString(),
  title: `Masonry Bookmark ${i + 1}`,
  url: `https://example.com/masonry-${i + 1}`,
  domain: 'example.com',
  tags: [mockTags[i % mockTags.length]],
  owner: { '@iri': 'https://bookmarkhive.test/users/profile/user1', username: 'user1', isPublic: false },
  mainImage: {
    '@iri': `https://bookmarkhive.test/users/me/files/masonry-${i + 1}`,
    id: `masonry-${i + 1}`,
    contentUrl: getRandomMasonryImageUrl(),
    size: 50000 + i * 1000,
    mime: 'image/jpeg',
  },
  pdf: null,
  archive: null,
  isPublic: false,
}));

export const Styleguide = () => {
  const [selectedTagSlugs, setSelectedTagSlugs] = useState<string[]>(['web-dev']);

  const handleTagToggle = (slug: string) => {
    setSelectedTagSlugs((prev) => {
      if (prev.includes(slug)) {
        return [];
      } else {
        return [slug];
      }
    });
  };

  const pinnedTags = mockTags.filter((tag) => tag.pinned);

  // Create sections for sidebar
  const sections: React.ReactNode[] = [
    <MeSection
      key="me"
      tags={mockTags}
      selectedTagSlugs={selectedTagSlugs}
      onTagToggle={handleTagToggle}
    />,
    <SocialSection key="social" />
  ];

  return (
    <>
      <nav className="navbar navbar-expand-md navbar-dark fixed-top bg-primary navbar-height">
        <div className="container-fluid">
          <a className="text-white navbar-brand" href="/me">
            BookmarkHive
          </a>
          <button
            className="navbar-toggler bookmark-navbar-toggler"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#offcanvasStyleguide"
            aria-controls="offcanvasStyleguide"
            aria-expanded="false"
            aria-label="Toggle navigation"
          >
            <span className="text-white navbar-toggler-icon"></span>
          </button>
        </div>
      </nav>
      <main className="d-flex navbar-height-compensate h-100">
        <div className="h-100">
          <div
            className="offcanvas-md offcanvas-start h-100"
            tabIndex={-1}
            id="offcanvasStyleguide"
            aria-labelledby="offcanvasStyleguideLabel"
          >
            <div className="offcanvas-header">
              <h5 className="offcanvas-title" id="offcanvasStyleguideLabel">
                BookmarkHive
              </h5>
              <button
                type="button"
                className="btn-close"
                data-bs-dismiss="offcanvas"
                data-bs-target="#offcanvasStyleguide"
                aria-label="Close"
              ></button>
            </div>
            <div className="offcanvas-body h-100 sidebar">
              <Sidebar sections={sections} />
            </div>
          </div>
        </div>
        <div className="container-fluid sidebar-left py-4">
          <h1 className="mb-4">Component Styleguide</h1>

      <section className="mb-5">
        <h2>Tag Component</h2>
        <div className="row">
          <div className="col-md-4">
            <h3>Default State</h3>
            <Tag tag={mockTags[0]} selectedTagSlugs={[]} className="mb-2" />
            <Tag tag={mockTags[2]} selectedTagSlugs={[]} />
          </div>
        </div>
        <div className="row">
          <div className="col-md-4">
            <h3>Selected State</h3>
            <Tag tag={mockTags[0]} selectedTagSlugs={['web-dev']} className="mb-2"/>
            <Tag tag={mockTags[2]} selectedTagSlugs={['react']} />
          </div>
        </div>
        <div className="row mt-3">
          <div className="col-md-4">
            <h3>With Icon</h3>
            <Tag tag={mockTags[0]} selectedTagSlugs={[]} />
            <Tag tag={mockTags[1]} selectedTagSlugs={[]} />
          </div>
        </div>
        <div className="row">
          <div className="col-md-4">
            <h3>Inline Style</h3>
            <div className="d-flex flex-wrap">
              <Tag tag={mockTags[0]} selectedTagSlugs={selectedTagSlugs} onToggle={handleTagToggle} className="me-2" />
              <Tag tag={mockTags[2]} selectedTagSlugs={selectedTagSlugs} onToggle={handleTagToggle} className="me-2" />
              <Tag tag={mockTags[3]} selectedTagSlugs={selectedTagSlugs} onToggle={handleTagToggle} />
            </div>
          </div>
        </div>
      </section>

      <section className="mb-5">
        <h2>PlaceholderImage and Icon Components</h2>
        <div className="row mb-4">
          <div className="col-auto">
            <h4 className="mb-2">PlaceholderImage: <small className="text-secondary">no-embed</small></h4>
            <div style={{
              width: 72,
              height: 72,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              border: '1px solid #eee',
              borderRadius: 8,
              background: '#fafafa'
            }}>
              <PlaceholderImage type="no-embed" />
            </div>
          </div>
          <div className="col-auto">
            <h4 className="mb-2">PlaceholderImage: <small className="text-secondary">error-image</small></h4>
            <div style={{
              width: 72,
              height: 72,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              border: '1px solid #eee',
              borderRadius: 8,
              background: '#fafafa'
            }}>
              <PlaceholderImage type="error-image" />
            </div>
          </div>
          <div className="col-auto">
            <h4 className="mb-2">PlaceholderImage: <small className="text-secondary">no-image</small></h4>
            <div style={{
              width: 72,
              height: 72,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              border: '1px solid #eee',
              borderRadius: 8,
              background: '#fafafa'
            }}>
              <PlaceholderImage type="no-image" />
            </div>
          </div>
        </div>

        <h4 className="mb-3">All <code>Icon</code> Components</h4>
        <div className="row row-cols-auto g-3 align-items-center">
          {(['pencil', 'share-fat', 'play', 'eye'] as const).map((iconName) => (
            <div className="text-center" key={iconName}>
              <div
                style={{
                  width: 48,
                  height: 48,
                  margin: '0 auto',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  border: '1px solid #eee',
                  borderRadius: 8,
                  background: '#fafafa'
                }}
              >
                <Icon name={iconName} width={28} height={28} />
              </div>
              <div className="mt-1" style={{ fontSize: 12, color: '#666', wordBreak: 'break-all' }}>
                {iconName}
              </div>
            </div>
          ))}
        </div>
      </section>


      <section className="mb-5">
        <h2>TagList Component</h2>
        <div className="row">
          <div className="col-md-4">
            <TagList
              tags={mockTags}
              selectedTagSlugs={selectedTagSlugs}
              pinnedTags={pinnedTags}
              onTagToggle={handleTagToggle}
            />
          </div>
        </div>
      </section>

      <section className="mb-5">
        <h2>Bookmark Component</h2>
        <div className="row gx-3">
          <Bookmark
            bookmark={mockBookmarks[0]}
            layout={LAYOUT_DEFAULT}
            selectedTagSlugs={selectedTagSlugs}
            onTagToggle={handleTagToggle}
          />
          <Bookmark
            bookmark={mockBookmarks[1]}
            layout={LAYOUT_DEFAULT}
            selectedTagSlugs={selectedTagSlugs}
            onTagToggle={handleTagToggle}
          />
        </div>
        <div className="mt-3">
          <h3>Long Titles</h3>
          <p className="text-muted">Titles are ellipsed after 3 lines with full title in tooltip.</p>
          <div className="row gx-3">
            <Bookmark
              bookmark={mockBookmarks.find(b => b.id === '6') || mockBookmarks[2]}
              layout={LAYOUT_DEFAULT}
              selectedTagSlugs={selectedTagSlugs}
              onTagToggle={handleTagToggle}
            />
            <Bookmark
              bookmark={mockBookmarks.find(b => b.id === '7') || mockBookmarks[3]}
              layout={LAYOUT_DEFAULT}
              selectedTagSlugs={selectedTagSlugs}
              onTagToggle={handleTagToggle}
            />
          </div>
        </div>
        <div className="mt-3">
          <h3>Embedded Layout</h3>
          <p className="text-muted">When a bookmark has an embeddable URL (YouTube, Vimeo, TED, PeerTube), it will show an embed player.</p>
          <div className="row gx-3">
            <Bookmark
              bookmark={mockBookmarks.find(b => b.url === 'https://www.youtube.com/watch?v=dQw4w9WgXcQ') || mockBookmarks[4]}
              layout={LAYOUT_EMBEDDED}
              selectedTagSlugs={selectedTagSlugs}
              onTagToggle={handleTagToggle}
            />
            <Bookmark
              bookmark={mockBookmarks[0]}
              layout={LAYOUT_EMBEDDED}
              selectedTagSlugs={selectedTagSlugs}
              onTagToggle={handleTagToggle}
            />
          </div>
        </div>
      </section>

      <section className="mb-5">
        <h2>Image Layout</h2>
        <div className="row gx-3">
          <BookmarkImage bookmark={mockBookmarks[0]} />
        </div>
      </section>

      <section className="mb-5">
        <h2>Full Layout Example</h2>
        <div className="row gx-3">
          {mockBookmarks.map((bookmark) => (
            <Bookmark
              key={bookmark.id}
              bookmark={bookmark}
              layout={LAYOUT_DEFAULT}
              selectedTagSlugs={selectedTagSlugs}
              onTagToggle={handleTagToggle}
            />
          ))}
        </div>
      </section>

      <section className="mb-5">
        <h2>Masonry</h2>
        <Masonry bookmarks={masonryBookmarks} />
      </section>
        </div>
      </main>
    </>
  );
};
