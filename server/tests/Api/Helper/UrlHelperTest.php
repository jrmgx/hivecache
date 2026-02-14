<?php

namespace App\Tests\Api\Helper;

use App\Api\Helper\UrlHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UrlHelperTest extends TestCase
{
    #[DataProvider('urlNormalizationProvider')]
    public function testNormalize(string $input, string $expected): void
    {
        $result = UrlHelper::normalize($input);
        $this->assertEquals($expected, $result);
    }

    #[DataProvider('urlNormalizationProvider')]
    public function testCalculateDomain(string $input, string $_): void
    {
        $result = UrlHelper::calculateDomain($input);
        $this->assertEquals('example.com', $result);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function urlNormalizationProvider(): array
    {
        return [
            'simple http url' => [
                'http://example.com',
                'example.com',
            ],
            'simple https url' => [
                'https://example.com',
                'example.com',
            ],
            'url with path' => [
                'https://example.com/path/to/page',
                'example.com/path/to/page',
            ],
            'url with path and www' => [
                'https://www.example.com/path/to/page',
                'example.com/path/to/page',
            ],
            'url with path and m (mobile)' => [
                'https://m.example.com/path/to/page',
                'example.com/path/to/page',
            ],
            'url with query params' => [
                'https://example.com/page?foo=bar&baz=qux',
                'example.com/page?baz=qux&foo=bar',
            ],
            'url with utm params' => [
                'https://example.com/page?utm_source=google&utm_medium=cpc&foo=bar',
                'example.com/page?foo=bar',
            ],
            'url with multiple utm params' => [
                'https://example.com/page?utm_source=google&utm_medium=cpc&utm_campaign=test&foo=bar',
                'example.com/page?foo=bar',
            ],
            'url with user and password' => [
                'https://user:password@example.com/page',
                'example.com/page',
            ],
            'url with port' => [
                'https://example.com:8080/page',
                'example.com/page',
            ],
            'url with fragment' => [
                'https://example.com/page#section',
                'example.com/page',
            ],
            'url with all components' => [
                'https://user:pass@example.com:8080/path?foo=bar&utm_source=test#fragment',
                'example.com/path?foo=bar',
            ],
            'url with sorted params' => [
                'https://example.com/page?zebra=last&apple=first&banana=middle',
                'example.com/page?apple=first&banana=middle&zebra=last',
            ],
            'url with empty query' => [
                'https://example.com/page?',
                'example.com/page',
            ],
            'url with only utm params' => [
                'https://example.com/page?utm_source=google&utm_medium=cpc',
                'example.com/page',
            ],
            'url with root path' => [
                'https://example.com/',
                'example.com',
            ],
            'url without path' => [
                'https://example.com',
                'example.com',
            ],
            'url with complex query params' => [
                'https://example.com/page?param1=value1&param2=value%20with%20spaces&param3=value+with+plus',
                'example.com/page?param1=value1&param2=value+with+spaces&param3=value+with+plus',
            ],
        ];
    }
}
