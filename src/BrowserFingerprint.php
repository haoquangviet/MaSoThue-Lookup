<?php

namespace HQV\Masothue;

/**
 * Browser Fingerprint Generator
 * Generates realistic browser fingerprints to avoid bot detection
 */
class BrowserFingerprint
{
    /**
     * Browser profiles with matching headers
     */
    private static array $browsers = [
        [
            'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'sec_ch_ua' => '"Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
            'platform' => 'Windows',
        ],
        [
            'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
            'sec_ch_ua' => '"Google Chrome";v="130", "Chromium";v="130", "Not_A Brand";v="24"',
            'platform' => 'Windows',
        ],
        [
            'ua' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'sec_ch_ua' => '"Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
            'platform' => 'macOS',
        ],
        [
            'ua' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
            'sec_ch_ua' => '"Google Chrome";v="130", "Chromium";v="130", "Not_A Brand";v="24"',
            'platform' => 'macOS',
        ],
        [
            'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0',
            'sec_ch_ua' => '"Microsoft Edge";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
            'platform' => 'Windows',
        ],
        [
            'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 Edg/130.0.0.0',
            'sec_ch_ua' => '"Microsoft Edge";v="130", "Chromium";v="130", "Not_A Brand";v="24"',
            'platform' => 'Windows',
        ],
        [
            'ua' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0',
            'sec_ch_ua' => '"Microsoft Edge";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
            'platform' => 'macOS',
        ],
        [
            'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0',
            'sec_ch_ua' => null,
            'platform' => 'Windows',
        ],
        [
            'ua' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:130.0) Gecko/20100101 Firefox/130.0',
            'sec_ch_ua' => null,
            'platform' => 'macOS',
        ],
        [
            'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0',
            'sec_ch_ua' => null,
            'platform' => 'Windows',
        ],
        [
            'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',
            'sec_ch_ua' => '"Google Chrome";v="129", "Chromium";v="129", "Not_A Brand";v="24"',
            'platform' => 'Windows',
        ],
        [
            'ua' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Safari/605.1.15',
            'sec_ch_ua' => null,
            'platform' => 'macOS',
        ],
    ];

    /**
     * Accept-Language variations
     */
    private static array $languages = [
        'vi-VN,vi;q=0.9,en-US;q=0.8,en;q=0.7',
        'vi-VN,vi;q=0.9,en;q=0.8',
        'vi,en-US;q=0.9,en;q=0.8',
        'vi-VN,vi;q=0.8,en-US;q=0.6,en;q=0.4',
        'en-US,en;q=0.9,vi-VN;q=0.8,vi;q=0.7',
        'vi-VN,vi;q=0.9,fr;q=0.8,en-US;q=0.7,en;q=0.6',
    ];

    /**
     * Referer variations (where the user "came from")
     */
    private static array $referers = [
        'https://www.google.com/',
        'https://www.google.com.vn/',
        'https://www.bing.com/',
        'https://masothue.com/',
        null, // direct visit
        null, // direct visit
    ];

    /**
     * Generate a random browser fingerprint
     *
     * @return array Associative array of HTTP headers
     */
    public static function generate(): array
    {
        $browser = self::$browsers[array_rand(self::$browsers)];
        $language = self::$languages[array_rand(self::$languages)];
        $referer = self::$referers[array_rand(self::$referers)];

        $isFirefox = strpos($browser['ua'], 'Firefox') !== false;
        $isSafari = strpos($browser['ua'], 'Safari') !== false && strpos($browser['ua'], 'Chrome') === false;

        $headers = [
            'User-Agent' => $browser['ua'],
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language' => $language,
            'Accept-Encoding' => 'gzip, deflate, br',
            'Cache-Control' => 'max-age=0',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'DNT' => (string) random_int(0, 1),
        ];

        // Chromium-based browsers send Sec-Ch-Ua headers
        if ($browser['sec_ch_ua'] !== null) {
            $headers['Sec-Ch-Ua'] = $browser['sec_ch_ua'];
            $headers['Sec-Ch-Ua-Mobile'] = '?0';
            $headers['Sec-Ch-Ua-Platform'] = '"' . $browser['platform'] . '"';
        }

        // Sec-Fetch headers (all modern browsers send these)
        $headers['Sec-Fetch-Dest'] = 'document';
        $headers['Sec-Fetch-Mode'] = 'navigate';
        $headers['Sec-Fetch-Site'] = $referer ? 'cross-site' : 'none';
        $headers['Sec-Fetch-User'] = '?1';

        if (!$isFirefox && !$isSafari) {
            $headers['Priority'] = 'u=0, i';
        }

        if ($referer) {
            $headers['Referer'] = $referer;
        }

        return $headers;
    }

    /**
     * Generate headers for a same-origin navigation (e.g., clicking search from homepage)
     *
     * @param array $baseHeaders Headers from generate()
     * @return array Modified headers for same-origin navigation
     */
    public static function forSameOriginNavigation(array $baseHeaders): array
    {
        $headers = $baseHeaders;
        $headers['Sec-Fetch-Site'] = 'same-origin';
        $headers['Referer'] = 'https://masothue.com/';

        return $headers;
    }

    /**
     * Generate headers for an AJAX/XHR request (used for search)
     *
     * @param array $baseHeaders Headers from generate()
     * @return array Modified headers for XHR request
     */
    public static function forXhr(array $baseHeaders): array
    {
        $headers = $baseHeaders;
        $headers['X-Requested-With'] = 'XMLHttpRequest';
        $headers['Sec-Fetch-Dest'] = 'empty';
        $headers['Sec-Fetch-Mode'] = 'cors';
        $headers['Sec-Fetch-Site'] = 'same-origin';
        unset($headers['Sec-Fetch-User']);
        unset($headers['Upgrade-Insecure-Requests']);
        $headers['Accept'] = 'application/json, text/javascript, */*; q=0.01';

        return $headers;
    }

    /**
     * Get a description of the fingerprint for logging
     */
    public static function describe(array $headers): string
    {
        $ua = $headers['User-Agent'] ?? 'unknown';
        if (preg_match('/Edg\/([\d.]+)/', $ua, $m)) {
            return "Edge {$m[1]}";
        } elseif (preg_match('/Firefox\/([\d.]+)/', $ua, $m)) {
            return "Firefox {$m[1]}";
        } elseif (preg_match('/Version\/([\d.]+).*Safari/', $ua, $m)) {
            return "Safari {$m[1]}";
        } elseif (preg_match('/Chrome\/([\d.]+)/', $ua, $m)) {
            return "Chrome {$m[1]}";
        }
        return 'Unknown Browser';
    }
}
