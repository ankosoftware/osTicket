<?php
/*********************************************************************
    http.php

    HTTP controller for the osTicket API

    Jared Hancock
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require 'api.inc.php';
# Include the main api urls
require_once INCLUDE_DIR."class.dispatcher.php";
$dispatcher = patterns('',
        url_post("^/tickets\.(?P<format>xml|json|email)$", array('api.tickets.php:TicketApiController','create')),
        url('^/tasks/', patterns('',
                url_post("^cron$", array('api.cron.php:CronApiController', 'execute'))
         )),
        url_post("^/mcp\.json$", array('api.mcp.php:McpApiController', 'handle')),
        // OAuth 2.1 endpoints for MCP authentication (RFC9728, RFC8414)
        url_get("^/\.well-known/oauth-protected-resource$", array('api.oauth.php:OAuthApiController', 'protectedResourceMetadata')),
        url_get("^/\.well-known/oauth-authorization-server$", array('api.oauth.php:OAuthApiController', 'authorizationServerMetadata')),
        url_post("^/oauth/token$", array('api.oauth.php:OAuthApiController', 'token'))
        );

// Send api signal so backend can register endpoints
Signal::send('api', $dispatcher);
# Call the respective function
print $dispatcher->resolve(Osticket::get_path_info());
?>
