<?php

namespace App\Tests\ActivityPub\Bundler;

use App\ActivityPub\Bundler\NoteObjectBundler;
use App\ActivityPub\Dto\Collection;
use App\ActivityPub\Dto\CollectionPage;
use App\ActivityPub\Dto\Constant;
use App\ActivityPub\Dto\DocumentObject;
use App\ActivityPub\Dto\HashtagObject;
use App\ActivityPub\Dto\NoteObject;
use App\Factory\AccountFactory;
use App\Factory\BookmarkFactory;
use App\Factory\FileObjectFactory;
use App\Factory\FollowerFactory;
use App\Factory\UserFactory;
use App\Factory\UserTagFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class NoteObjectBundlerTest extends KernelTestCase
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

        $this->assertEquals('https://cursor.com/blog/scaling-agents', $bookmark->url);
        $this->assertEquals('Cursor Blog: Scaling Agents', $bookmark->title);

        $this->assertCount(1, $bookmark->instanceTags);
        $this->assertEquals('writing', $bookmark->instanceTags->first()->slug);

        $this->assertNotNull($bookmark->mainImage);
        $this->assertEquals('https://activitypub.academy/system/media_attachments/files/115/903/742/588/033/413/original/c3d294bfcf67ad88.jpg', $bookmark->mainImage->filePath);
    }

    public function testBundleFromBookmark(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var NoteObjectBundler $noteObjectBundler */
        $noteObjectBundler = $container->get(NoteObjectBundler::class);

        $user = UserFactory::createOne(['username' => 'testuser']);
        $account = AccountFactory::createOneWithUsernameAndInstance('testuser', AccountFactory::TEST_INSTANCE, [
            'owner' => $user,
        ]);

        $publicTag = UserTagFactory::createOne([
            'owner' => $user,
            'name' => 'public-tag',
            'isPublic' => true,
        ]);
        $privateTag = UserTagFactory::createOne([
            'owner' => $user,
            'name' => 'private-tag',
            'isPublic' => false,
        ]);

        $mainImage = FileObjectFactory::createOne([
            'mime' => 'image/jpeg',
            'filePath' => 'images/test.jpg',
        ]);
        $archive = FileObjectFactory::createOne([
            'mime' => 'application/gzip',
            'filePath' => 'archives/test.gz',
        ]);

        $bookmark = BookmarkFactory::createOne([
            'account' => $account,
            'title' => 'Test Bookmark Title',
            'url' => 'https://example.com/test',
            'userTags' => new ArrayCollection([$publicTag, $privateTag]),
            'mainImage' => $mainImage,
            'archive' => $archive,
        ]);

        $follower1 = FollowerFactory::createOne(['owner' => $user]);
        $follower2 = FollowerFactory::createOne(['owner' => $user]);
        $followers = [$follower1, $follower2];

        $noteObject = $noteObjectBundler->bundleFromBookmark($bookmark, $followers);

        $this->assertEquals('Note', $noteObject->type);
        $this->assertEquals($noteObject->id, $noteObject->url);
        $this->assertStringContainsString($account->username, $noteObject->id);
        $this->assertStringContainsString($bookmark->id, $noteObject->id);
        $this->assertEquals($account->uri, $noteObject->attributedTo);
        $this->assertEquals($bookmark->createdAt->format(\DATE_ATOM), $noteObject->published);

        $this->assertStringContainsString($bookmark->title, $noteObject->content);
        $this->assertStringContainsString($bookmark->url, $noteObject->content);

        $this->assertCount(2, $noteObject->cc);
        $this->assertContains($follower1->account->uri, $noteObject->cc);
        $this->assertContains($follower2->account->uri, $noteObject->cc);

        $this->assertCount(1, $noteObject->tag);
        $this->assertEquals('#public-tag', $noteObject->tag[0]->name);
        $this->assertStringContainsString('public-tag', $noteObject->content);

        $this->assertCount(2, $noteObject->attachment);
        $imageAttachment = array_filter($noteObject->attachment, fn ($att) => str_starts_with($att->mediaType, 'image/'));
        $this->assertCount(1, $imageAttachment);
        $archiveAttachment = array_filter($noteObject->attachment, fn ($att) => \in_array($att->mediaType, ['application/gzip', 'text/html']));
        $this->assertCount(1, $archiveAttachment);
    }
}
