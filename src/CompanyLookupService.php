<?php

namespace HQV\Masothue;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;

/**
 * Company information structure
 */
class CompanyInfo
{
    public string $taxCode = '';
    public ?string $name = null;
    public ?string $nameInternational = null;
    public ?string $nameShort = null;
    public ?string $address = null;
    public ?string $addressLine1 = null;
    public ?string $city = null;
    public ?string $stateProvince = null;
    public ?string $country = null;
    public ?string $taxAddress = null;
    public ?string $representative = null;
    public ?string $establishedDate = null;
    public ?string $status = null;
    public ?string $businessType = null;
    public ?string $businessSector = null;
    public ?string $managedBy = null;
    public ?string $phone = null;
    public ?string $error = null;
    public array $logs = [];
    public array $steps = [];
    public ?string $rawHtml = null;

    public function toArray(): array
    {
        return [
            'taxCode' => $this->taxCode,
            'name' => $this->name,
            'nameInternational' => $this->nameInternational,
            'nameShort' => $this->nameShort,
            'address' => $this->address,
            'addressLine1' => $this->addressLine1,
            'city' => $this->city,
            'stateProvince' => $this->stateProvince,
            'country' => $this->country,
            'taxAddress' => $this->taxAddress,
            'representative' => $this->representative,
            'establishedDate' => $this->establishedDate,
            'status' => $this->status,
            'businessType' => $this->businessType,
            'businessSector' => $this->businessSector,
            'managedBy' => $this->managedBy,
            'phone' => $this->phone,
            'error' => $this->error,
            'logs' => $this->logs,
            'steps' => $this->steps,
        ];
    }
}

/**
 * Company Lookup Service - Fetches company information from masothue.com
 */
class CompanyLookupService
{
    private array $proxies = [];
    private ?string $proxyFile = null;
    private int $timeout = 120;
    private int $maxRetries = 3;
    private array $usedProxyIndexes = [];

    /**
     * Load proxies from file (one proxy per line)
     * Format: http://username:password@host:port
     */
    public function loadProxiesFromFile(string $filePath): self
    {
        $this->proxyFile = $filePath;
        $this->proxies = [];
        $this->usedProxyIndexes = [];

        if (!file_exists($filePath)) {
            throw new \RuntimeException("Proxy file not found: {$filePath}");
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments and empty lines
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            $this->proxies[] = $line;
        }

        if (empty($this->proxies)) {
            throw new \RuntimeException("No proxies found in file: {$filePath}");
        }

        return $this;
    }

    /**
     * Add a single proxy
     * Format: http://username:password@host:port
     */
    public function addProxy(string $proxyUrl): self
    {
        $this->proxies[] = $proxyUrl;
        return $this;
    }

    /**
     * Set proxy URL (single proxy, legacy method)
     * Format: http://username:password@host:port
     */
    public function setProxy(string $proxyUrl): self
    {
        $this->proxies = [$proxyUrl];
        $this->usedProxyIndexes = [];
        return $this;
    }

    /**
     * Get a random proxy that hasn't been used in current retry cycle
     */
    private function getRandomProxy(): ?string
    {
        if (empty($this->proxies)) {
            return null;
        }

        // Get available indexes (not used yet in this retry cycle)
        $availableIndexes = array_diff(array_keys($this->proxies), $this->usedProxyIndexes);

        // If all proxies used, reset the cycle
        if (empty($availableIndexes)) {
            $this->usedProxyIndexes = [];
            $availableIndexes = array_keys($this->proxies);
        }

        // Pick random available proxy
        $randomKey = array_rand(array_flip($availableIndexes));
        $this->usedProxyIndexes[] = $randomKey;

        return $this->proxies[$randomKey];
    }

    /**
     * Reset used proxies tracker (for new lookup)
     */
    private function resetProxyTracker(): void
    {
        $this->usedProxyIndexes = [];
    }

    /**
     * Get total number of loaded proxies
     */
    public function getProxyCount(): int
    {
        return count($this->proxies);
    }

