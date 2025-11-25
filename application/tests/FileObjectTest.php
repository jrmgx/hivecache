<?php

namespace App\Tests;

use App\Entity\FileObject;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileObjectTest extends BaseApiTestCase
{
    public function testCreateMediaObject(): void
    {
        [, $token] = $this->createAuthenticatedUser('test@example.com', 'testuser', 'test');
        $file = new UploadedFile(__DIR__ . '/data/image_01.jpg', 'image_01.jpg');

        $this->assertUnauthorized('POST', '/api/file_objects', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'files' => [
                    'file' => $file,
                ],
            ],
        ]);

        $response = $this->client->request('POST', '/api/file_objects', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'auth_bearer' => $token,
            'extra' => [
                'files' => [
                    'file' => $file,
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertMatchesResourceItemJsonSchema(FileObject::class);

        $json = dump($response->toArray());

        $this->assertArrayHasKey('@id', $json);
        $this->assertArrayHasKey('contentUrl', $json);

        $this->assertIsString($json['@id']);
        $this->assertIsString($json['contentUrl']);

        // TODO at some point this should be true
        // $this->assertStringStartsWith('http', $json['contentUrl'], 'contentUrl should be a valid URL');
    }
}
