<?php
/*********************************************************************
    mcp.php

    HTTP controller for the osTicket MCP (Model Context Protocol) API

    Enables AI agents to interact with osTicket via JSON-RPC 2.0.
    Authentication uses HTTP Basic Auth with staff credentials.

    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require 'api.inc.php';
require_once INCLUDE_DIR."class.dispatcher.php";

// When accessed directly as /api/mcp.php, the path info may be empty or just /
// Also handle /mcp.json for when routing through http.php
$dispatcher = patterns('',
    url_post("^/?$", array('api.mcp.php:McpApiController', 'handle')),
    url_post("^/mcp\.json$", array('api.mcp.php:McpApiController', 'handle'))
);

// Send api signal so backend can register endpoints
Signal::send('api', $dispatcher);
// Call the respective function
print $dispatcher->resolve(Osticket::get_path_info());
?>
