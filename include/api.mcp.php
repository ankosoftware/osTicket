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

class McpApiController extends ApiController {

    private $staff;

    /**
     * Authenticate using HTTP Basic Auth with staff credentials
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

        if (!$auth || stripos($auth, 'Basic ') !== 0) {
            return false;
        }

        // Decode credentials
        $credentials = base64_decode(substr($auth, 6));
        if (!$credentials || strpos($credentials, ':') === false) {
            return false;
        }

        list($username, $password) = explode(':', $credentials, 2);

        if (!$username || !$password) {
            return false;
        }

        // Lookup staff by username
        $staff = StaffSession::lookup($username);
        if (!$staff) {
            return false;
        }

        // Verify password
        if (!$staff->check_passwd($password, false)) {
            return false;
        }

        // Check if staff is active
        if (!$staff->isActive()) {
            return false;
        }

        return $staff;
    }

    /**
     * Handle MCP JSON-RPC 2.0 request
     */
    public function handle() {
        // Authenticate
        $this->staff = $this->authenticate();
        if (!$this->staff) {
            $this->sendJsonRpcError(null, -32001, 'Authentication required', 401);
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

    /**
     * Override parent's error handler
     */
    public function onError($code, $error, $title = null, $logOnly = false) {
        if (!$logOnly) {
            $this->sendJsonRpcError(null, -32000, $error, $code);
        }
    }
}
?>
