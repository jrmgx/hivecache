<?php

namespace App\Api\Helper;

final readonly class UrlHelper
{
    /** @var list<string> */
    private const array TRACKING_QUERY_PREFIXES = [
        'utm_',
        'hsa_',
        'gad_',
        'mtm_',
        'pk_',
    ];

    /** @var array<string, true> */
    private const array TRACKING_QUERY_EXACT = [
        'gclid' => true,
        'gbraid' => true,
        'wbraid' => true,
        'fbclid' => true,
        'yclid' => true,
        'msclkid' => true,
        'twclid' => true,
        'igshid' => true,
        'mc_cid' => true,
        'si' => true,
        'gc_id' => true,
        'h_ad_id' => true,
        '_ga' => true,
        '_gl' => true,
        'mkt_tok' => true,
        'vero_id' => true,
        'li_fat_id' => true,
        's_kwcid' => true,
        'dclid' => true,
        'srsltid' => true,
        'oly_anon_id' => true,
        'oly_enc_id' => true,
        'spm' => true,
    ];

    /**
     * Aggressively normalize url:
     * - remove scheme
     * - remove user/password/port
     * - remove known tracking query params
     * - sort params
     * - remove fragment
     */
    public static function normalize(string $url): string
    {
        $parts = parse_url($url);

        $queryParams = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $queryParams);
            $queryParams = self::removeTrackingQueryParams($queryParams);
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
     * Returns the URL with tracking query parameters removed, preserving scheme, host, path, non-tracking query, and fragment.
     * Unparseable URLs are returned unchanged.
     */
    public static function sanitizeStoredUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $queryParams = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $queryParams);
            $queryParams = self::removeTrackingQueryParams($queryParams);
            ksort($queryParams);
        }

        $newQuery = http_build_query($queryParams);

        $scheme = strtolower($parts['scheme']);
        $result = $scheme . '://';

        if (isset($parts['user'])) {
            $result .= $parts['user'];
            if (isset($parts['pass'])) {
                $result .= ':' . $parts['pass'];
            }
            $result .= '@';
        }

        $result .= $parts['host'];

        if (isset($parts['port'])) {
            $result .= ':' . $parts['port'];
        }

        if (isset($parts['path']) && '' !== $parts['path']) {
            $result .= $parts['path'];
        }

        if ('' !== $newQuery) {
            $result .= '?' . $newQuery;
        }

        if (isset($parts['fragment'])) {
            $result .= '#' . $parts['fragment'];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $queryParams
     *
     * @return array<string, mixed>
     */
    public static function removeTrackingQueryParams(array $queryParams): array
    {
        return array_filter(
            $queryParams,
            static fn ($_, string $k): bool => !self::isTrackingQueryKey($k),
            \ARRAY_FILTER_USE_BOTH
        );
    }

    private static function isTrackingQueryKey(string $key): bool
    {
        $lower = strtolower($key);
        if (isset(self::TRACKING_QUERY_EXACT[$lower])) {
            return true;
        }

        foreach (self::TRACKING_QUERY_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Opinionated: remove `www` and `m` (for mobile most of the time) from domain to normalize a bit more.
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
