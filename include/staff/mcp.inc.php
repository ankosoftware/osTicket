<?php
if(!defined('OSTADMININC') || !$thisstaff->isAdmin()) die('Access Denied');

// Build MCP endpoint URL from current request
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$basePath = dirname($basePath); // Go up from /scp/
$mcpEndpoint = $protocol . '://' . $host . $basePath . '/api/mcp.json';
?>
<div class="sticky bar opaque">
    <div class="content">
        <div class="pull-left flush-left">
            <h2><?php echo __('MCP Server'); ?> <small>(Model Context Protocol)</small></h2>
        </div>
    </div>
</div>
<div class="clear"></div>

<div style="padding: 20px; max-width: 900px;">
    <div style="background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
        <h3 style="margin-top: 0;"><i class="icon-globe"></i> <?php echo __('MCP Endpoint'); ?></h3>
        <p><?php echo __('Use the following endpoint to connect AI agents to osTicket:'); ?></p>
        <code style="display: block; background: #fff; padding: 15px; border: 1px solid #ccc; border-radius: 3px; font-size: 14px; word-break: break-all;">
            <?php echo Format::htmlchars($mcpEndpoint); ?>
        </code>
    </div>

    <h3><i class="icon-info-sign"></i> <?php echo __('About MCP'); ?></h3>
    <p>
        <?php echo __('The Model Context Protocol (MCP) is an open standard that enables AI agents and assistants to interact with osTicket. MCP provides a standardized way for AI systems to search tickets, create responses, manage tasks, and more.'); ?>
    </p>

    <h3><i class="icon-lock"></i> <?php echo __('Authentication'); ?></h3>
    <p><?php echo __('MCP uses HTTP Basic Authentication with staff credentials. The AI agent must provide:'); ?></p>
    <ul>
        <li><strong><?php echo __('Username'); ?>:</strong> <?php echo __('Staff username or email'); ?></li>
        <li><strong><?php echo __('Password'); ?>:</strong> <?php echo __('Staff password'); ?></li>
    </ul>
    <p><em><?php echo __('Note: The authenticated staff member\'s permissions determine what tickets and data the AI agent can access.'); ?></em></p>

    <h3><i class="icon-wrench"></i> <?php echo __('Available Tools'); ?></h3>
    <p><?php echo __('The MCP server exposes the following tools for AI agents:'); ?></p>

    <table class="list" border="0" cellspacing="1" cellpadding="5" style="margin-bottom: 20px;">
        <thead>
            <tr>
                <th width="25%"><?php echo __('Tool'); ?></th>
                <th width="75%"><?php echo __('Description'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr><td><strong>search_tickets</strong></td><td><?php echo __('Search and filter tickets by status, department, assignee, date range, and keywords'); ?></td></tr>
            <tr><td><strong>get_ticket</strong></td><td><?php echo __('Retrieve full ticket details including thread, collaborators, and custom fields'); ?></td></tr>
            <tr><td><strong>create_ticket</strong></td><td><?php echo __('Create a new support ticket'); ?></td></tr>
            <tr><td><strong>reply_to_ticket</strong></td><td><?php echo __('Post a reply to a ticket (visible to user)'); ?></td></tr>
            <tr><td><strong>add_note_to_ticket</strong></td><td><?php echo __('Add an internal note to a ticket (staff only)'); ?></td></tr>
            <tr><td><strong>update_ticket_status</strong></td><td><?php echo __('Change ticket status (open, closed, resolved, etc.)'); ?></td></tr>
            <tr><td><strong>assign_ticket</strong></td><td><?php echo __('Assign a ticket to a staff member or team'); ?></td></tr>
            <tr><td><strong>search_tasks</strong></td><td><?php echo __('Search and filter tasks'); ?></td></tr>
            <tr><td><strong>create_task</strong></td><td><?php echo __('Create a new task'); ?></td></tr>
            <tr><td><strong>update_task</strong></td><td><?php echo __('Update task details or status'); ?></td></tr>
        </tbody>
    </table>

    <h3><i class="icon-folder-open"></i> <?php echo __('Available Resources'); ?></h3>
    <p><?php echo __('The MCP server provides access to these resources for context:'); ?></p>

    <table class="list" border="0" cellspacing="1" cellpadding="5" style="margin-bottom: 20px;">
        <thead>
            <tr>
                <th width="25%"><?php echo __('Resource'); ?></th>
                <th width="75%"><?php echo __('Description'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr><td><strong>departments</strong></td><td><?php echo __('List of all departments'); ?></td></tr>
            <tr><td><strong>help-topics</strong></td><td><?php echo __('List of all help topics'); ?></td></tr>
            <tr><td><strong>staff</strong></td><td><?php echo __('List of all staff members'); ?></td></tr>
            <tr><td><strong>teams</strong></td><td><?php echo __('List of all teams'); ?></td></tr>
            <tr><td><strong>statuses</strong></td><td><?php echo __('List of all ticket statuses'); ?></td></tr>
            <tr><td><strong>priorities</strong></td><td><?php echo __('List of all priority levels'); ?></td></tr>
        </tbody>
    </table>

    <h3><i class="icon-cog"></i> <?php echo __('Claude Desktop Configuration'); ?></h3>
    <p><?php echo __('To connect Claude Desktop to osTicket, add the following to your Claude Desktop configuration file:'); ?></p>
    <p><strong>Windows:</strong> <code>%APPDATA%\Claude\claude_desktop_config.json</code></p>
    <p><strong>macOS:</strong> <code>~/Library/Application Support/Claude/claude_desktop_config.json</code></p>

    <pre style="background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 13px;">{
  "mcpServers": {
    "osticket": {
      "command": "npx",
      "args": [
        "mcp-remote",
        "<?php echo Format::htmlchars($mcpEndpoint); ?>",
        "--header",
        "Authorization: Basic ${OSTICKET_AUTH}"
      ],
      "env": {
        "OSTICKET_AUTH": "&lt;base64-encoded username:password&gt;"
      }
    }
  }
}</pre>

    <p style="margin-top: 15px;">
        <strong><?php echo __('To generate the Base64 auth string:'); ?></strong>
    </p>
    <pre style="background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 13px;"># Linux/macOS
echo -n "username:password" | base64

# PowerShell
[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes("username:password"))</pre>

    <h3><i class="icon-code"></i> <?php echo __('Direct API Usage'); ?></h3>
    <p><?php echo __('You can also interact with the MCP endpoint directly using JSON-RPC 2.0:'); ?></p>

    <pre style="background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 13px;">curl -X POST <?php echo Format::htmlchars($mcpEndpoint); ?> \
  -H "Content-Type: application/json" \
  -u "username:password" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/call",
    "params": {
      "name": "search_tickets",
      "arguments": {
        "status": "open",
        "limit": 10
      }
    }
  }'</pre>

    <h3><i class="icon-question-sign"></i> <?php echo __('Protocol Methods'); ?></h3>
    <p><?php echo __('The MCP server supports these JSON-RPC methods:'); ?></p>
    <ul>
        <li><code>initialize</code> - <?php echo __('Initialize the MCP session'); ?></li>
        <li><code>tools/list</code> - <?php echo __('List available tools'); ?></li>
        <li><code>tools/call</code> - <?php echo __('Execute a tool'); ?></li>
        <li><code>resources/list</code> - <?php echo __('List available resources'); ?></li>
        <li><code>resources/read</code> - <?php echo __('Read a resource'); ?></li>
    </ul>
</div>
