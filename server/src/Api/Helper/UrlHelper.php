<?php

namespace App\Api\Helper;

final readonly class UrlHelper
{
    /**
     * Aggressively normalize url:
     * - remove scheme
     * - remove user/password/port
     * - remove utm_ params
     * - sort params
     * - remove fragment
     */
    public static function normalize(string $url): string
    {
        $parts = parse_url($url);

        $queryParams = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $queryParams);
            $queryParams = array_filter(
                $queryParams,
                fn ($v, $k) => !str_starts_with((string) $k, 'utm_'),
                \ARRAY_FILTER_USE_BOTH
            );
            ksort($queryParams);
        }

        $newQuery = http_build_query($queryParams);

        $host = $parts['host'] ?? '';
        $host = (string) preg_replace('`^(www|m)\.`', '', $host);

        $normalizedUrl = $host . ($parts['path'] ?? '');

        if ('' !== $newQuery) {
            $normalizedUrl .= '?' . $newQuery;
        }

        return mb_trim($normalizedUrl, '/');
    }

    /**
     * Opinionated: remove `www` and `m` (for mobile most of the time) from domain to normalize a bit.
     */
    public static function calculateDomain(string $url): string
    {
        $host = parse_url($url, \PHP_URL_HOST);

        if (!$host) {
            return '';
        }

        return (string) preg_replace('`^(www|m)\.`', '', $host);
    }
}
