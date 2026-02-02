<?php

namespace HQV\Masothue;

class RateLimiter
{
    private string $storageDir;
    private int $maxRequests;
    private int $windowSeconds;
    private array $whitelistedIPs = [];
    private array $whitelistedRanges = [];

    public function __construct(int $maxRequests = 5, int $windowSeconds = 3600)
    {
        $this->storageDir = __DIR__ . '/../data/ratelimit';
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;

        // Create storage directory if not exists
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * Add whitelisted IP (exact match)
     */
    public function addWhitelistedIP(string $ip): self
    {
        $this->whitelistedIPs[] = $ip;
        return $this;
    }

    /**
     * Add whitelisted IP range (CIDR notation)
     */
    public function addWhitelistedRange(string $cidr): self
    {
        $this->whitelistedRanges[] = $cidr;
        return $this;
    }

    /**
     * Check if IP is whitelisted
     */
    public function isWhitelisted(string $ip): bool
    {
        // Check exact match
        if (in_array($ip, $this->whitelistedIPs)) {
            return true;
        }

        // Check CIDR ranges
        foreach ($this->whitelistedRanges as $cidr) {
            if ($this->ipInRange($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP is in CIDR range
     */
    private function ipInRange(string $ip, string $cidr): bool
    {
        if (strpos($cidr, '/') === false) {
            // Treat as /32 for single IP or /24 for .0 ending
            if (substr($cidr, -2) === '.0') {
                $cidr .= '/24';
            } else {
                $cidr .= '/32';
            }
        }

        list($subnet, $bits) = explode('/', $cidr);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - (int)$bits);
        $subnet &= $mask;

        return ($ip & $mask) === $subnet;
    }

    /**
     * Get client IP address
     */
    public function getClientIP(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_X_FORWARDED_FOR',      // Standard proxy
            'REMOTE_ADDR'                // Direct connection
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // X-Forwarded-For can contain multiple IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Get storage file path for IP
     */
    private function getStorageFile(string $ip): string
    {
        $hash = md5($ip);
        return $this->storageDir . '/' . $hash . '.json';
    }

    /**
     * Check rate limit and return result
     */
    public function check(?string $ip = null): array
    {
        $ip = $ip ?? $this->getClientIP();

        // Whitelisted IPs bypass rate limit
        if ($this->isWhitelisted($ip)) {
            return [
                'allowed' => true,
                'remaining' => -1, // Unlimited
                'reset' => 0,
                'ip' => $ip,
                'whitelisted' => true
            ];
        }

        $file = $this->getStorageFile($ip);
        $now = time();
        $data = ['requests' => [], 'ip' => $ip];

        // Load existing data
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content) {
                $data = json_decode($content, true) ?? $data;
            }
        }

        // Filter requests within time window
        $windowStart = $now - $this->windowSeconds;
        $data['requests'] = array_filter($data['requests'], function ($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });

        $requestCount = count($data['requests']);
        $remaining = max(0, $this->maxRequests - $requestCount);
        $oldestRequest = !empty($data['requests']) ? min($data['requests']) : $now;
        $reset = $oldestRequest + $this->windowSeconds;

        // Check if limit exceeded
        if ($requestCount >= $this->maxRequests) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset' => $reset,
                'ip' => $ip,
                'whitelisted' => false,
                'retry_after' => $reset - $now
            ];
        }

        // Record this request
        $data['requests'][] = $now;
        $data['last_request'] = $now;

        // Save data
        @file_put_contents($file, json_encode($data), LOCK_EX);

        return [
            'allowed' => true,
            'remaining' => $remaining - 1,
            'reset' => $reset,
            'ip' => $ip,
            'whitelisted' => false
        ];
    }

    /**
     * Clean up old rate limit files
     */
    public function cleanup(): int
    {
        $count = 0;
        $files = glob($this->storageDir . '/*.json');
        $expiry = time() - $this->windowSeconds;

        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content) {
                $data = json_decode($content, true);
                if (isset($data['last_request']) && $data['last_request'] < $expiry) {
                    @unlink($file);
                    $count++;
                }
            }
        }

        return $count;
    }
}
