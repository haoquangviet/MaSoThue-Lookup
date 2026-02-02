<?php

// Get tax code from GET parameter
$taxCode = $_GET['mst'] ?? '';

// Check for docs page
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (in_array($requestUri, ['/docs', '/docs/', '/api-docs', '/api-docs/'])) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/templates/api-docs.html');
    exit;
}

// If no tax code, serve the HTML interface
if (empty($taxCode)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/templates/search.html');
    exit;
}

// API mode - return JSON
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/vendor/autoload.php';

use HQV\Masothue\CompanyLookupService;

// Trim and validate tax code format
$taxCode = trim($taxCode);
if (!preg_match('/^[\d-]{10,14}$/', $taxCode)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid tax code format. Expected 10-14 digits.',
        'input' => $taxCode
    ], JSON_UNESCAPED_UNICODE);
    exit;
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
