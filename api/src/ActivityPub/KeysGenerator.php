<?php

declare(strict_types=1);

namespace App\ActivityPub;

use phpseclib3\Crypt\RSA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class KeysGenerator
{
    public function __construct(
        #[Autowire('%env(APP_ENV)%')]
        private string $appEnv,
    ) {
    }

    public function generate(): RSA\PrivateKey
    {
        return RSA::createKey('test' === $this->appEnv ? 512 : 4096);
    }
}
