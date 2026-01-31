<?php
/*********************************************************************
    api.mcp.php

    MCP (Model Context Protocol) API Controller

    Handles JSON-RPC 2.0 requests for AI agent interactions with osTicket.
    Uses HTTP Basic Auth with staff credentials for authentication.

    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

include_once INCLUDE_DIR.'class.api.php';
include_once INCLUDE_DIR.'class.mcp.php';
include_once API_DIR.'api.oauth.php';

class McpApiController extends ApiController {

    private $staff;

    /**
     * Get the base URL for OAuth endpoints
     */
    private function getBaseUrl() {
        global $cfg;

        $baseUrl = $cfg ? $cfg->getBaseUrl() : '';
        if (!$baseUrl) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = "{$scheme}://{$host}";
        }

        return rtrim($baseUrl, '/');
    }

    /**
     * Get the resource metadata URL for WWW-Authenticate header
     * Points to root .well-known path per RFC9728
     */
    private function getResourceMetadataUrl() {
        return $this->getBaseUrl() . '/.well-known/oauth-protected-resource';
    }

    /**
     * Authenticate using HTTP Basic Auth or JWT Bearer token
     *
     * Supports:
     * - Basic auth: Authorization: Basic base64(username:password)
     * - Bearer token: Authorization: Bearer <JWT> (from /api/oauth/token)
     *
     * JWT tokens are validated for:
     * - Valid HMAC-SHA256 signature
     * - Expiration time
     * - Audience (must match this MCP server URL)
     *
     * @return Staff|false The authenticated staff member or false on failure
     */
    protected function authenticate() {
        // Check for Authorization header
        $auth = null;
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                $auth = $headers['Authorization'];
            }
        }

        if (!$auth) {
            return false;
        }

        // Support both Basic auth and JWT Bearer tokens
        if (stripos($auth, 'Basic ') === 0) {
            // Basic auth: decode credentials and verify password
            $credentials = base64_decode(substr($auth, 6));
            if (!$credentials || strpos($credentials, ':') === false) {
                return false;
            }

            list($username, $password) = explode(':', $credentials, 2);
            if (!$username || !$password) {
                return false;
            }

            $staff = StaffSession::lookup($username);
            if (!$staff || !$staff->check_passwd($password, false) || !$staff->isActive()) {
                return false;
            }

            return $staff;

        } elseif (stripos($auth, 'Bearer ') === 0) {
            // Bearer token: validate JWT and lookup staff
            $token = substr($auth, 7);

            // Validate JWT token with audience check
            $expectedAudience = $this->getBaseUrl() . '/api/mcp.json';
            $payload = OAuthApiController::validateToken($token, $expectedAudience);

            if (!$payload) {
                return false;
            }

            // Lookup staff by ID from token
            if (!isset($payload['staff_id'])) {
                return false;
            }

            $staff = Staff::lookup($payload['staff_id']);
            if (!$staff || !$staff->isActive()) {
                return false;
            }

            return $staff;
        }

        return false;
    }

    /**
     * Handle MCP JSON-RPC 2.0 request
     */
    public function handle() {
        // Authenticate
        $this->staff = $this->authenticate();
        if (!$this->staff) {
            $this->sendAuthenticationRequired();
            return;
        }

        // Read request body
        $input = file_get_contents('php://input');
        if (!$input) {
            $this->sendJsonRpcError(null, -32700, 'Parse error: Empty request body');
            return;
        }

        // Parse JSON
        $request = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendJsonRpcError(null, -32700, 'Parse error: Invalid JSON');
            return;
        }

        // Validate JSON-RPC request
        if (!isset($request['jsonrpc']) || $request['jsonrpc'] !== '2.0') {
            $this->sendJsonRpcError(
                $request['id'] ?? null,
                -32600,
                'Invalid Request: Missing or invalid jsonrpc version'
            );
            return;
        }

        if (!isset($request['method']) || !is_string($request['method'])) {
            $this->sendJsonRpcError(
                $request['id'] ?? null,
                -32600,
                'Invalid Request: Missing or invalid method'
            );
            return;
        }

        // Handle the request
        try {
            $handler = new McpProtocolHandler($this->staff);
            $result = $handler->handleRequest(
                $request['method'],
                $request['params'] ?? [],
                $request['id'] ?? null
            );

            // Send response
            $this->sendJsonRpcResponse($request['id'] ?? null, $result);
        } catch (McpException $e) {
            $this->sendJsonRpcError(
                $request['id'] ?? null,
                $e->getJsonRpcCode(),
                $e->getMessage()
            );
        } catch (Exception $e) {
            $this->sendJsonRpcError(
                $request['id'] ?? null,
                -32603,
                'Internal error: ' . $e->getMessage()
            );
        }
    }

    /**
     * Send 401 Unauthorized with WWW-Authenticate header per MCP/OAuth spec (RFC9728)
     */
    protected function sendAuthenticationRequired() {
        $resourceMetadata = $this->getResourceMetadataUrl();

        http_response_code(401);
        header('WWW-Authenticate: Bearer resource_metadata="' . $resourceMetadata . '", scope="mcp:full"');
        header('Content-Type: application/json; charset=UTF-8');

        $response = array(
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => array(
                'code' => -32001,
                'message' => 'Authentication required'
            )
        );

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Send a JSON-RPC success response
     */
    protected function sendJsonRpcResponse($id, $result) {
        $response = array(
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result
        );

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Send a JSON-RPC error response
     */
    protected function sendJsonRpcError($id, $code, $message, $httpCode = 200, $data = null) {
        if ($httpCode !== 200) {
            Http::response($httpCode, null, 'application/json', 'UTF-8');
        }

        $error = array(
            'code' => $code,
            'message' => $message
        );

        if ($data !== null) {
            $error['data'] = $data;
        }

        $response = array(
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => $error
        );

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
?>