    /**
     * Set request timeout in seconds
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Set max retries
     */
    public function setMaxRetries(int $retries): self
    {
        $this->maxRetries = max(1, $retries);
        return $this;
    }

    /**
     * Parse address into structured components
     * Logic: Split by comma, parse from right to left
     */
    private function parseAddress(string $fullAddress): array
    {
        if (empty($fullAddress)) {
            return [];
        }

        $parts = array_filter(array_map('trim', explode(',', $fullAddress)));
        $parts = array_values($parts);

        $country = null;
        $stateProvince = null;
        $city = null;
        $addressLine1Parts = [];

        // Parse from right to left (reverse order)
        for ($i = count($parts) - 1; $i >= 0; $i--) {
            $part = $parts[$i];
            $partLower = mb_strtolower($part, 'UTF-8');

            // Check for country (Việt Nam)
            if ($country === null && strpos($partLower, 'việt nam') !== false) {
                $country = $part;
                continue;
            }

            // Check for state/province (Tỉnh/Thành phố/TP)
            if ($stateProvince === null && (
                strpos($partLower, 'tỉnh') !== false ||
                strpos($partLower, 'thành phố') !== false ||
                preg_match('/^tp[\s\.]/', $partLower)
            )) {
                $stateProvince = $part;
                continue;
            }

            // Check for city (Phường/Xã/Đặc khu/Quận)
            if ($city === null && (
                strpos($partLower, 'phường') !== false ||
                strpos($partLower, 'xã') !== false ||
                strpos($partLower, 'đặc khu') !== false ||
                strpos($partLower, 'quận') !== false
            )) {
                $city = $part;
                continue;
            }

            // Everything else is addressLine1 (street address)
            array_unshift($addressLine1Parts, $part);
        }

        return [
            'addressLine1' => !empty($addressLine1Parts) ? implode(', ', $addressLine1Parts) : null,
            'city' => $city,
            'stateProvince' => $stateProvince,
            'country' => $country ?? 'Việt Nam',
        ];
    }

    /**
     * Lookup company information by tax code with retry logic
     */
    public function lookupByTaxCode(string $taxCode): CompanyInfo
    {
        // Reset proxy tracker for new lookup
        $this->resetProxyTracker();

        $allLogs = [];
        $allErrors = [];
        $proxyCount = $this->getProxyCount();

        $allLogs[] = "Loaded {$proxyCount} proxy(ies) from " . ($this->proxyFile ?? 'manual config');

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            // Get a random proxy for this attempt
            $proxyUrl = $this->getRandomProxy();

            $allLogs[] = "\n=== Attempt {$attempt}/{$this->maxRetries} ===";

            try {
                $result = $this->lookupWithProxy($taxCode, $attempt, $proxyUrl);

                if ($result->error === null) {
                    $result->logs = array_merge($allLogs, $result->logs);
                    return $result;
                }

                $allErrors[] = "Attempt {$attempt}: {$result->error}";
                $allLogs = array_merge($allLogs, $result->logs);

                if ($attempt < $this->maxRetries) {
                    $allLogs[] = "⚠️ Attempt {$attempt} failed, retrying with different proxy + fingerprint...";
                    sleep(random_int(1, 3));
                }

            } catch (\Exception $e) {
                $allErrors[] = "Attempt {$attempt}: {$e->getMessage()}";
                $allLogs[] = "❌ Attempt {$attempt} exception: {$e->getMessage()}";

                if ($attempt < $this->maxRetries) {
                    $allLogs[] = "⚠️ Attempt {$attempt} failed, retrying with different proxy + fingerprint...";
                    sleep(random_int(1, 3));
                }
            }
        }

        $companyInfo = new CompanyInfo();
        $companyInfo->taxCode = $taxCode;
        $companyInfo->error = "Đã cố gắng {$this->maxRetries} lần nhưng không tìm thấy kết quả, vui lòng kiểm tra lại thông tin bạn nhập.";
        $companyInfo->logs = $allLogs;
        $companyInfo->steps = [[
            'step' => 'All Attempts Failed',
            'status' => 'error',
            'message' => "Tried {$this->maxRetries} attempts",
            'timestamp' => time() * 1000,
        ]];

