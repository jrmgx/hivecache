<?php

namespace App\Tests\Api\Helper;

use App\Api\Helper\RequestHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class RequestHelperTest extends TestCase
{
    #[DataProvider('acceptsProvider')]
    public function testAccepts(string $acceptHeader, array $types, bool $expected): void
    {
        $request = Request::create('/test');
        if ('' !== $acceptHeader) {
            $request->headers->set('Accept', $acceptHeader);
        }

        $result = RequestHelper::accepts($request, $types);
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array<string, array{0: string, 1: array<int, string>, 2: bool}>
     */
    public static function acceptsProvider(): array
    {
        return [
            'matching single type' => [
                'application/json', ['application/json'], true,
            ],
            'matching type in multiple types' => [
                'application/json', ['application/json', 'text/html'], true,
            ],
            'matching first type in Accept header' => [
                'application/json,text/html', ['application/json'], true,
            ],
            'matching second type in Accept header' => [
                'application/json,text/html', ['text/html'], true,
            ],
            'matching multiple types in Accept header' => [
                'application/json,text/html,application/xml', ['application/json', 'text/html'], true,
            ],
            'non-matching type' => [
                'application/json', ['text/html'], false,
            ],
            'no Accept header' => [
                '', ['application/json'], false,
            ],
            'empty types array' => [
                'application/json', [], false,
            ],
            'empty Accept header with empty types' => [
                '', [], false,
            ],
            'case sensitive match' => [
                'application/JSON', ['application/json'], false,
            ],
            'multiple types in request, one matches' => [
                'application/xml,application/json', ['application/json', 'text/plain'], true,
            ],
            'multiple types in request, none match' => [
                'application/xml,text/css', ['application/json', 'text/html'], false,
            ],
            'Accept header with spaces (spaces are trimmed)' => [
                'application/json, text/html', ['text/html'], true,
            ],
            'Accept header with quality values (quality values are stripped)' => [
                'application/json;q=0.9', ['application/json'], true,
            ],
            'Accept header with quality values (exact match with quality fails)' => [
                'application/json;q=0.9', ['application/json;q=0.9'], false,
            ],
            'Accept header with multiple quality values' => [
                'application/json;q=0.9, text/html;q=0.8', ['text/html'], true,
            ],
            'Accept header with quality values matching first type' => [
                'application/json;q=0.9, text/html;q=0.8', ['application/json'], true,
            ],
        ];
    }

    public function testAcceptsWithExactMatch(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Accept', 'application/json');

        $result = RequestHelper::accepts($request, ['application/json']);
        $this->assertTrue($result);
    }

    public function testAcceptsWithoutAcceptHeader(): void
    {
        $request = Request::create('/test');

        $result = RequestHelper::accepts($request, ['application/json']);
        $this->assertFalse($result);
    }

    public function testAcceptsWithMultipleAcceptValues(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Accept', 'application/json,text/html,application/xml');

        $result = RequestHelper::accepts($request, ['text/html']);
        $this->assertTrue($result);
    }

    public function testAcceptsWithSpacesInAcceptHeader(): void
    {
        // Symfony's getAcceptableContentTypes() properly trims spaces
        $request = Request::create('/test');
        $request->headers->set('Accept', 'application/json, text/html');

        $result = RequestHelper::accepts($request, ['text/html']);
        $this->assertTrue($result, 'Spaces in Accept header are properly trimmed');
    }

    public function testAcceptsWithQualityValues(): void
    {
        // Symfony's getAcceptableContentTypes() strips quality values
        $request = Request::create('/test');
        $request->headers->set('Accept', 'application/json;q=0.9, text/html;q=0.8');

        $result = RequestHelper::accepts($request, ['application/json']);
        $this->assertTrue($result, 'Quality values are stripped, so base content type matches');
    }

    public function testAcceptsWithNoMatch(): void
    {
        $request = Request::create('/test');
        $request->headers->set('Accept', 'application/xml');

        $result = RequestHelper::accepts($request, ['application/json', 'text/html']);
        $this->assertFalse($result);
    }
}
