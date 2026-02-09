<?php

require_once __DIR__ . '/vendor/autoload.php';

use HQV\Masothue\CompanyLookupService;
use HQV\Masothue\RateLimiter;

// Get tax code from GET parameter
$taxCode = $_GET['mst'] ?? '';

// Check for docs page
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (in_array($requestUri, ['/docs', '/docs/', '/api-docs', '/api-docs/'])) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/templates/api-docs.html');
    exit;
}

// If no tax code, serve the HTML interface (no rate limit for homepage)
if (empty($taxCode)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/templates/search.html');
    exit;
}

// API mode - apply rate limiting
header('Content-Type: application/json; charset=utf-8');

// Initialize rate limiter (5 requests per hour for non-whitelisted IPs)
$rateLimiter = new RateLimiter(5, 3600);

// Add whitelisted IPs/ranges (HQV IPs - unlimited)
$rateLimiter->addWhitelistedRange('14.224.174.0/24');    // 14.224.174.0 - 14.224.174.255
$rateLimiter->addWhitelistedRange('36.50.234.0/23');     // 36.50.234.0 - 36.50.235.255
$rateLimiter->addWhitelistedIP('118.69.168.15');
$rateLimiter->addWhitelistedIP('118.69.171.5');

// Check rate limit
$rateCheck = $rateLimiter->check();

// Add rate limit headers
if (!$rateCheck['whitelisted']) {
    header('X-RateLimit-Limit: 5');
    header('X-RateLimit-Remaining: ' . max(0, $rateCheck['remaining']));
    header('X-RateLimit-Reset: ' . $rateCheck['reset']);
}

// Block if rate limit exceeded
if (!$rateCheck['allowed']) {
    http_response_code(429);
    header('Retry-After: ' . $rateCheck['retry_after']);
    echo json_encode([
        'success' => false,
        'error' => 'Bạn đã vượt quá giới hạn 5 lần tra cứu/giờ. Vui lòng thử lại sau.',
        'retry_after' => $rateCheck['retry_after'],
        'reset' => date('Y-m-d H:i:s', $rateCheck['reset'])
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Trim and validate input (tax code or company name)
$taxCode = trim($taxCode);
$isTaxCodeInput = preg_match('/^\d[\d-]{8,13}$/', $taxCode);

if ($isTaxCodeInput) {
    // Tax code: validate 10-14 digits/dashes
    if (!preg_match('/^[\d-]{10,14}$/', $taxCode)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Mã số thuế không hợp lệ. Yêu cầu 10-14 chữ số.',
            'input' => $taxCode
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
} else {
    // Company name: at least 2 characters, max 200
    if (mb_strlen($taxCode) < 2 || mb_strlen($taxCode) > 200) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Từ khóa tìm kiếm phải từ 2 đến 200 ký tự.',
            'input' => $taxCode
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    // Create service and load proxies
    $service = new CompanyLookupService();
    $service->loadProxiesFromFile(__DIR__ . '/proxies.txt');
    $service->setTimeout(120);

    // Lookup company
    $result = $service->lookupByTaxCode($taxCode);

    // Build response
    if ($result->error) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => $result->error,
            'taxCode' => $taxCode
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => true,
            'data' => [
                'taxCode' => $result->taxCode,
                'name' => $result->name,
                'nameInternational' => $result->nameInternational,
                'nameShort' => $result->nameShort,
                'address' => $result->address,
                'addressLine1' => $result->addressLine1,
                'city' => $result->city,
                'stateProvince' => $result->stateProvince,
                'country' => $result->country,
                'representative' => $result->representative,
                'establishedDate' => $result->establishedDate,
                'status' => $result->status,
                'businessType' => $result->businessType,
                'businessSector' => $result->businessSector,
                'managedBy' => $result->managedBy,
                'phone' => $result->phone,
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
