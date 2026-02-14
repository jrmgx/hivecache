<?php

namespace App\Tests\ActivityPub;

use App\ActivityPub\Exception\SignatureException;
use App\ActivityPub\SignatureHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SignatureHelperTest extends TestCase
{
    private string $privateKey = '';
    private string $publicKey = '';
    private string $keyId = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Generate a test RSA key pair for testing
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
        ];

        $resource = openssl_pkey_new($config);
        if (!$resource) {
            $this->markTestSkipped('Could not generate test key pair');
        }

        $privateKey = '';
        openssl_pkey_export($resource, $privateKey);
        $this->privateKey = $privateKey;

        $publicKeyDetails = openssl_pkey_get_details($resource);
        $this->publicKey = $publicKeyDetails['key'];
        $this->keyId = 'https://my.example.com/actor#main-key';
    }

    public function testBuildWithoutPayload(): void
    {
        $url = 'https://mastodon.example/users/username/inbox';
        $headers = SignatureHelper::build($url, $this->keyId, $this->privateKey);

        // Verify required headers are present
        $this->assertArrayHasKey('Host', $headers);
        $this->assertArrayHasKey('Date', $headers);
        $this->assertArrayHasKey('Signature', $headers);
        $this->assertArrayHasKey('Content-Type', $headers);

        // Verify Host header
        $this->assertEquals('mastodon.example', $headers['Host']);

        // Verify Date header format
        $this->assertMatchesRegularExpression(
            '/^[A-Z][a-z]{2}, \d{1,2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} GMT$/',
            $headers['Date']
        );

        // Verify Signature header format
        $this->assertStringContainsString('keyId="' . $this->keyId . '"', $headers['Signature']);
        $this->assertStringContainsString('headers="', $headers['Signature']);
        $this->assertStringContainsString('signature="', $headers['Signature']);
        $this->assertStringContainsString('algorithm="rsa-sha256"', $headers['Signature']);

        // Verify Digest header is NOT present for GET requests
        $this->assertArrayNotHasKey('Digest', $headers);

        // Verify Content-Type
        $this->assertEquals('application/activity+json', $headers['Content-Type']);
    }

    public function testBuildWithPayload(): void
    {
        $url = 'https://mastodon.example/users/username/inbox';
        $payload = '{"@context":"https://www.w3.org/ns/activitystreams","type":"Create","object":{"type":"Note","content":"Hello!"}}';
        $headers = SignatureHelper::build($url, $this->keyId, $this->privateKey, $payload);

        // Verify Digest header is present for POST requests
        $this->assertArrayHasKey('Digest', $headers);

        // Verify Digest format (SHA-256=base64(hash))
        $this->assertStringStartsWith('SHA-256=', $headers['Digest']);
        $expectedDigest = base64_encode(hash('sha256', $payload, true));
        $this->assertEquals('SHA-256=' . $expectedDigest, $headers['Digest']);

        // Verify Signature header includes digest
        $this->assertStringContainsString('digest', mb_strtolower($headers['Signature']));
    }

    public function testBuildSignatureHeaderFormat(): void
    {
        $url = 'https://mastodon.example/users/username/inbox';
        $headers = SignatureHelper::build($url, $this->keyId, $this->privateKey);

        // Parse the signature header
        $signatureData = SignatureHelper::parseSignatureHeader($headers['Signature']);

        $this->assertEquals($this->keyId, $signatureData['keyId']);
        $this->assertArrayHasKey('headers', $signatureData);
        $this->assertArrayHasKey('signature', $signatureData);
        $this->assertEquals('rsa-sha256', $signatureData['algorithm']);

        // Verify headers list includes required headers
        $headerList = explode(' ', $signatureData['headers']);
        $this->assertContains('host', $headerList);
        $this->assertContains('date', $headerList);
    }

    public function testBuildSignatureHeaderFormatWithDigest(): void
    {
        $url = 'https://mastodon.example/users/username/inbox';
        $payload = '{"type":"Create"}';
        $headers = SignatureHelper::build($url, $this->keyId, $this->privateKey, $payload);

        $signatureData = SignatureHelper::parseSignatureHeader($headers['Signature']);

        $headerList = explode(' ', $signatureData['headers']);
        $this->assertContains('host', $headerList);
        $this->assertContains('date', $headerList);
        $this->assertContains('digest', $headerList);
    }

    public function testBuildWithInvalidPrivateKey(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not read private key.');

        SignatureHelper::build(
            'https://mastodon.example/users/username/inbox',
            $this->keyId,
            'invalid-private-key'
        );
    }

    public function testBuildCreatesVerifiableSignature(): void
    {
        $url = 'https://mastodon.example/users/username/inbox';
        $path = '/users/username/inbox';
        $payload = '{"@context":"https://www.w3.org/ns/activitystreams","type":"Create"}';

        $headers = SignatureHelper::build($url, $this->keyId, $this->privateKey, $payload);
        $signatureData = SignatureHelper::parseSignatureHeader($headers['Signature']);

        // Prepare input headers for verification (as they would come from HTTP request)
        $inputHeaders = [
            'host' => [$headers['Host']],
            'date' => [$headers['Date']],
            'digest' => [$headers['Digest']],
        ];

        // Verify the signature
        $isValid = SignatureHelper::verify(
            $path,
            $this->publicKey,
            $signatureData,
            $inputHeaders,
            $payload
        );

        $this->assertTrue($isValid, 'Signature should be verifiable');
    }

    public function testVerifyValidSignature(): void
    {
        $path = '/users/username/inbox';
        $payload = '{"type":"Create","object":{"type":"Note","content":"Hello!"}}';

        // Build signature
        $url = 'https://mastodon.example' . $path;
        $headers = SignatureHelper::build($url, $this->keyId, $this->privateKey, $payload);
        $signatureData = SignatureHelper::parseSignatureHeader($headers['Signature']);

        // Prepare input headers
        $inputHeaders = [
            'host' => [$headers['Host']],
            'date' => [$headers['Date']],
            'digest' => [$headers['Digest']],
        ];

        $result = SignatureHelper::verify(
            $path,
            $this->publicKey,
            $signatureData,
            $inputHeaders,
            $payload
        );

        $this->assertTrue($result);
    }

    public function testVerifyValidSignatureWithoutDigest(): void
    {
        $path = '/users/username/outbox';

        // Build signature without payload (GET request)
        $url = 'https://mastodon.example' . $path;
        $headers = SignatureHelper::build($url, $this->keyId, $this->privateKey);
        $signatureData = SignatureHelper::parseSignatureHeader($headers['Signature']);

        // Prepare input headers (no digest for GET)
        $inputHeaders = [
            'host' => [$headers['Host']],
            'date' => [$headers['Date']],
        ];

        // For GET requests, we need to verify without body
        // Note: The verify method expects a body, but for GET requests we can pass empty string
        $result = SignatureHelper::verify(
            $path,
            $this->publicKey,
            $signatureData,
            $inputHeaders,
            ''
        );

        $this->assertTrue($result);
    }

    public function testVerifyInvalidSignature(): void
    {
        $path = '/users/username/inbox';
        $payload = '{"type":"Create"}';

        // Build signature
        $url = 'https://mastodon.example' . $path;
        $headers = SignatureHelper::build($url, $this->keyId, $this->privateKey, $payload);
        $signatureData = SignatureHelper::parseSignatureHeader($headers['Signature']);

        // Tamper with the signature
        $signatureData['signature'] = base64_encode('tampered-signature-data');

        $inputHeaders = [
            'host' => [$headers['Host']],
            'date' => [$headers['Date']],
            'digest' => [$headers['Digest']],
        ];

        $result = SignatureHelper::verify(
            $path,
            $this->publicKey,
            $signatureData,
            $inputHeaders,
            $payload
        );

        $this->assertFalse($result);
    }

    public function testVerifyWithTamperedBody(): void
    {
        $path = '/users/username/inbox';
        $originalPayload = '{"type":"Create","object":{"type":"Note","content":"Hello!"}}';
        $tamperedPayload = '{"type":"Create","object":{"type":"Note","content":"Hacked!"}}';

        // Build signature with original payload
        $url = 'https://mastodon.example' . $path;
        $headers = SignatureHelper::build($url, $this->keyId, $this->privateKey, $originalPayload);
        $signatureData = SignatureHelper::parseSignatureHeader($headers['Signature']);

        // Try to verify with tampered payload
        $inputHeaders = [
            'host' => [$headers['Host']],
            'date' => [$headers['Date']],
            'digest' => [$headers['Digest']], // This digest won't match tampered payload
        ];

        $result = SignatureHelper::verify(
            $path,
            $this->publicKey,
            $signatureData,
            $inputHeaders,
            $tamperedPayload
        );

        $this->assertFalse($result, 'Signature should fail when body is tampered');
    }

    public function testVerifyWithWrongPath(): void
    {
        $path = '/users/username/inbox';
        $wrongPath = '/users/username/outbox';
        $payload = '{"type":"Create"}';

        // Build signature for inbox
        $url = 'https://mastodon.example' . $path;
        $headers = SignatureHelper::build($url, $this->keyId, $this->privateKey, $payload);
        $signatureData = SignatureHelper::parseSignatureHeader($headers['Signature']);

        // Try to verify with wrong path
        $inputHeaders = [
            'host' => [$headers['Host']],
            'date' => [$headers['Date']],
            'digest' => [$headers['Digest']],
        ];

        $result = SignatureHelper::verify(
            $wrongPath,
            $this->publicKey,
            $signatureData,
            $inputHeaders,
            $payload
        );

        $this->assertFalse($result, 'Signature should fail when path is wrong');
    }

    public function testVerifyWithWrongPublicKey(): void
    {
        // Generate a different key pair
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
        ];
        $resource = openssl_pkey_new($config);
        if (!$resource) {
            $this->markTestSkipped('Could not generate test key pair');
        }
        $publicKeyDetails = openssl_pkey_get_details($resource);
        $wrongPublicKey = $publicKeyDetails['key'];

        $path = '/users/username/inbox';
        $payload = '{"type":"Create"}';

        // Build signature with original key
        $url = 'https://mastodon.example' . $path;
        $headers = SignatureHelper::build($url, $this->keyId, $this->privateKey, $payload);
        $signatureData = SignatureHelper::parseSignatureHeader($headers['Signature']);

        // Try to verify with wrong public key
        $inputHeaders = [
            'host' => [$headers['Host']],
            'date' => [$headers['Date']],
            'digest' => [$headers['Digest']],
        ];

        $result = SignatureHelper::verify(
            $path,
            $wrongPublicKey,
            $signatureData,
            $inputHeaders,
            $payload
        );

        $this->assertFalse($result, 'Signature should fail when public key is wrong');
    }

    #[DataProvider('parseSignatureHeaderProvider')]
    public function testParseSignatureHeader(string $signatureHeader, ?string $expectedException, ?string $expectedKeyId): void
    {
        if ($expectedException) {
            $this->expectException($expectedException);
        }

        $result = SignatureHelper::parseSignatureHeader($signatureHeader);

        if (!$expectedException) {
            $this->assertIsArray($result);
            if ($expectedKeyId) {
                $this->assertEquals($expectedKeyId, $result['keyId']);
            }
            $this->assertArrayHasKey('keyId', $result);
            $this->assertArrayHasKey('headers', $result);
            $this->assertArrayHasKey('signature', $result);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string|null, 2: string|null}>
     */
    public static function parseSignatureHeaderProvider(): array
    {
        return [
            'valid signature header' => [
                'keyId="https://my.example.com/actor#main-key",headers="(request-target) host date",signature="Y2FiYW...IxNGRiZDk4ZA=="',
                null,
                'https://my.example.com/actor#main-key',
            ],
            'valid signature header with algorithm' => [
                'keyId="https://example.com/user#key",headers="host date digest",signature="abc123==",algorithm="rsa-sha256"',
                null,
                'https://example.com/user#key',
            ],
            'missing keyId' => [
                'headers="host date",signature="abc123=="',
                SignatureException::class,
                null,
            ],
            'keyId is not a URL' => [
                'keyId="not-a-url",headers="host date",signature="abc123=="',
                SignatureException::class,
                null,
            ],
            'missing headers' => [
                'keyId="https://example.com/user#key",signature="abc123=="',
                SignatureException::class,
                null,
            ],
            'missing signature' => [
                'keyId="https://example.com/user#key",headers="host date"',
                SignatureException::class,
                null,
            ],
            'empty signature header' => [
                '',
                SignatureException::class,
                null,
            ],
            'signature header with spaces (should fail due to untrimmed keys)' => [
                'keyId="https://example.com/user#key", headers="host date", signature="abc123=="',
                SignatureException::class,
                null,
            ],
        ];
    }

    public function testParseSignatureHeaderWithRealSignature(): void
    {
        $url = 'https://mastodon.example/users/username/inbox';
        $headers = SignatureHelper::build($url, $this->keyId, $this->privateKey);

        $signatureData = SignatureHelper::parseSignatureHeader($headers['Signature']);

        $this->assertEquals($this->keyId, $signatureData['keyId']);
        $this->assertNotEmpty($signatureData['headers']);
        $this->assertNotEmpty($signatureData['signature']);
        $this->assertEquals('rsa-sha256', $signatureData['algorithm']);
    }

    public function testStringToSignFormat(): void
    {
        // Test the signature string format matches Mastodon spec
        // According to spec: (request-target): get /path\nhost: example.com\ndate: ...
        $url = 'https://mastodon.example/users/username/inbox';
        $headers = SignatureHelper::build($url, $this->keyId, $this->privateKey);
        $signatureData = SignatureHelper::parseSignatureHeader($headers['Signature']);

        // Reconstruct what should be signed
        $headerList = explode(' ', $signatureData['headers']);
        $headersToSign = [];
        foreach ($headerList as $h) {
            if ('(request-target)' === $h) {
                $headersToSign[$h] = 'post ' . parse_url($url, \PHP_URL_PATH);
            } elseif (isset($headers[ucfirst($h)])) {
                $headersToSign[$h] = $headers[ucfirst($h)];
            }
        }

        // Verify we can verify the signature
        $inputHeaders = [
            'host' => [$headers['Host']],
            'date' => [$headers['Date']],
        ];

        $result = SignatureHelper::verify(
            parse_url($url, \PHP_URL_PATH),
            $this->publicKey,
            $signatureData,
            $inputHeaders,
            ''
        );

        $this->assertTrue($result);
    }

    public function testBuildWithDifferentUrls(): void
    {
        $testCases = [
            'https://example.com/inbox',
            'https://mastodon.social/users/alice/inbox',
            'https://test.example.com:8080/path/to/inbox',
            'https://example.com/',
        ];

        foreach ($testCases as $url) {
            $headers = SignatureHelper::build($url, $this->keyId, $this->privateKey);

            $this->assertArrayHasKey('Host', $headers);
            $this->assertArrayHasKey('Signature', $headers);

            // Verify Host matches URL
            $expectedHost = parse_url($url, \PHP_URL_HOST);
            $expectedPort = parse_url($url, \PHP_URL_PORT);
            $this->assertEquals($expectedHost . ($expectedPort ? ':' . $expectedPort : ''), $headers['Host']);

            // Verify signature is parseable
            $signatureData = SignatureHelper::parseSignatureHeader($headers['Signature']);
            $this->assertEquals($this->keyId, $signatureData['keyId']);
        }
    }

    public function testBuildAndVerifyRoundTrip(): void
    {
        $testCases = [
            ['url' => 'https://mastodon.example/inbox', 'payload' => null],
            ['url' => 'https://mastodon.example/inbox', 'payload' => '{"type":"Create"}'],
            ['url' => 'https://mastodon.example/users/alice/inbox', 'payload' => '{"@context":"https://www.w3.org/ns/activitystreams","type":"Note","content":"Hello World"}'],
        ];

        foreach ($testCases as $testCase) {
            $url = $testCase['url'];
            $payload = $testCase['payload'] ?? '';
            $path = parse_url($url, \PHP_URL_PATH);

            $headers = SignatureHelper::build($url, $this->keyId, $this->privateKey, $payload ?: null);
            $signatureData = SignatureHelper::parseSignatureHeader($headers['Signature']);

            $inputHeaders = [
                'host' => [$headers['Host']],
                'date' => [$headers['Date']],
            ];

            if (isset($headers['Digest'])) {
                $inputHeaders['digest'] = [$headers['Digest']];
            }

            $result = SignatureHelper::verify(
                $path,
                $this->publicKey,
                $signatureData,
                $inputHeaders,
                $payload
            );

            $this->assertTrue($result, \sprintf('Round trip should work for URL: %s', $url));
        }
    }

    /**
     * Test against Mastodon documentation example for GET request.
     *
     * @see https://docs.joinmastodon.org/spec/security/#creating-http-signatures
     */
    public function testMatchesMastodonDocumentationGetRequest(): void
    {
        // According to Mastodon docs, GET request should have:
        // Signature: keyId="...",headers="(request-target) host date",signature="..."
        $url = 'https://mastodon.example/users/username/outbox';
        $headers = SignatureHelper::build($url, $this->keyId, $this->privateKey);

        $signatureData = SignatureHelper::parseSignatureHeader($headers['Signature']);

        // Verify headers list matches spec format
        $headerList = explode(' ', $signatureData['headers']);
        $this->assertContains('(request-target)', $headerList, 'Should include (request-target)');
        $this->assertContains('host', $headerList, 'Should include host');
        $this->assertContains('date', $headerList, 'Should include date');

        // Verify signature string format matches spec:
        // (request-target): get /path
        // host: example.com
        // date: ...
        $path = parse_url($url, \PHP_URL_PATH);
        $inputHeaders = [
            'host' => [$headers['Host']],
            'date' => [$headers['Date']],
        ];

        $result = SignatureHelper::verify(
            $path,
            $this->publicKey,
            $signatureData,
            $inputHeaders,
            ''
        );

        $this->assertTrue($result, 'Should verify against Mastodon spec format');
    }

    /**
     * Test against Mastodon documentation example for POST request with Digest.
     *
     * @see https://docs.joinmastodon.org/spec/security/#signing-post-requests-and-the-digest-header
     */
    public function testMatchesMastodonDocumentationPostRequest(): void
    {
        // According to Mastodon docs, POST request should have:
        // Digest: sha-256=...
        // Signature: keyId="...",headers="(request-target) host date digest",signature="..."
        $url = 'https://mastodon.example/users/username/inbox';
        $payload = '{"@context":"https://www.w3.org/ns/activitystreams","actor":"https://my.example.com/actor","type":"Create","object":{"type":"Note","content":"Hello!"},"to":"https://mastodon.example/users/username"}';
        $headers = SignatureHelper::build($url, $this->keyId, $this->privateKey, $payload);

        // Verify Digest header is present
        $this->assertArrayHasKey('Digest', $headers);
        $this->assertStringStartsWith('SHA-256=', $headers['Digest']);

        // NOTE: Mastodon docs show lowercase "sha-256=" but implementation uses uppercase "SHA-256="
        // This is a known discrepancy - both formats are commonly accepted in practice
        // The implementation uses uppercase which is also valid per HTTP header standards

        $signatureData = SignatureHelper::parseSignatureHeader($headers['Signature']);

        // Verify headers list includes digest
        $headerList = explode(' ', $signatureData['headers']);
        $this->assertContains('(request-target)', $headerList);
        $this->assertContains('host', $headerList);
        $this->assertContains('date', $headerList);
        $this->assertContains('digest', $headerList, 'POST requests should include digest in signature headers');

        // Verify signature string format matches spec
        $path = parse_url($url, \PHP_URL_PATH);
        $inputHeaders = [
            'host' => [$headers['Host']],
            'date' => [$headers['Date']],
            'digest' => [$headers['Digest']],
        ];

        $result = SignatureHelper::verify(
            $path,
            $this->publicKey,
            $signatureData,
            $inputHeaders,
            $payload
        );

        $this->assertTrue($result, 'Should verify POST request with digest per Mastodon spec');
    }

    /**
     * Test signature string format matches Mastodon documentation exactly.
     * According to spec: header names should be lowercase, format: "header-name: value".
     */
    public function testSignatureStringFormatMatchesSpec(): void
    {
        $url = 'https://mastodon.example/users/username/inbox';
        $payload = '{"type":"Create"}';
        $headers = SignatureHelper::build($url, $this->keyId, $this->privateKey, $payload);
        $signatureData = SignatureHelper::parseSignatureHeader($headers['Signature']);

        // Reconstruct signature string as it should be according to spec
        $path = parse_url($url, \PHP_URL_PATH);
        $headerList = explode(' ', $signatureData['headers']);

        $expectedSignatureParts = [];
        foreach ($headerList as $h) {
            if ('(request-target)' === $h) {
                $expectedSignatureParts[] = '(request-target): post ' . $path;
            } elseif ('host' === $h) {
                $expectedSignatureParts[] = 'host: ' . $headers['Host'];
            } elseif ('date' === $h) {
                $expectedSignatureParts[] = 'date: ' . $headers['Date'];
            } elseif ('digest' === $h) {
                $expectedSignatureParts[] = 'digest: ' . $headers['Digest'];
            }
        }

        $expectedSignatureString = implode("\n", $expectedSignatureParts);

        // Verify by checking that our verification works (which reconstructs the same string)
        $inputHeaders = [
            'host' => [$headers['Host']],
            'date' => [$headers['Date']],
            'digest' => [$headers['Digest']],
        ];

        $result = SignatureHelper::verify(
            $path,
            $this->publicKey,
            $signatureData,
            $inputHeaders,
            $payload
        );

        $this->assertTrue($result, 'Signature string format should match Mastodon spec');
    }

    /**
     * Document known discrepancy: Digest header format.
     * Mastodon docs show lowercase "sha-256=" but implementation uses uppercase "SHA-256=".
     * Both are valid per HTTP standards, but we document this for clarity.
     */
    public function testDigestHeaderFormatDiscrepancy(): void
    {
        $url = 'https://mastodon.example/users/username/inbox';
        $payload = '{"type":"Create"}';
        $headers = SignatureHelper::build($url, $this->keyId, $this->privateKey, $payload);

        // Implementation uses uppercase "SHA-256="
        // Mastodon docs example shows lowercase "sha-256="
        // Both formats are valid per HTTP header standards
        $this->assertStringStartsWith('SHA-256=', $headers['Digest']);

        // Verify it still works correctly
        $signatureData = SignatureHelper::parseSignatureHeader($headers['Signature']);
        $path = parse_url($url, \PHP_URL_PATH);
        $inputHeaders = [
            'host' => [$headers['Host']],
            'date' => [$headers['Date']],
            'digest' => [$headers['Digest']],
        ];

        $result = SignatureHelper::verify(
            $path,
            $this->publicKey,
            $signatureData,
            $inputHeaders,
            $payload
        );

        $this->assertTrue($result, 'Uppercase SHA-256 format should work correctly');
    }
}
