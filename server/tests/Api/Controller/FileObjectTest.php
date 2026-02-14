<?php

namespace App\Tests\Api\Controller;

use App\Tests\BaseApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileObjectTest extends BaseApiTestCase
{
    public function testCreateMediaObject(): void
    {
        [, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');
        $file = new UploadedFile(__DIR__ . '/../../data/image_01.jpg', 'image_01.jpg');

        $this->assertUnauthorized('POST', '/users/me/files', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'files' => [
                    'file' => $file,
                ],
            ],
        ]);

        $this->request('POST', '/users/me/files', [
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

        $this->assertIsInt($json['size']);
        $this->assertIsString($json['mime']);

        $this->assertValidUrl($json['@iri'], '@iri should be a valid URL');
        $this->assertValidUrl($json['contentUrl'], 'contentUrl should be a valid URL');
    }
}
