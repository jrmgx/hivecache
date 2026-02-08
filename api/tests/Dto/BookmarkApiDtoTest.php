<?php

namespace App\Tests\Dto;

use App\Dto\BookmarkApiDto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BookmarkApiDtoTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    #[DataProvider('validUrlProvider')]
    public function testUrlValidationWithValidUrls(string $url): void
    {
        $dto = new BookmarkApiDto();
        $dto->title = 'Test Bookmark';
        $dto->url = $url;

        $violations = $this->validator->validate($dto, groups: ['bookmark:create']);

        $urlViolations = [];
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'url') {
                $urlViolations[] = $violation;
            }
        }
        $this->assertCount(0, $urlViolations, sprintf('URL "%s" should be valid', $url));
    }

    #[DataProvider('invalidUrlProvider')]
    public function testUrlValidationWithInvalidUrls(string $url, string $expectedMessage): void
    {
        $dto = new BookmarkApiDto();
        $dto->title = 'Test Bookmark';
        $dto->url = $url;

        $violations = $this->validator->validate($dto, groups: ['bookmark:create']);

        $urlViolations = [];
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'url') {
                $urlViolations[] = $violation;
            }
        }
        $this->assertGreaterThan(0, count($urlViolations), sprintf('URL "%s" should be invalid: %s', $url, $expectedMessage));
    }

    public function testUrlValidationWithNull(): void
    {
        $dto = new BookmarkApiDto();
        $dto->title = 'Test Bookmark';
        $dto->url = null;

        $violations = $this->validator->validate($dto, groups: ['bookmark:create']);

        $hasUrlViolation = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'url') {
                $hasUrlViolation = true;
                break;
            }
        }
        $this->assertTrue($hasUrlViolation, 'Should have a violation for url property');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validUrlProvider(): array
    {
        return [
            'http url' => ['http://example.com'],
            'https url' => ['https://test.org'],
            'ftp url' => ['ftp://server.net'],
            's3' => ['s3://some.server/'],
            'hyphen' => ['https://valid-url.com/'],
            'japan ascii' => ['http://xn--r8jz45g.xn--zckzah/'],
            'japan' => ['http://例え.テスト/'],
            'web extension' => ['moz-extension://6e4c76c9-960f-4a0b-bf97-10e00d83ba82/options.html'],
            'url with numeric scheme' => ['http1://example.com'],
            'url with alphanumeric domain' => ['https://test123.org'],
            'url with alphanumeric tld' => ['http://example.com1'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidUrlProvider(): array
    {
        return [
            'missing scheme' => ['example.com', 'regex'],
            'missing domain' => ['http://', 'regex'],
            'missing tld' => ['http://example', 'regex'],
            'missing protocol separator' => ['http:example.com', 'regex'],
            'empty string' => ['', 'not blank'],
        ];
    }
}
