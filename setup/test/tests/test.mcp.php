<?php
/*********************************************************************
    test.mcp.php

    Unit tests for MCP (Model Context Protocol) implementation

    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

require_once "class.test.php";

class TestMcp extends Test {
    var $name = "MCP Protocol Tests";

    /**
     * Test McpException class
     */
    function testMcpException() {
        require_once INCLUDE_DIR . "class.mcp.php";

        $ex = new McpException(-32600, 'Invalid Request');

        $this->assertEqual($ex->getMessage(), 'Invalid Request',
            'McpException message should match');
        $this->assertEqual($ex->getJsonRpcCode(), -32600,
            'McpException code should match');
    }

    /**
     * Test that McpProtocolHandler can be instantiated
     */
    function testMcpProtocolHandlerInstantiation() {
        require_once INCLUDE_DIR . "class.mcp.php";

        // Create a mock staff object for testing
        $mockStaff = new MockStaff();

        $handler = new McpProtocolHandler($mockStaff);
        $this->assert($handler instanceof McpProtocolHandler,
            'McpProtocolHandler should be instantiable');
    }

    /**
     * Test initialize method response structure
     */
    function testInitializeResponse() {
        require_once INCLUDE_DIR . "class.mcp.php";

        $mockStaff = new MockStaff();
        $handler = new McpProtocolHandler($mockStaff);

        $result = $handler->handleRequest('initialize', array(), 1);

        $this->assert(isset($result['protocolVersion']),
            'Initialize should return protocolVersion');
        $this->assert(isset($result['capabilities']),
            'Initialize should return capabilities');
        $this->assert(isset($result['capabilities']['tools']),
            'Capabilities should include tools');
        $this->assert(isset($result['capabilities']['resources']),
            'Capabilities should include resources');
        $this->assert(isset($result['serverInfo']),
            'Initialize should return serverInfo');
        $this->assert(isset($result['serverInfo']['name']),
            'ServerInfo should include name');
        $this->assert(isset($result['serverInfo']['version']),
            'ServerInfo should include version');
    }

    /**
     * Test tools/list method response structure
     */
    function testToolsListResponse() {
        require_once INCLUDE_DIR . "class.mcp.php";

        $mockStaff = new MockStaff();
        $handler = new McpProtocolHandler($mockStaff);

        $result = $handler->handleRequest('tools/list', array(), 1);

        $this->assert(isset($result['tools']),
            'tools/list should return tools array');
        $this->assert(is_array($result['tools']),
            'tools should be an array');

        // Check that expected tools are present
        $toolNames = array_map(function($t) { return $t['name']; }, $result['tools']);

        $expectedTools = array(
            'search_tickets',
            'get_ticket',
            'create_ticket',
            'reply_to_ticket',
            'add_note_to_ticket',
            'update_ticket_status',
            'assign_ticket',
            'search_tasks',
            'create_task',
            'update_task'
        );

        foreach ($expectedTools as $tool) {
            $this->assert(in_array($tool, $toolNames),
                "Tool '{$tool}' should be in tools list");
        }

        // Check tool structure
        if (!empty($result['tools'])) {
            $tool = $result['tools'][0];
            $this->assert(isset($tool['name']),
                'Tool should have name');
            $this->assert(isset($tool['description']),
                'Tool should have description');
            $this->assert(isset($tool['inputSchema']),
                'Tool should have inputSchema');
        }
    }

    /**
     * Test resources/list method response structure
     */
    function testResourcesListResponse() {
        require_once INCLUDE_DIR . "class.mcp.php";

        $mockStaff = new MockStaff();
        $handler = new McpProtocolHandler($mockStaff);

        $result = $handler->handleRequest('resources/list', array(), 1);

        $this->assert(isset($result['resources']),
            'resources/list should return resources array');
        $this->assert(is_array($result['resources']),
            'resources should be an array');

        // Check that expected resources are present
        $resourceUris = array_map(function($r) { return $r['uri']; }, $result['resources']);

        $expectedResources = array(
            'osticket://departments',
            'osticket://help-topics',
            'osticket://staff',
            'osticket://teams',
            'osticket://statuses',
            'osticket://priorities'
        );

        foreach ($expectedResources as $uri) {
            $this->assert(in_array($uri, $resourceUris),
                "Resource '{$uri}' should be in resources list");
        }

        // Check resource structure
        if (!empty($result['resources'])) {
            $resource = $result['resources'][0];
            $this->assert(isset($resource['uri']),
                'Resource should have uri');
            $this->assert(isset($resource['name']),
                'Resource should have name');
            $this->assert(isset($resource['description']),
                'Resource should have description');
            $this->assert(isset($resource['mimeType']),
                'Resource should have mimeType');
        }
    }

    /**
     * Test unknown method handling
     */
    function testUnknownMethod() {
        require_once INCLUDE_DIR . "class.mcp.php";

        $mockStaff = new MockStaff();
        $handler = new McpProtocolHandler($mockStaff);

        $exceptionThrown = false;
        $errorCode = null;

        try {
            $handler->handleRequest('unknown/method', array(), 1);
        } catch (McpException $e) {
            $exceptionThrown = true;
            $errorCode = $e->getJsonRpcCode();
        }

        $this->assert($exceptionThrown,
            'Unknown method should throw McpException');
        $this->assertEqual($errorCode, -32601,
            'Unknown method should return -32601 error code');
    }

    /**
     * Test tools/call with missing name parameter
     */
    function testToolsCallMissingName() {
        require_once INCLUDE_DIR . "class.mcp.php";

        $mockStaff = new MockStaff();
        $handler = new McpProtocolHandler($mockStaff);

        $exceptionThrown = false;
        $errorCode = null;

        try {
            $handler->handleRequest('tools/call', array(), 1);
        } catch (McpException $e) {
            $exceptionThrown = true;
            $errorCode = $e->getJsonRpcCode();
        }

        $this->assert($exceptionThrown,
            'tools/call without name should throw McpException');
        $this->assertEqual($errorCode, -32602,
            'Missing parameter should return -32602 error code');
    }

    /**
     * Test tools/call with unknown tool
     */
    function testToolsCallUnknownTool() {
        require_once INCLUDE_DIR . "class.mcp.php";

        $mockStaff = new MockStaff();
        $handler = new McpProtocolHandler($mockStaff);

        $exceptionThrown = false;
        $errorCode = null;

        try {
            $handler->handleRequest('tools/call', array('name' => 'nonexistent_tool'), 1);
        } catch (McpException $e) {
            $exceptionThrown = true;
            $errorCode = $e->getJsonRpcCode();
        }

        $this->assert($exceptionThrown,
            'tools/call with unknown tool should throw McpException');
        $this->assertEqual($errorCode, -32602,
            'Unknown tool should return -32602 error code');
    }

    /**
     * Test resources/read with missing uri parameter
     */
    function testResourcesReadMissingUri() {
        require_once INCLUDE_DIR . "class.mcp.php";

        $mockStaff = new MockStaff();
        $handler = new McpProtocolHandler($mockStaff);

        $exceptionThrown = false;
        $errorCode = null;

        try {
            $handler->handleRequest('resources/read', array(), 1);
        } catch (McpException $e) {
            $exceptionThrown = true;
            $errorCode = $e->getJsonRpcCode();
        }

        $this->assert($exceptionThrown,
            'resources/read without uri should throw McpException');
        $this->assertEqual($errorCode, -32602,
            'Missing parameter should return -32602 error code');
    }

    /**
     * Test resources/read with unknown resource
     */
    function testResourcesReadUnknownResource() {
        require_once INCLUDE_DIR . "class.mcp.php";

        $mockStaff = new MockStaff();
        $handler = new McpProtocolHandler($mockStaff);

        $exceptionThrown = false;
        $errorCode = null;

        try {
            $handler->handleRequest('resources/read', array('uri' => 'osticket://nonexistent'), 1);
        } catch (McpException $e) {
            $exceptionThrown = true;
            $errorCode = $e->getJsonRpcCode();
        }

        $this->assert($exceptionThrown,
            'resources/read with unknown resource should throw McpException');
        $this->assertEqual($errorCode, -32602,
            'Unknown resource should return -32602 error code');
    }

    /**
     * Test JSON-RPC error codes are standard compliant
     */
    function testJsonRpcErrorCodes() {
        // Standard JSON-RPC 2.0 error codes
        $standardCodes = array(
            -32700, // Parse error
            -32600, // Invalid Request
            -32601, // Method not found
            -32602, // Invalid params
            -32603, // Internal error
        );

        // Custom server error codes should be in range -32000 to -32099
        $customCodes = array(
            -32001, // Authentication required (used in api.mcp.php)
            -32000, // Generic server error
        );

        // Just verify the ranges are correct
        foreach ($standardCodes as $code) {
            $this->assert($code >= -32700 && $code <= -32600,
                "Standard error code {$code} should be in range -32700 to -32600");
        }

        foreach ($customCodes as $code) {
            $this->assert($code >= -32099 && $code <= -32000,
                "Custom error code {$code} should be in range -32099 to -32000");
        }
    }

    /**
     * Test tool input schema structure
     */
    function testToolInputSchemaStructure() {
        require_once INCLUDE_DIR . "class.mcp.php";

        $mockStaff = new MockStaff();
        $handler = new McpProtocolHandler($mockStaff);

        $result = $handler->handleRequest('tools/list', array(), 1);

        foreach ($result['tools'] as $tool) {
            $schema = $tool['inputSchema'];

            $this->assertEqual($schema['type'], 'object',
                "Tool {$tool['name']} schema type should be 'object'");

            $this->assert(isset($schema['properties']),
                "Tool {$tool['name']} schema should have properties");

            // Verify property structure
            foreach ($schema['properties'] as $propName => $propDef) {
                $this->assert(isset($propDef['type']),
                    "Property {$propName} in {$tool['name']} should have type");
                $this->assert(isset($propDef['description']),
                    "Property {$propName} in {$tool['name']} should have description");
            }
        }
    }
}

/**
 * Mock Staff class for testing
 */
class MockStaff {
    private $id = 1;
    private $perms = array();

    function getId() {
        return $this->id;
    }

    function getName() {
        return 'Test Staff';
    }

    function getEmail() {
        return 'test@example.com';
    }

    function getUserName() {
        return 'teststaff';
    }

    function getDeptId() {
        return 1;
    }

    function getDepts() {
        return array(1, 2, 3);
    }

    function isAdmin() {
        return false;
    }

    function isActive() {
        return true;
    }

    function hasPerm($perm) {
        return in_array($perm, $this->perms);
    }

    function setPerms($perms) {
        $this->perms = $perms;
    }
}

return 'TestMcp';
?>
