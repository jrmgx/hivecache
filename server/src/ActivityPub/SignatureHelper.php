<?php

namespace App\ActivityPub;

use App\ActivityPub\Exception\SignatureException;

/**
 * @see https://docs.joinmastodon.org/spec/security/#digest
 * Implemented with inspiration from aaronpk and pixelfed
 */
final readonly class SignatureHelper
{
    /**
     * @return array<string, string> List of headers to apply to the request
     */
    public static function build(
        string $url,
        string $keyId,
        string $privateKey,
        ?string $payload = null,
    ): array {
        $digest = null;
        if ($payload) {
            $digest = base64_encode(hash('sha256', $payload, true));
        }

        $method = 'post';
        $parseUrl = parse_url($url) ?: [];
        $host = $parseUrl['host'] ??
            throw new \LogicException('Host not found on given url.');
        $port = $parseUrl['port'] ?? null;
        $path = $parseUrl['path'] ?? '';
        $headers = [
            '(request-target)' => $method . ' ' . $path,
            'Host' => $host . ($port ? ':' . $port : ''),
            'Date' => new \DateTimeImmutable()->format('D, d M Y H:i:s \G\M\T'),
        ];

        if ($digest) {
            $headers['Digest'] = 'SHA-256=' . $digest;
        }

        $stringToSign = self::stringToSign($headers);
        $signedHeaders = implode(' ', array_map(strtolower(...), array_keys($headers)));

        $key = openssl_pkey_get_private($privateKey);
        if (!$key) {
            throw new \RuntimeException('Could not read private key.');
        }
        openssl_sign($stringToSign, $signature, $key, \OPENSSL_ALGO_SHA256);
        $signature = base64_encode($signature);

        $signatureHeaders = [
            'keyId="' . $keyId . '"',
            'headers="' . $signedHeaders . '"',
            'algorithm="rsa-sha256"',
            'signature="' . $signature . '"',
        ];
        unset($headers['(request-target)']);

        $headers['Signature'] = implode(',', $signatureHeaders);
        $headers['Content-Type'] = 'application/activity+json';

        return $headers;
    }

    /**
     * @param array<string, string>             $signatureData
     * @param array<string, array<int, string>> $inputHeaders
     */
    public static function verify(
        string $path,
        string $publicKey,
        array $signatureData,
        array $inputHeaders,
        string $body,
    ): bool {
        $digest = 'SHA-256=' . base64_encode(hash('sha256', $body, true));
        $headersToSign = [];
        foreach (explode(' ', $signatureData['headers']) as $h) {
            if ('(request-target)' === $h) {
                $headersToSign[$h] = 'post ' . $path;
            } elseif ('digest' === $h) {
                $headersToSign[$h] = $digest;
            } elseif (isset($inputHeaders[$h][0])) {
                $headersToSign[$h] = $inputHeaders[$h][0];
            }
        }
        $signingString = self::stringToSign($headersToSign);

        return 1 === openssl_verify($signingString, base64_decode($signatureData['signature']), $publicKey, \OPENSSL_ALGO_SHA256);
    }

    /**
     * @return array<string, string>
     */
    public static function parseSignatureHeader(string $signature): array
    {
        $parts = explode(',', $signature);
        $signatureData = [];

        foreach ($parts as $part) {
            if (preg_match('/(.+)="(.+)"/', $part, $match)) {
                $signatureData[$match[1]] = $match[2];
            }
        }

        if (!isset($signatureData['keyId'])) {
            throw new SignatureException('No keyId was found in the signature header.');
        }

        if (!filter_var($signatureData['keyId'], \FILTER_VALIDATE_URL)) {
            throw new SignatureException('keyId is not a URL');
        }

        if (!isset($signatureData['headers']) || !isset($signatureData['signature'])) {
            throw new SignatureException('Signature is missing headers or signature parts');
        }

        return $signatureData;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function stringToSign(array $headers): string
    {
        return implode("\n", array_map(fn ($k, $v) => mb_strtolower($k) . ': ' . $v, array_keys($headers), $headers));
    }
}