        return $companyInfo;
    }

    /**
     * Extract CSRF token from masothue.com homepage HTML
     */
    private function extractCsrfToken(string $html): ?string
    {
        // Look for meta tag: <meta name="csrf-token" content="...">
        if (preg_match('/<meta\s+name=["\']csrf-token["\']\s+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            return $matches[1];
        }
        // masothue.com uses: <input type="hidden" name="token" value="..." class="token-search">
        if (preg_match('/<input[^>]+name=["\']token["\']\s+value=["\']([^"\']+)["\']/i', $html, $matches)) {
            return $matches[1];
        }
        if (preg_match('/<input[^>]+value=["\']([^"\']+)["\']\s+name=["\']token["\']/i', $html, $matches)) {
            return $matches[1];
        }
        // Fallback: <input type="hidden" name="_token" value="...">
        if (preg_match('/<input[^>]+name=["\']_token["\']\s+value=["\']([^"\']+)["\']/i', $html, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Internal method: Lookup company information using proxy
     */
    private function lookupWithProxy(string $taxCode, int $attempt, ?string $proxyUrl = null): CompanyInfo
    {
        $logs = [];
        $steps = [];
        $companyInfo = new CompanyInfo();
        $companyInfo->taxCode = trim($taxCode);

        $addLog = function (string $message) use (&$logs) {
            $timestamp = date('Y-m-d\TH:i:s');
            $logs[] = "[{$timestamp}] {$message}";
        };

        $addStep = function (string $step, string $status, ?string $message = null) use (&$steps, $addLog) {
            $steps[] = [
                'step' => $step,
                'status' => $status,
                'message' => $message,
                'timestamp' => time() * 1000,
            ];
            $addLog("{$step}: {$status}" . ($message ? " - {$message}" : ''));
        };

        try {
            // Generate random browser fingerprint for this attempt
            $fingerprint = BrowserFingerprint::generate();
            $browserDesc = BrowserFingerprint::describe($fingerprint);

            $proxyInfo = $proxyUrl ? parse_url($proxyUrl) : null;
            $proxyDisplay = $proxyInfo ? "{$proxyInfo['host']}:{$proxyInfo['port']}" : 'No proxy';
            $addStep('Initialize', 'success', "Attempt {$attempt}/{$this->maxRetries} - Proxy: {$proxyDisplay} - Browser: {$browserDesc}");

            // Create cookie jar to persist cookies across requests (like a real browser)
            $cookieJar = new CookieJar();

            // Base client options
            $clientOptions = [
                'timeout' => $this->timeout,
                'connect_timeout' => 30,
                'allow_redirects' => [
                    'max' => 10,
                    'track_redirects' => true,
                ],
                'cookies' => $cookieJar,
                'headers' => $fingerprint,
                'verify' => false,
            ];

            // Add proxy if configured
            if ($proxyUrl) {
                $clientOptions['proxy'] = $proxyUrl;
                $addLog("Using proxy URL: " . preg_replace('/:[^:@]+@/', ':****@', $proxyUrl));
            } else {
                $addLog('No proxy configured - using direct connection');
            }

            $addLog("Fingerprint: {$browserDesc}, Lang: " . ($fingerprint['Accept-Language'] ?? 'n/a'));

            $client = new Client($clientOptions);

            // Step 1: Fetch homepage to get cookies + CSRF token (like a real browser visit)
            $addStep('Fetch Homepage', 'pending', 'Loading masothue.com homepage for cookies/CSRF');

            $homepageStartTime = microtime(true);
            try {
                $homepageResponse = $client->get('https://masothue.com/');
                $homepageTime = round((microtime(true) - $homepageStartTime) * 1000);
                $homepageHtml = (string) $homepageResponse->getBody();

                $csrfToken = $this->extractCsrfToken($homepageHtml);
                $cookieCount = count($cookieJar->toArray());

                $addStep('Fetch Homepage', 'success', "Got homepage ({$homepageResponse->getStatusCode()}) in {$homepageTime}ms, cookies: {$cookieCount}, CSRF: " . ($csrfToken ? 'found' : 'none'));
            } catch (\Exception $e) {
                $homepageTime = round((microtime(true) - $homepageStartTime) * 1000);
                $csrfToken = null;
                $addStep('Fetch Homepage', 'warning', "Homepage failed in {$homepageTime}ms: {$e->getMessage()} - continuing without cookies");
            }

            // Small delay to mimic human browsing
            usleep(random_int(300000, 800000)); // 300-800ms

            // Step 2: Search with same-origin headers (as if user clicked search from homepage)
            $searchHeaders = BrowserFingerprint::forSameOriginNavigation($fingerprint);

            // Detect if input is tax code or company name
            $isTaxCodeInput = preg_match('/^\d[\d-]{8,13}$/', trim($taxCode));
            $searchType = $isTaxCodeInput ? 'enterpriseTax' : 'auto';
            $searchUrl = "https://masothue.com/Search/?q=" . urlencode(trim($taxCode)) . "&type={$searchType}";

            $addStep('Fetch Company Page', 'pending', "Searching (type={$searchType}): {$searchUrl}");

            $startTime = microtime(true);
            try {
                $response = $client->get($searchUrl, ['headers' => $searchHeaders]);
            } catch (ClientException $e) {
                $responseCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
                $responseTime = round((microtime(true) - $startTime) * 1000);

                if ($responseCode === 403) {
                    $addStep('Fetch Company Page', 'error', "Got 403 Forbidden in {$responseTime}ms - bot detected");
                    $companyInfo->error = "403 Forbidden - bot detected (attempt {$attempt})";
                    $companyInfo->logs = $logs;
                    $companyInfo->steps = $steps;
                    return $companyInfo;
                }

                throw $e;
            }
            $responseTime = round((microtime(true) - $startTime) * 1000);

            $finalUrl = $searchUrl;
            $redirectHistory = $response->getHeader('X-Guzzle-Redirect-History');
            if (!empty($redirectHistory)) {
                $finalUrl = end($redirectHistory);
            }

            $addStep('Fetch Company Page', 'success', "Got response ({$response->getStatusCode()}) in {$responseTime}ms. Final URL: {$finalUrl}");

            $html = (string) $response->getBody();
            $addLog("Page loaded, size: " . strlen($html) . " bytes");

            // Load HTML with DOMDocument
            $doc = new \DOMDocument();
            @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($doc);

            // Check if this is a search results page and follow the best result link
            $detailPath = null;

            if ($isTaxCodeInput) {
                // Tax code search: find links matching the exact tax code
                $trimmedTax = trim($taxCode);
                $searchLinks = $xpath->query("//a[starts-with(@href, '/" . $trimmedTax . "-')]");
                if ($searchLinks->length > 0) {
                    // Find the main company link (skip branches like /TAXCODE-001-...)
                    $branchPath = null;
                    for ($li = 0; $li < $searchLinks->length; $li++) {
                        $href = $searchLinks->item($li)->getAttribute('href');
                        $text = trim($searchLinks->item($li)->textContent);
                        if (empty($text) || empty($href)) continue;
                        // Branch pattern: /TAXCODE-NNN- where NNN is 3 digits
                        if (preg_match('/^\/' . preg_quote($trimmedTax, '/') . '-\d{3}-/', $href)) {
                            if ($branchPath === null) $branchPath = $href;
                        } else {
                            $detailPath = $href;
                            break; // Main company found
                        }
                    }
                    // Fallback to branch if no main company link found
                    if ($detailPath === null) $detailPath = $branchPath;
                    $addLog("Found " . $searchLinks->length . " tax code links, selected: {$detailPath}");
                }
            } else {
                // Name search: find the first company result link (pattern: /DIGITS-slug)
                $resultLinks = $xpath->query("//a[contains(@href, '-')]");
                for ($li = 0; $li < $resultLinks->length; $li++) {
                    $href = $resultLinks->item($li)->getAttribute('href');
                    $text = trim($resultLinks->item($li)->textContent);
                    if (empty($text) || empty($href)) continue;
                    // Company detail links match: /0123456789-company-name-slug
                    if (preg_match('/^\/(\d{10,14})-[a-z]/', $href)) {
                        $detailPath = $href;
                        $addLog("Found company result link: {$href} => {$text}");
                        break;
                    }
                }
            }

            // Follow the detail link if found
            if ($detailPath) {
                $detailUrl = "https://masothue.com{$detailPath}";
                $addStep('Fetch Detail Page', 'pending', "Fetching: {$detailUrl}");

                // Small delay to mimic human clicking
                usleep(random_int(200000, 500000)); // 200-500ms

                $detailHeaders = BrowserFingerprint::forSameOriginNavigation($fingerprint);
                $detailHeaders['Referer'] = $searchUrl;
                $detailResponse = $client->get($detailUrl, ['headers' => $detailHeaders]);
                $html = (string) $detailResponse->getBody();

                $doc = new \DOMDocument();
                @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
                $xpath = new \DOMXPath($doc);

                $addStep('Fetch Detail Page', 'success', "Got detail page ({$detailResponse->getStatusCode()})");
                $addLog("Detail page loaded, size: " . strlen($html) . " bytes");
            }

            // Strip heavy elements for raw HTML
            $strippedHtml = preg_replace('/<head[^>]*>.*?<\/head>/is', '', $html);
            $strippedHtml = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $strippedHtml);
            $strippedHtml = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $strippedHtml);
            $addLog("Stripped HTML size: " . strlen($strippedHtml) . " bytes");

            // Check if company exists (error message)
            // Only check div/p elements for errors - tr.alert-danger is used for inactive company status rows
            $errorElements = $xpath->query("//div[contains(@class, 'alert-danger')] | //p[contains(@class, 'alert-danger')] | //*[contains(@class, 'error-message')]");
            if ($errorElements->length > 0) {
                $errorText = trim($errorElements->item(0)->textContent);
                $addStep('Validate Page', 'error', "Error message found: {$errorText}");
                $companyInfo->error = 'Company not found';
                $companyInfo->logs = $logs;
                $companyInfo->steps = $steps;
                return $companyInfo;
            }

            $addStep('Validate Page', 'success', 'Page loaded successfully');

            // Extract data
            $addStep('Extract Data', 'pending', 'Parsing company information table');
            $extractedData = [];
            $rowCount = 0;

            // Get first table
            $tables = $xpath->query("//table");
            if ($tables->length > 0) {
                $table = $tables->item(0);

                // Company name from itemprop="name" in th element
                $nameNodes = $xpath->query(".//th[@itemprop='name'] | .//th//*[@itemprop='name']", $table);
                if ($nameNodes->length > 0) {
                    $nameValue = trim($nameNodes->item(0)->textContent);
                    if ($nameValue) {
                        $extractedData['tên công ty'] = $nameValue;
                        $addLog("  ✓ Company name (itemprop): {$nameValue}");
                        $rowCount++;
                    }
                }

                // Tax code from itemprop="taxID"
                $taxIdNodes = $xpath->query(".//*[@itemprop='taxID']", $table);
                if ($taxIdNodes->length > 0) {
                    $taxIdValue = trim($taxIdNodes->item(0)->textContent);
                    if ($taxIdValue) {
                        $extractedData['mã số thuế'] = $taxIdValue;
                        $addLog("  ✓ Tax code (itemprop): {$taxIdValue}");
                        $rowCount++;
                    }
                }

                // Address from itemprop="address"
                $addressNodes = $xpath->query(".//*[@itemprop='address']", $table);
                if ($addressNodes->length > 0) {
                    $addressValue = trim($addressNodes->item(0)->textContent);
                    if ($addressValue) {
                        $extractedData['địa chỉ'] = $addressValue;
                        $shortAddr = mb_strlen($addressValue) > 60 ? mb_substr($addressValue, 0, 60) . '...' : $addressValue;
                        $addLog("  ✓ Address (itemprop): {$shortAddr}");
                        $rowCount++;
                    }
                }

                // Representative from alumni/Person itemprop
                $repRows = $xpath->query(".//*[@itemprop='alumni']", $table);
                if ($repRows->length > 0) {
                    $repNameNodes = $xpath->query(".//*[@itemprop='name']", $repRows->item(0));
                    if ($repNameNodes->length > 0) {
                        $repName = trim($repNameNodes->item(0)->textContent);
                        if ($repName) {
                            $extractedData['người đại diện'] = $repName;
                            $addLog("  ✓ Representative (itemprop): {$repName}");
                            $rowCount++;
                        }
                    }
                }

                // Phone from itemprop="telephone" - get first span.copy element
                $phoneNodes = $xpath->query(".//*[@itemprop='telephone']", $table);
                if ($phoneNodes->length > 0) {
                    $phoneSpans = $xpath->query(".//span[contains(@class, 'copy')]", $phoneNodes->item(0));
                    if ($phoneSpans->length > 0) {
                        $phoneValue = trim($phoneSpans->item(0)->textContent);
                        if ($phoneValue) {
                            $extractedData['điện thoại'] = $phoneValue;
                            $addLog("  ✓ Phone (itemprop): {$phoneValue}");
                            $rowCount++;
                        }
                    }
                }

                // Parse table rows for additional data
                $rows = $xpath->query(".//tr", $table);
                foreach ($rows as $row) {
                    $cells = $xpath->query(".//td | .//th", $row);

                    if ($cells->length >= 2) {
                        $rawLabel = trim($cells->item(0)->textContent);
                        $value = trim($cells->item(1)->textContent);

                        if ($rawLabel && $value) {
                            $cleanLabel = mb_strtolower(preg_replace('/\s+/', ' ', $rawLabel), 'UTF-8');
                            $cleanLabel = preg_replace('/^[\s\p{P}]+/u', '', $cleanLabel);
                            $cleanLabel = trim($cleanLabel);

                            // Only add if not already extracted
                            if (!isset($extractedData[$cleanLabel]) || strpos($cleanLabel, 'địa chỉ') !== false) {
                                $extractedData[$cleanLabel] = $value;
                                $shortValue = mb_strlen($value) > 60 ? mb_substr($value, 0, 60) . '...' : $value;
                                $addLog("  - Found \"{$cleanLabel}\": {$shortValue}");
                                $rowCount++;
                            }
                        }
                    }
                }
            }

            $addLog("Extracted {$rowCount} rows from table");

            // Validate tax code
            if (isset($extractedData['mã số thuế'])) {
                $extractedTaxCode = trim($extractedData['mã số thuế']);

                if ($isTaxCodeInput && $extractedTaxCode !== trim($taxCode)) {
                    $addStep('Validate Tax Code', 'error', "Tax code mismatch: searched \"{$taxCode}\" but got \"{$extractedTaxCode}\"");
                    $companyInfo->error = "Không tìm thấy công ty với mã số thuế \"{$taxCode}\"";
                    $companyInfo->logs = $logs;
                    $companyInfo->steps = $steps;
                    return $companyInfo;
                }

                if ($extractedTaxCode && $extractedTaxCode !== $taxCode) {
                    $addLog("  ✓ Tax code from search result: \"{$extractedTaxCode}\"");
                    $companyInfo->taxCode = $extractedTaxCode;
                } else {
                    $addLog("  ✓ Tax code verified: {$extractedTaxCode}");
                }
            }

            // Map extracted data to CompanyInfo fields
            $companyInfo->name = $extractedData['tên công ty'] ?? $extractedData['company name'] ?? null;
            $companyInfo->nameInternational = $extractedData['tên quốc tế'] ?? null;
            $companyInfo->nameShort = $extractedData['tên viết tắt'] ?? null;

            // Tax address
            if (isset($extractedData['địa chỉ thuế'])) {
                $companyInfo->taxAddress = $extractedData['địa chỉ thuế'];
            }

            // Main address: priority = "Địa chỉ thuế" > "Địa chỉ"
            $mainAddress = $extractedData['địa chỉ thuế'] ?? $extractedData['địa chỉ'] ?? null;
            if ($mainAddress) {
                $companyInfo->address = $mainAddress;
                $parsedAddress = $this->parseAddress($mainAddress);
                $companyInfo->addressLine1 = $parsedAddress['addressLine1'] ?? null;
                $companyInfo->city = $parsedAddress['city'] ?? null;
                $companyInfo->stateProvince = $parsedAddress['stateProvince'] ?? null;
                $companyInfo->country = $parsedAddress['country'] ?? null;
                $addLog("  ✓ Parsed address: line1=\"{$companyInfo->addressLine1}\", city=\"{$companyInfo->city}\", state=\"{$companyInfo->stateProvince}\", country=\"{$companyInfo->country}\"");
            }

            // Representative
            $companyInfo->representative = $extractedData['người đại diện pháp luật']
                ?? $extractedData['người đại diện']
                ?? $extractedData['giám đốc']
                ?? null;

            // Established date
            $companyInfo->establishedDate = $extractedData['ngày thành lập']
                ?? $extractedData['ngày hoạt động']
                ?? $extractedData['ngày cấp']
                ?? null;

            // Status
            $companyInfo->status = $extractedData['tình trạng'] ?? $extractedData['trạng thái'] ?? null;

            // Business type
            $companyInfo->businessType = $extractedData['loại hình doanh nghiệp']
                ?? $extractedData['loại hình dn']
                ?? $extractedData['loại hình']
                ?? null;

            // Business sector
            $companyInfo->businessSector = $extractedData['ngành nghề chính'] ?? $extractedData['ngành nghề'] ?? null;

            // Managed by
            $companyInfo->managedBy = $extractedData['quản lý bởi'] ?? null;

            // Phone - extract digits only
            $phoneRaw = $extractedData['điện thoại']
                ?? $extractedData['số điện thoại']
                ?? $extractedData['phone']
                ?? $extractedData['tel']
                ?? null;
            if ($phoneRaw) {
                $companyInfo->phone = preg_replace('/\D/', '', $phoneRaw);
                $addLog("  ✓ Phone extracted: {$phoneRaw} -> {$companyInfo->phone}");
            }

            if (!$companyInfo->name) {
                $addStep('Extract Data', 'error', 'Could not find company name in table');
                $companyInfo->error = 'Company information not found';
                $companyInfo->logs = $logs;
                $companyInfo->steps = $steps;
                return $companyInfo;
            }

            $addStep('Extract Data', 'success', "Extracted data from {$rowCount} rows");
            $addStep('Complete', 'success', 'Successfully fetched company information');

            $companyInfo->logs = $logs;
            $companyInfo->steps = $steps;
            $companyInfo->rawHtml = $strippedHtml;

            return $companyInfo;

        } catch (GuzzleException $e) {
            $errorMessage = $e->getMessage();

            if ($e->getCode()) {
                $errorMessage = "[{$e->getCode()}] {$errorMessage}";
            }

            $addStep('Error', 'error', $errorMessage);
            $addLog("Error occurred: {$errorMessage}");

            $companyInfo->error = $errorMessage;
            $companyInfo->logs = $logs;
            $companyInfo->steps = $steps;

            return $companyInfo;

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $addStep('Error', 'error', $errorMessage);
            $addLog("Error occurred: {$errorMessage}");

            $companyInfo->error = $errorMessage;
            $companyInfo->logs = $logs;
            $companyInfo->steps = $steps;

            return $companyInfo;
        }
    }
}
