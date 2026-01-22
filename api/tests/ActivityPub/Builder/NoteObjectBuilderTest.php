<?php

namespace App\Tests\ActivityPub\Builder;

use App\ActivityPub\Bundler\NoteObjectBundler;
use App\ActivityPub\Dto\Collection;
use App\ActivityPub\Dto\CollectionPage;
use App\ActivityPub\Dto\Constant;
use App\ActivityPub\Dto\DocumentObject;
use App\ActivityPub\Dto\HashtagObject;
use App\ActivityPub\Dto\NoteObject;
use App\Entity\Bookmark;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class NoteObjectBuilderTest extends KernelTestCase
{
    public function testUnbundleToBookmark(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var NoteObjectBundler $noteObjectBundler */
        $noteObjectBundler = $container->get(NoteObjectBundler::class);

        $noteObject = new NoteObject();
        // We have that account in our fixtures
        $noteObject->id = 'https://activitypub.academy/users/braulus_aelamun/statuses/115903743533604527';
        $noteObject->published = '2026-01-01T00:00:00Z';
        $noteObject->url = 'https://activitypub.academy/@braulus_aelamun/115903743533604527';
        $noteObject->attributedTo = 'https://activitypub.academy/users/braulus_aelamun';
        $noteObject->to = [Constant::PUBLIC_URL];
        $noteObject->cc = ['https://activitypub.academy/users/braulus_aelamun/followers'];

        $documentObject = new DocumentObject();
        $documentObject->url = 'https://activitypub.academy/system/media_attachments/files/115/903/742/588/033/413/original/c3d294bfcf67ad88.jpg';
        $documentObject->mediaType = 'image/jpeg';
        $documentObject->name = 'Alt text';
        $documentObject->blurhash = '...';
        $documentObject->focalPoint = [0, 0];
        $documentObject->width = 1427;
        $documentObject->height = 962;
        $noteObject->attachment = [$documentObject];

        $hashtagObject = new HashtagObject();
        $hashtagObject->href = 'https://activitypub.academy/tags/writing';
        $hashtagObject->name = '#writing';
        $noteObject->tag = [$hashtagObject];

        $collectionPage = new CollectionPage();
        $collectionPage->next = 'https://activitypub.academy/users/braulus_aelamun/statuses/115903743533604527/replies?only_other_accounts=true&page=true';
        $collectionPage->partOf = 'https://activitypub.academy/users/braulus_aelamun/statuses/115903743533604527/replies';
        $collectionPage->items = [];

        $collection = new Collection();
        $collection->id = 'https://activitypub.academy/users/braulus_aelamun/statuses/115903743533604527/replies';
        $collection->first = $collectionPage;
        $noteObject->replies = $collection;

        $noteObject->content = <<<'HTML'
            <p>
                Cursor Blog: Scaling Agents
                <a href="https://cursor.com/blog/scaling-agents" target="_blank" rel="nofollow noopener noreferrer">
                    <span class="invisible">https://</span>
                    <span class="">cursor.com/blog/scaling-agents</span>
                </a>
                <a href="https://api2.hivecache.test/profile/bob/bookmarks?tags=writing" target="_blank" rel="nofollow noopener noreferrer tag" class="mention hashtag">
                    #<span>writing</span>
                </a>
            </p>
            HTML;

        $bookmark = $noteObjectBundler->unbundleToBookmark($noteObject);

        $this->assertInstanceOf(Bookmark::class, $bookmark);
        $this->assertEquals('https://cursor.com/blog/scaling-agents', $bookmark->url);
        $this->assertEquals('Cursor Blog: Scaling Agents', $bookmark->title);

        $this->assertCount(1, $bookmark->instanceTags);
        $this->assertEquals('writing', $bookmark->instanceTags->first()->slug);

        $this->assertNotNull($bookmark->mainImage);
        $this->assertEquals('https://activitypub.academy/system/media_attachments/files/115/903/742/588/033/413/original/c3d294bfcf67ad88.jpg', $bookmark->mainImage->filePath);
    }
}
