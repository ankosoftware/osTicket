<?php
/*********************************************************************
    mcp.php

    MCP (Model Context Protocol) Server Documentation.

    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require('admin.inc.php');

$nav->setTabActive('manage');
$ost->addExtraHeader('<meta name="tip-namespace" content="manage.mcp" />',
    "$('#content').data('tipNamespace', 'manage.mcp');");
require(STAFFINC_DIR.'header.inc.php');
require(STAFFINC_DIR.'mcp.inc.php');
include(STAFFINC_DIR.'footer.inc.php');
?>
