<?php

namespace App\Tests;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileObjectTest extends BaseApiTestCase
{
    public function testCreateMediaObject(): void
    {
        [, $token] = $this->createAuthenticatedUser('test@example.com', 'testuser', 'test');
        $file = new UploadedFile(__DIR__ . '/data/image_01.jpg', 'image_01.jpg');

        $this->assertUnauthorized('POST', '/api/users/me/files', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'files' => [
                    'file' => $file,
                ],
            ],
        ]);

        $this->client->enableProfiler();
        $this->request('POST', '/api/users/me/files', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'auth_bearer' => $token,
            'extra' => [
                'files' => [
                    'file' => $file,
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertArrayHasKey('@iri', $json);
        $this->assertArrayHasKey('contentUrl', $json);
        $this->assertArrayHasKey('size', $json);
        $this->assertArrayHasKey('mime', $json);

        $this->assertIsString($json['@iri']);
        $this->assertIsString($json['contentUrl']);
        $this->assertIsInt($json['size']);
        $this->assertIsString($json['mime']);

        // TODO at some point this should be true
        // $this->assertStringStartsWith('http', $json['contentUrl'], 'contentUrl should be a valid URL');
    }
}
