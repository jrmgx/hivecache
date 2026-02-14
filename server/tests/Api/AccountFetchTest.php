<?php

namespace App\Tests\Api;

use App\ActivityPub\AccountFetch;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AccountFetchTest extends KernelTestCase
{
    private AccountFetch $accountFetch;
    private string $defaultHost;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $container = self::getContainer();
        $this->accountFetch = $container->get(AccountFetch::class);
        $this->defaultHost = $container->getParameter('instanceHost');
    }

    #[DataProvider('parseUsernameWithInstanceProvider')]
    public function testParseUsernameWithInstance(string $input, string $expectedUsername, string $expectedInstance, ?string $exception = null): void
    {
        if ('{{INSTANCE_HOST}}' === $expectedInstance) {
            $expectedInstance = $this->defaultHost;
        }

        if ($exception) {
            $this->expectException($exception);
        }
        [$username, $instance] = $this->accountFetch->parseUsernameWithInstance($input);
        if (!$exception) {
            $this->assertEquals($expectedUsername, $username);
            $this->assertEquals($expectedInstance, $instance);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function parseUsernameWithInstanceProvider(): array
    {
        return [
            'username with instance' => [
                'username@example-one.com',
                'username',
                'example-one.com',
            ],
            'username with @ prefix and instance' => [
                '@username@example_one.com',
                'username',
                'example_one.com',
            ],
            'username only' => [
                'username',
                'username',
                '{{INSTANCE_HOST}}',
            ],
            'username with @ prefix only' => [
                '@username',
                'username',
                '{{INSTANCE_HOST}}',
            ],
            'username with multiple @ symbols' => [
                'user@name@example.com',
                'user',
                'name@example.com',
                BadRequestHttpException::class,
            ],
        ];
    }
}
