<?php

namespace App\Tests\ActivityPub\Controller;

use App\ActivityPub\KeysGenerator;
use App\ActivityPub\SignatureHelper;
use App\Api\Config\RouteAction;
use App\Api\Config\RouteType;
use App\Api\UrlGenerator;
use App\Entity\Bookmark;
use App\Entity\UserTimelineEntry;
use App\Factory\AccountFactory;
use App\Factory\UserFactory;
use App\Tests\BaseApiTestCase;

class SharedInboxControllerTest extends BaseApiTestCase
{
    private string $instanceHost;
    private UrlGenerator $urlGenerator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->instanceHost = $this->container->getParameter('instanceHost');
        $this->urlGenerator = $this->container->get(UrlGenerator::class);
    }

    public function testInboxPostCreatesBookmarkAndTimelineEntries(): void
    {
        $keysGenerator = $this->container->get(KeysGenerator::class);
        $keyPair = $keysGenerator->generate();

        $externalActorUri = 'https://external.example.com/users/testuser';
        $externalAccount = AccountFactory::createOne([
            'uri' => $externalActorUri,
            'username' => 'testuser',
            'instance' => 'external.example.com',
            'publicKey' => $keyPair['public'],
            'privateKey' => $keyPair['private'],
        ]);

        $localUser1 = UserFactory::createOne(['username' => 'localuser1']);
        $localAccount1 = AccountFactory::createOne([
            'username' => 'localuser1',
            'owner' => $localUser1,
            'uri' => $this->urlGenerator->generate(RouteType::Profile, RouteAction::Get, ['username' => 'localuser1']),
        ]);

        $localUser2 = UserFactory::createOne(['username' => 'localuser2']);
        $localAccount2 = AccountFactory::createOne([
            'username' => 'localuser2',
            'owner' => $localUser2,
            'uri' => $this->urlGenerator->generate(RouteType::Profile, RouteAction::Get, ['username' => 'localuser2']),
        ]);

        $noteId = 'https://external.example.com/notes/123';
        $noteUrl = 'https://external.example.com/@testuser/123';
        $bookmarkUrl = 'https://example.com/article';
        $bookmarkTitle = 'Test Article Title';
        $published = '2026-02-04T12:00:00Z';

        $noteObject = [
            'id' => $noteId,
            'type' => 'Note',
            'published' => $published,
            'url' => $noteUrl,
            'attributedTo' => $externalActorUri,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => [
                $localAccount1->uri,
                $localAccount2->uri,
                'https://external.example.com/users/testuser/followers',
            ],
            'content' => "<p>{$bookmarkTitle} <a href=\"{$bookmarkUrl}\" target=\"_blank\" rel=\"nofollow noopener noreferrer\"><span class=\"invisible\">https://</span><span class=\"\">example.com/article</span></a></p>",
        ];

        $createActivity = [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                [
                    'ostatus' => 'http://ostatus.org#',
                    'atomUri' => 'ostatus:atomUri',
                    'inReplyToAtomUri' => 'ostatus:inReplyToAtomUri',
                    'conversation' => 'ostatus:conversation',
                    'sensitive' => 'as:sensitive',
                    'toot' => 'http://joinmastodon.org/ns#',
                    'votersCount' => 'toot:votersCount',
                    'blurhash' => 'toot:blurhash',
                    'focalPoint' => [
                        '@container' => '@list',
                        '@id' => 'toot:focalPoint',
                    ],
                    'Hashtag' => 'as:Hashtag',
                ],
            ],
            'id' => $noteId . '/activity',
            'type' => 'Create',
            'actor' => $externalActorUri,
            'published' => $published,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => $noteObject['cc'],
            'object' => $noteObject,
        ];

        $payload = json_encode($createActivity, \JSON_UNESCAPED_SLASHES);
        $inboxPath = '/ap/inbox';
        $inboxUrl = 'https://' . $this->instanceHost . $inboxPath;
        $signatureHeaders = SignatureHelper::build(
            $inboxUrl,
            $externalAccount->keyId,
            $externalAccount->privateKey,
            $payload
        );

        $server = [
            'CONTENT_TYPE' => 'application/activity+json',
            'HTTP_HOST' => $this->instanceHost,
            'HTTP_DATE' => $signatureHeaders['Date'],
            'HTTP_DIGEST' => $signatureHeaders['Digest'],
            'HTTP_SIGNATURE' => $signatureHeaders['Signature'],
        ];

        $bookmarkCountBefore = $this->entityManager->getRepository(Bookmark::class)->count([]);
        $timelineCountBefore = $this->entityManager->getRepository(UserTimelineEntry::class)->count([]);

        $this->client->enableProfiler();
        $this->client->request('POST', '/ap/inbox', [], [], $server, $payload);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/activity+json');

        $this->entityManager->clear();

        $bookmarkCountAfter = $this->entityManager->getRepository(Bookmark::class)->count([]);
        $this->assertEquals($bookmarkCountBefore + 1, $bookmarkCountAfter, 'A bookmark should be created');

        $bookmark = $this->entityManager->getRepository(Bookmark::class)->findOneBy(['url' => $bookmarkUrl]);
        $this->assertNotNull($bookmark, 'Bookmark should exist');
        $this->assertEquals($bookmarkTitle, $bookmark->title);
        $this->assertEquals($bookmarkUrl, $bookmark->url);
        $this->assertTrue($bookmark->isPublic);
        $this->assertEquals($externalAccount->id, $bookmark->account->id);

        $timelineCountAfter = $this->entityManager->getRepository(UserTimelineEntry::class)->count([]);
        $this->assertEquals($timelineCountBefore + 2, $timelineCountAfter, 'Two timeline entries should be created for local users');

        $timelineEntry1 = $this->entityManager->getRepository(UserTimelineEntry::class)->findOneBy([
            'owner' => $localUser1,
            'bookmark' => $bookmark,
        ]);
        $this->assertNotNull($timelineEntry1, 'Timeline entry should exist for localuser1');

        $timelineEntry2 = $this->entityManager->getRepository(UserTimelineEntry::class)->findOneBy([
            'owner' => $localUser2,
            'bookmark' => $bookmark,
        ]);
        $this->assertNotNull($timelineEntry2, 'Timeline entry should exist for localuser2');
    }
}
