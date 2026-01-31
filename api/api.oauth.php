<?php
/*********************************************************************
    api.oauth.php

    Minimal OAuth 2.1 implementation for MCP authentication.
    Implements RFC9728 (Protected Resource Metadata) and basic token endpoint.

    Token Format: JWT-like structure with HMAC-SHA256 signature
    - Header: {"alg":"HS256","typ":"JWT"}
    - Payload: {"sub","aud","iat","exp","scope"}
    - Signature: HMAC-SHA256(header.payload, SECRET_SALT)

    Copyright (c) 2025 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

require_once INCLUDE_DIR . 'class.api.php';
require_once INCLUDE_DIR . 'class.staff.php';

class OAuthApiController extends ApiController {

    // Token expiration time in seconds (1 hour)
    const TOKEN_EXPIRATION = 3600;

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
     * Get the MCP server URL
     */
    private function getMcpServerUrl() {
        return $this->getBaseUrl() . '/api/mcp.json';
    }

    /**
     * Protected Resource Metadata (RFC9728)
     * GET /api/.well-known/oauth-protected-resource
     */
    function protectedResourceMetadata() {
        $baseUrl = $this->getBaseUrl();

        $metadata = array(
            'resource' => $this->getMcpServerUrl(),
            'authorization_servers' => array($baseUrl),
            'scopes_supported' => array('mcp:full'),
            'bearer_methods_supported' => array('header'),
            'resource_documentation' => $baseUrl . '/api/',
        );

        header('Content-Type: application/json');
        header('Cache-Control: public, max-age=3600');
        header('Access-Control-Allow-Origin: *');
        echo json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Authorization Server Metadata (RFC8414)
     * GET /api/.well-known/oauth-authorization-server
     */
    function authorizationServerMetadata() {
        $baseUrl = $this->getBaseUrl();

        $metadata = array(
            'issuer' => $baseUrl,
            'token_endpoint' => $baseUrl . '/api/oauth/token',
            'token_endpoint_auth_methods_supported' => array('client_secret_basic', 'client_secret_post'),
            'grant_types_supported' => array('client_credentials'),
            'scopes_supported' => array('mcp:full'),
            'response_types_supported' => array('token'),
            'service_documentation' => $baseUrl . '/api/',
            'code_challenge_methods_supported' => array('S256'),
        );

        header('Content-Type: application/json');
        header('Cache-Control: public, max-age=3600');
        header('Access-Control-Allow-Origin: *');
        echo json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Token Endpoint
     * POST /api/oauth/token
     *
     * Supports client_credentials grant with Basic auth (staff credentials)
     * Returns a Bearer token (base64 encoded credentials for simplicity)
     */
    function token() {
        // Only POST allowed
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->errorResponse(405, 'Method Not Allowed');
            return;
        }

        // Get grant_type
        $grantType = $_POST['grant_type'] ?? '';
        if ($grantType !== 'client_credentials') {
            $this->oauthError('unsupported_grant_type', 'Only client_credentials grant is supported');
            return;
        }

        // Authenticate via Basic auth or POST body
        $username = null;
        $password = null;

        // Try Basic auth first
        $auth = $this->getAuthorizationHeader();
        if ($auth && stripos($auth, 'Basic ') === 0) {
            $credentials = base64_decode(substr($auth, 6));
            if ($credentials && strpos($credentials, ':') !== false) {
                list($username, $password) = explode(':', $credentials, 2);
            }
        }

        // Fall back to POST body (client_id/client_secret)
        if (!$username) {
            $username = $_POST['client_id'] ?? '';
            $password = $_POST['client_secret'] ?? '';
        }

        if (!$username || !$password) {
            $this->oauthError('invalid_client', 'Client credentials required');
            return;
        }

        // Authenticate staff member
        $staff = $this->authenticateStaff($username, $password);
        if (!$staff) {
            $this->oauthError('invalid_client', 'Invalid credentials');
            return;
        }

        // Get resource parameter (RFC 8707)
        $resource = $_POST['resource'] ?? $this->getMcpServerUrl();

        // Generate JWT token
        $token = $this->generateToken($staff, $resource);
        $expiresIn = self::TOKEN_EXPIRATION;

        $response = array(
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'scope' => 'mcp:full',
        );

        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        header('Pragma: no-cache');
        echo json_encode($response, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate a JWT-like token with HMAC signature
     */
    private function generateToken($staff, $audience) {
        $header = array(
            'alg' => 'HS256',
            'typ' => 'JWT'
        );

        $now = time();
        $payload = array(
            'sub' => $staff->getUserName(),          // Subject (username)
            'staff_id' => $staff->getId(),           // Staff ID for lookup
            'aud' => $audience,                       // Audience (MCP server URL)
            'iat' => $now,                            // Issued at
            'exp' => $now + self::TOKEN_EXPIRATION,  // Expiration
            'scope' => 'mcp:full'                     // Granted scope
        );

        $headerB64 = $this->base64UrlEncode(json_encode($header));
        $payloadB64 = $this->base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', "{$headerB64}.{$payloadB64}", SECRET_SALT, true);
        $signatureB64 = $this->base64UrlEncode($signature);

        return "{$headerB64}.{$payloadB64}.{$signatureB64}";
    }

    /**
     * Validate a JWT token and return the payload
     */
    public static function validateToken($token, $expectedAudience = null) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        list($headerB64, $payloadB64, $signatureB64) = $parts;

        // Verify signature
        $expectedSignature = hash_hmac('sha256', "{$headerB64}.{$payloadB64}", SECRET_SALT, true);
        $expectedSignatureB64 = self::base64UrlEncodeStatic($expectedSignature);

        if (!hash_equals($expectedSignatureB64, $signatureB64)) {
            return false;
        }

        // Decode payload
        $payload = json_decode(self::base64UrlDecodeStatic($payloadB64), true);
        if (!$payload) {
            return false;
        }

        // Check expiration
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            return false;
        }

        // Check audience if specified
        if ($expectedAudience && isset($payload['aud']) && $payload['aud'] !== $expectedAudience) {
            return false;
        }

        return $payload;
    }

    /**
     * Base64 URL-safe encoding
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlEncodeStatic($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecodeStatic($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Get Authorization header
     */
    private function getAuthorizationHeader() {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                return $headers['Authorization'];
            }
        }
        return null;
    }

    /**
     * Authenticate staff member
     */
    private function authenticateStaff($username, $password) {
        // Try by username
        $staff = Staff::lookup($username);

        // If not found, try by email
        if (!$staff) {
            $staff = Staff::lookup(array('email' => $username));
        }

        if (!$staff || !$staff->isActive()) {
            return false;
        }

        // Check password
        if (!$staff->check_passwd($password)) {
            return false;
        }

        return $staff;
    }

    /**
     * Send OAuth error response
     */
    private function oauthError($error, $description, $statusCode = 400) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');

        echo json_encode(array(
            'error' => $error,
            'error_description' => $description,
        ), JSON_UNESCAPED_SLASHES);
    }

    /**
     * Send error response
     */
    private function errorResponse($statusCode, $message) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode(array('error' => $message));
    }
}
