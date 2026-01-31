<?php
/**
 * API v2 Test Script
 *
 * Run this script from the command line to test the Ticket API:
 *   php test-api.php
 *
 * Or include it in a web request to osTicket for internal testing.
 */

// Configuration
$config = [
    'base_url' => getenv('API_BASE_URL') ?: 'http://localhost:8080',
    'api_key' => getenv('API_KEY') ?: '',
];

// If running from CLI within osTicket context
if (php_sapi_name() === 'cli' && file_exists(__DIR__ . '/main.inc.php')) {
    define('ROOT_DIR', __DIR__ . '/');
    define('INCLUDE_DIR', ROOT_DIR . 'include/');

    // Try to load a test API key from the database
    require_once 'bootstrap.php';
    require_once INCLUDE_DIR . 'class.osticket.php';

    // Initialize osTicket
    if (file_exists(INCLUDE_DIR . 'ost-config.php')) {
        require_once INCLUDE_DIR . 'ost-config.php';
        require_once INCLUDE_DIR . 'class.api.php';

        // Check if we need to add the API columns
        $result = db_query("SHOW COLUMNS FROM " . API_KEY_TABLE . " LIKE 'can_read_tickets'");
        if (!$result || db_num_rows($result) == 0) {
            echo "Adding v2 API columns to database...\n";
            db_query("ALTER TABLE " . API_KEY_TABLE . "
                ADD COLUMN `can_read_tickets` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `can_exec_cron`,
                ADD COLUMN `staff_id` int(11) unsigned DEFAULT NULL AFTER `can_read_tickets`");
            echo "Columns added successfully!\n\n";
        }

        // Get or create a test API key
        $result = db_query("SELECT apikey, ipaddr FROM " . API_KEY_TABLE . " WHERE can_read_tickets = 1 LIMIT 1");
        if ($result && db_num_rows($result) > 0) {
            $row = db_fetch_array($result);
            $config['api_key'] = $row['apikey'];
            echo "Using existing API key for testing\n";
            echo "Note: API key is bound to IP: {$row['ipaddr']}\n\n";
        }
    }
}

class ApiTester {
    private $baseUrl;
    private $apiKey;
    private $results = [];

    public function __construct($baseUrl, $apiKey) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
    }

    public function run() {
        echo "=== osTicket v2 API Test Suite ===\n";
        echo "Base URL: {$this->baseUrl}\n";
        echo "API Key: " . ($this->apiKey ? substr($this->apiKey, 0, 8) . '...' : 'NOT SET') . "\n\n";

        if (!$this->apiKey) {
            echo "ERROR: No API key configured!\n\n";
            echo "To test the API:\n";
            echo "1. Go to Admin Panel -> Manage -> API Keys\n";
            echo "2. Create or edit an API key\n";
            echo "3. Enable 'Can Search/Read Tickets' permission\n";
            echo "4. Select an Agent (determines ticket visibility)\n";
            echo "5. Set the API key:\n";
            echo "   - Windows: set API_KEY=your_key_here\n";
            echo "   - Linux: export API_KEY=your_key_here\n";
            echo "6. Run this script again\n\n";
            return;
        }

        $this->testSearchEndpoint();
        $this->testSearchWithFilters();
        $this->testGetByNumber();
        $this->testGetById();
        $this->testInvalidAuth();
        $this->testNotFound();

        $this->printSummary();
    }

    private function request($method, $endpoint, $params = []) {
        $url = $this->baseUrl . $endpoint;

        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $this->apiKey,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'code' => $httpCode,
            'body' => $response,
            'data' => json_decode($response, true),
            'error' => $error,
        ];
    }

    private function testSearchEndpoint() {
        echo "--- Test 1: Search Tickets ---\n";
        echo "GET /api/v2/tickets.json\n";

        $result = $this->request('GET', '/api/v2/tickets.json', ['limit' => 5]);

        echo "HTTP {$result['code']}\n";

        if ($result['code'] === 200) {
            $data = $result['data'];
            $count = count($data['tickets'] ?? []);
            $total = $data['pagination']['total'] ?? 0;
            echo "Found {$count} tickets (total: {$total})\n";
            echo "✓ PASSED\n\n";
            $this->results['search'] = true;

            // Store first ticket for later tests
            if (!empty($data['tickets'])) {
                $this->firstTicket = $data['tickets'][0];
            }
        } else {
            echo "Response: " . substr($result['body'], 0, 200) . "\n";
            echo "✗ FAILED\n\n";
            $this->results['search'] = false;
        }
    }

    private function testSearchWithFilters() {
        echo "--- Test 2: Search with Filters ---\n";
        echo "GET /api/v2/tickets.json?status=open&limit=3\n";

        $result = $this->request('GET', '/api/v2/tickets.json', [
            'status' => 'open',
            'limit' => 3,
            'sort' => 'created',
            'order' => 'desc'
        ]);

        echo "HTTP {$result['code']}\n";

        if ($result['code'] === 200) {
            $count = count($result['data']['tickets'] ?? []);
            echo "Found {$count} open tickets\n";
            echo "✓ PASSED\n\n";
            $this->results['search_filtered'] = true;
        } else {
            echo "Response: " . substr($result['body'], 0, 200) . "\n";
            echo "✗ FAILED\n\n";
            $this->results['search_filtered'] = false;
        }
    }

    private function testGetByNumber() {
        echo "--- Test 3: Get Ticket by Number ---\n";

        if (empty($this->firstTicket['number'])) {
            echo "SKIPPED (no tickets available)\n\n";
            $this->results['get_by_number'] = null;
            return;
        }

        $number = $this->firstTicket['number'];
        echo "GET /api/v2/tickets/number/{$number}.json\n";

        $result = $this->request('GET', "/api/v2/tickets/number/{$number}.json");

        echo "HTTP {$result['code']}\n";

        if ($result['code'] === 200 && !empty($result['data']['ticket'])) {
            $ticket = $result['data']['ticket'];
            echo "Retrieved: #{$ticket['number']} - {$ticket['subject']}\n";
            echo "Status: {$ticket['status']['name']}\n";
            echo "Thread entries: " . count($ticket['thread'] ?? []) . "\n";
            echo "Custom fields: " . count($ticket['custom_fields'] ?? []) . "\n";
            echo "✓ PASSED\n\n";
            $this->results['get_by_number'] = true;
        } else {
            echo "Response: " . substr($result['body'], 0, 200) . "\n";
            echo "✗ FAILED\n\n";
            $this->results['get_by_number'] = false;
        }
    }

    private function testGetById() {
        echo "--- Test 4: Get Ticket by ID ---\n";

        if (empty($this->firstTicket['id'])) {
            echo "SKIPPED (no tickets available)\n\n";
            $this->results['get_by_id'] = null;
            return;
        }

        $id = $this->firstTicket['id'];
        echo "GET /api/v2/tickets/{$id}.json\n";

        $result = $this->request('GET', "/api/v2/tickets/{$id}.json");

        echo "HTTP {$result['code']}\n";

        if ($result['code'] === 200 && !empty($result['data']['ticket'])) {
            echo "Retrieved ticket successfully\n";
            echo "✓ PASSED\n\n";
            $this->results['get_by_id'] = true;
        } else {
            echo "Response: " . substr($result['body'], 0, 200) . "\n";
            echo "✗ FAILED\n\n";
            $this->results['get_by_id'] = false;
        }
    }

    private function testInvalidAuth() {
        echo "--- Test 5: Invalid API Key ---\n";
        echo "GET /api/v2/tickets.json (with invalid key)\n";

        // Temporarily use invalid key
        $originalKey = $this->apiKey;
        $this->apiKey = 'INVALID_KEY_12345';

        $result = $this->request('GET', '/api/v2/tickets.json');

        $this->apiKey = $originalKey;

        echo "HTTP {$result['code']}\n";

        if ($result['code'] === 401) {
            echo "Correctly rejected invalid API key\n";
            echo "✓ PASSED\n\n";
            $this->results['invalid_auth'] = true;
        } else {
            echo "Expected 401, got {$result['code']}\n";
            echo "✗ FAILED\n\n";
            $this->results['invalid_auth'] = false;
        }
    }

    private function testNotFound() {
        echo "--- Test 6: Ticket Not Found ---\n";
        echo "GET /api/v2/tickets/999999999.json\n";

        $result = $this->request('GET', '/api/v2/tickets/999999999.json');

        echo "HTTP {$result['code']}\n";

        if ($result['code'] === 404) {
            echo "Correctly returned 404 for non-existent ticket\n";
            echo "✓ PASSED\n\n";
            $this->results['not_found'] = true;
        } else {
            echo "Expected 404, got {$result['code']}\n";
            echo "✗ FAILED\n\n";
            $this->results['not_found'] = false;
        }
    }

    private function printSummary() {
        echo "=== Test Summary ===\n";
        $passed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($this->results as $test => $result) {
            if ($result === true) {
                $passed++;
            } elseif ($result === false) {
                $failed++;
            } else {
                $skipped++;
            }
        }

        echo "Passed: {$passed}\n";
        echo "Failed: {$failed}\n";
        echo "Skipped: {$skipped}\n";

        if ($failed > 0) {
            echo "\n⚠ Some tests failed. Check the output above for details.\n";
        } else {
            echo "\n✓ All tests passed!\n";
        }
    }

    private $firstTicket = null;
}

// Run tests
$tester = new ApiTester($config['base_url'], $config['api_key']);
$tester->run();
