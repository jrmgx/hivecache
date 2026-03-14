<?php

namespace App\Tests\Api\Controller;

use App\Factory\BookmarkFactory;
use App\Factory\NoteFactory;
use App\Tests\BaseApiTestCase;

class NoteTest extends BaseApiTestCase
{
    public function testCreateNote(): void
    {
        [, $token, $account] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $bookmark = BookmarkFactory::createOne(['account' => $account]);

        $this->assertUnauthorized('POST', '/users/me/notes', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'content' => 'My note content',
                'bookmark' => '/users/me/bookmarks/' . $bookmark->id,
            ],
        ]);

        $this->request('POST', '/users/me/notes', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'content' => 'My note content',
                'bookmark' => '/users/me/bookmarks/' . $bookmark->id,
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertEquals('My note content', $json['content']);
        $this->assertNoteResponse($json);
    }

    public function testCreateNoteWithFullIri(): void
    {
        [, $token, $account] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $bookmark = BookmarkFactory::createOne(['account' => $account]);

        $this->request('POST', '/users/me/notes', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'content' => 'My note content',
                'bookmark' => 'https://hivecache.test/users/me/bookmarks/' . $bookmark->id,
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());
        $this->assertEquals('My note content', $json['content']);
        $this->assertNoteResponse($json);
    }

    public function testCreateNoteFailsWhenBookmarkAlreadyHasNote(): void
    {
        [$user, $token, $account] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $bookmark = BookmarkFactory::createOne(['account' => $account]);
        NoteFactory::createOne(['owner' => $user, 'bookmark' => $bookmark, 'content' => 'Existing note']);

        $this->request('POST', '/users/me/notes', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'content' => 'Second note',
                'bookmark' => '/users/me/bookmarks/' . $bookmark->id,
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateNoteFailsWhenBookmarkNotOwned(): void
    {
        [, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');
        [, , $otherAccount] = $this->createAuthenticatedUserAccount('otheruser', 'test');

        $otherBookmark = BookmarkFactory::createOne(['account' => $otherAccount]);

        $this->request('POST', '/users/me/notes', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'content' => 'My note',
                'bookmark' => '/users/me/bookmarks/' . $otherBookmark->id,
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testGetOwnNote(): void
    {
        [$user, $token, $account] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $bookmark = BookmarkFactory::createOne(['account' => $account]);
        $note = NoteFactory::createOne(['owner' => $user, 'bookmark' => $bookmark, 'content' => 'My note']);

        $this->assertUnauthorized('GET', "/users/me/notes/{$note->id}", [], 'Should not be able to access.');

        $this->request('GET', "/users/me/notes/{$note->id}", [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertEquals('My note', $json['content']);
        $this->assertNoteResponse($json);

        $this->assertOtherUserCannotAccess('GET', "/users/me/notes/{$note->id}");
    }

    public function testGetBookmarkNote(): void
    {
        [$user, $token, $account] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $bookmark = BookmarkFactory::createOne(['account' => $account]);
        $note = NoteFactory::createOne(['owner' => $user, 'bookmark' => $bookmark, 'content' => 'My note']);

        $this->assertUnauthorized('GET', "/users/me/bookmarks/{$bookmark->id}/note", [], 'Should not be able to access.');

        $this->request('GET', "/users/me/bookmarks/{$bookmark->id}/note", [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertEquals('My note', $json['content']);
        $this->assertNoteResponse($json);

        $this->assertOtherUserCannotAccess('GET', "/users/me/bookmarks/{$bookmark->id}/note");
    }

    public function testGetBookmarkNoteReturns404WhenNoNote(): void
    {
        [, $token, $account] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $bookmark = BookmarkFactory::createOne(['account' => $account]);

        $this->request('GET', "/users/me/bookmarks/{$bookmark->id}/note", [
            'auth_bearer' => $token,
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testUpdateOwnNote(): void
    {
        [$user, $token, $account] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $bookmark = BookmarkFactory::createOne(['account' => $account]);
        $note = NoteFactory::createOne(['owner' => $user, 'bookmark' => $bookmark, 'content' => 'Original content']);

        $this->assertUnauthorized('PATCH', "/users/me/notes/{$note->id}", [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['content' => 'Updated content'],
        ]);

        $this->request('PATCH', "/users/me/notes/{$note->id}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => ['content' => 'Updated content'],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertEquals('Updated content', $json['content']);
        $this->assertNoteResponse($json);

        $this->assertOtherUserCannotAccess('PATCH', "/users/me/notes/{$note->id}", [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['content' => 'Hacked content'],
        ]);
    }

    public function testDeleteOwnNote(): void
    {
        [$user, $token, $account] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $bookmark = BookmarkFactory::createOne(['account' => $account]);
        $note = NoteFactory::createOne(['owner' => $user, 'bookmark' => $bookmark]);

        $this->assertUnauthorized('DELETE', "/users/me/notes/{$note->id}");

        $this->assertOtherUserCannotAccess('DELETE', "/users/me/notes/{$note->id}");

        $this->request('DELETE', "/users/me/notes/{$note->id}", ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(204);

        $this->request('GET', "/users/me/notes/{$note->id}", ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testCanNotAccessOtherUsersNote(): void
    {
        [$owner, $ownerToken, $ownerAccount] = $this->createAuthenticatedUserAccount('owneruser', 'test');
        [, $otherToken] = $this->createAuthenticatedUserAccount('otheruser', 'test');

        $bookmark = BookmarkFactory::createOne(['account' => $ownerAccount]);
        $note = NoteFactory::createOne(['owner' => $owner, 'bookmark' => $bookmark, 'content' => 'Private note']);

        $this->request('GET', "/users/me/notes/{$note->id}", ['auth_bearer' => $ownerToken]);
        $this->assertResponseIsSuccessful();

        $this->request('GET', "/users/me/notes/{$note->id}", ['auth_bearer' => $otherToken]);
        $this->assertResponseStatusCodeSame(404);
    }

    private function assertOtherUserCannotAccess(string $method, string $url, array $options = []): void
    {
        [, $otherToken] = $this->createAuthenticatedUserAccount('otheruser', 'test');

        $requestOptions = array_merge($options, ['auth_bearer' => $otherToken]);
        $this->request($method, $url, $requestOptions);
        $this->assertResponseStatusCodeSame(404);
    }

    private function assertNoteResponse(array $json): void
    {
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('createdAt', $json);
        $this->assertArrayHasKey('content', $json);
        $this->assertArrayHasKey('@iri', $json);
        $this->assertValidUrl($json['@iri'], '@iri should be a valid URL');
    }
}
