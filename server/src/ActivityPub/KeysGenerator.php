<?php

declare(strict_types=1);

namespace App\ActivityPub;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class KeysGenerator
{
    public function __construct(
        #[Autowire('%env(APP_ENV)%')]
        private string $appEnv,
    ) {
    }

    /**
     * @return array{public: string, private: string}
     */
    public function generate(): array
    {
        return self::doGenerate('test' === $this->appEnv ? 512 : 4096);
    }

    /**
     * @return array{public: string, private: string}
     */
    public static function doctrineMigrationHelper(): array
    {
        return self::doGenerate(512);
    }

    /**
     * @return array{public: string, private: string}
     */
    private static function doGenerate(int $keySize): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => $keySize,
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
        ]);

        if (false === $resource) {
            throw new \RuntimeException('Failed to generate RSA key pair: ' . openssl_error_string());
        }

        $privateKeyPem = '';
        if (!openssl_pkey_export($resource, $privateKeyPem)) {
            throw new \RuntimeException('Failed to export private key: ' . openssl_error_string());
        }

        $details = openssl_pkey_get_details($resource);
        if (false === $details || !isset($details['key'])) {
            throw new \RuntimeException('Failed to get public key details: ' . openssl_error_string());
        }

        return [
            'public' => $details['key'],
            'private' => $privateKeyPem,
        ];
    }
}
