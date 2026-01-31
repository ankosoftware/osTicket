<?php
if(!defined('OSTADMININC') || !$thisstaff->isAdmin()) die('Access Denied');

// Build MCP endpoint URL from current request
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$basePath = dirname($basePath); // Go up from /scp/
$mcpEndpoint = $protocol . '://' . $host . $basePath . '/api/mcp.json';
?>
<style>
.mcp-container {
    padding: 15px 0;
}
.mcp-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.mcp-section-header {
    background: linear-gradient(to bottom, #f8f8f8, #f0f0f0);
    border-bottom: 1px solid #ddd;
    padding: 12px 20px;
    font-size: 15px;
    font-weight: 600;
    color: #333;
}
.mcp-section-header i {
    margin-right: 8px;
    color: #184E81;
}
.mcp-section-body {
    padding: 20px;
}
.mcp-endpoint-box {
    background: #f0f7ff;
    border: 1px solid #b8d4f0;
    border-radius: 4px;
    padding: 15px 20px;
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 14px;
    color: #184E81;
    word-break: break-all;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.mcp-endpoint-box code {
    flex: 1;
    background: none;
    border: none;
    padding: 0;
}
.mcp-endpoint-box .copy-btn {
    margin-left: 15px;
    padding: 6px 12px;
    background: #184E81;
    color: #fff;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    font-size: 12px;
    white-space: nowrap;
}
.mcp-endpoint-box .copy-btn:hover {
    background: #0d3a5f;
}
.mcp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 15px;
}
.mcp-card {
    background: #fafafa;
    border: 1px solid #e5e5e5;
    border-radius: 4px;
    padding: 15px;
}
.mcp-card h4 {
    margin: 0 0 8px 0;
    font-size: 14px;
    color: #333;
}
.mcp-card p {
    margin: 0;
    font-size: 13px;
    color: #666;
    line-height: 1.5;
}
.mcp-tools-table {
    width: 100%;
    border-collapse: collapse;
}
.mcp-tools-table th {
    text-align: left;
    padding: 10px 15px;
    background: #f5f5f5;
    border-bottom: 2px solid #ddd;
    font-weight: 600;
    color: #333;
}
.mcp-tools-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #eee;
    vertical-align: top;
}
.mcp-tools-table tr:hover td {
    background: #fafafa;
}
.mcp-tools-table code {
    background: #e8f4fc;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 13px;
    color: #184E81;
    font-weight: 500;
}
.mcp-code-block {
    background: #1e1e1e;
    border-radius: 4px;
    padding: 15px 20px;
    overflow-x: auto;
    margin: 10px 0;
}
.mcp-code-block pre {
    margin: 0;
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 13px;
    line-height: 1.6;
    color: #d4d4d4;
    white-space: pre-wrap;
    word-wrap: break-word;
}
.mcp-code-block .comment { color: #6a9955; }
.mcp-code-block .string { color: #ce9178; }
.mcp-code-block .key { color: #9cdcfe; }
.mcp-code-block .value { color: #b5cea8; }
.mcp-tabs {
    display: flex;
    border-bottom: 1px solid #ddd;
    margin-bottom: 0;
    padding: 0;
    list-style: none;
    background: #f8f8f8;
}
.mcp-tabs li {
    margin: 0;
}
.mcp-tabs li a {
    display: block;
    padding: 12px 20px;
    color: #666;
    text-decoration: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    font-weight: 500;
}
.mcp-tabs li a:hover {
    color: #333;
    background: #fff;
}
.mcp-tabs li.active a {
    color: #184E81;
    border-bottom-color: #184E81;
    background: #fff;
}
.mcp-tab-content {
    display: none;
    padding: 20px;
}
.mcp-tab-content.active {
    display: block;
}
.mcp-note {
    background: #fffbe6;
    border: 1px solid #ffe58f;
    border-radius: 4px;
    padding: 12px 15px;
    font-size: 13px;
    color: #614700;
    margin: 15px 0;
}
.mcp-note i {
    margin-right: 8px;
    color: #faad14;
}
.mcp-list {
    margin: 10px 0;
    padding-left: 20px;
}
.mcp-list li {
    margin: 8px 0;
    line-height: 1.5;
}
.mcp-badge {
    display: inline-block;
    padding: 2px 8px;
    background: #e8f4fc;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    color: #184E81;
    text-transform: uppercase;
    margin-left: 8px;
}
</style>

<div class="sticky bar opaque">
    <div class="content">
        <div class="pull-left flush-left">
            <h2><i class="icon-cloud"></i> <?php echo __('MCP Server'); ?>
                <small style="color: #666; font-weight: normal;">&mdash; <?php echo __('Model Context Protocol'); ?></small>
            </h2>
        </div>
    </div>
</div>
<div class="clear"></div>

<div class="mcp-container">
    <!-- Endpoint Section -->
    <div class="mcp-section">
        <div class="mcp-section-header">
            <i class="icon-link"></i><?php echo __('MCP Endpoint'); ?>
        </div>
        <div class="mcp-section-body">
            <p style="margin: 0 0 15px 0; color: #666;">
                <?php echo __('Connect AI agents to osTicket using this endpoint:'); ?>
            </p>
            <div class="mcp-endpoint-box">
                <code id="mcp-endpoint"><?php echo Format::htmlchars($mcpEndpoint); ?></code>
                <button class="copy-btn" onclick="copyEndpoint()">
                    <i class="icon-copy"></i> <?php echo __('Copy'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Quick Start -->
    <div class="mcp-section">
        <div class="mcp-section-header">
            <i class="icon-rocket"></i><?php echo __('Quick Start'); ?>
        </div>
        <div class="mcp-section-body">
            <div class="mcp-grid">
                <div class="mcp-card">
                    <h4><i class="icon-lock" style="color:#184E81"></i> <?php echo __('1. Authentication'); ?></h4>
                    <p><?php echo __('Uses HTTP Basic Auth with staff credentials. The staff member\'s permissions determine data access.'); ?></p>
                </div>
                <div class="mcp-card">
                    <h4><i class="icon-cogs" style="color:#184E81"></i> <?php echo __('2. Configure AI Agent'); ?></h4>
                    <p><?php echo __('Add the endpoint URL to your AI agent (Claude Desktop, custom integration, etc.)'); ?></p>
                </div>
                <div class="mcp-card">
                    <h4><i class="icon-comments" style="color:#184E81"></i> <?php echo __('3. Start Interacting'); ?></h4>
                    <p><?php echo __('AI agent can now search tickets, create responses, manage tasks, and more.'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tools & Resources -->
    <div class="mcp-section">
        <ul class="mcp-tabs">
            <li class="active"><a href="#tools-tab" onclick="switchTab(event, 'tools-tab')"><?php echo __('Available Tools'); ?></a></li>
            <li><a href="#resources-tab" onclick="switchTab(event, 'resources-tab')"><?php echo __('Resources'); ?></a></li>
            <li><a href="#methods-tab" onclick="switchTab(event, 'methods-tab')"><?php echo __('Protocol Methods'); ?></a></li>
        </ul>

        <div id="tools-tab" class="mcp-tab-content active">
            <table class="mcp-tools-table">
                <thead>
                    <tr>
                        <th width="22%"><?php echo __('Tool'); ?></th>
                        <th><?php echo __('Description'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><code>search_tickets</code></td><td><?php echo __('Search and filter tickets by status, department, assignee, date range, and keywords'); ?></td></tr>
                    <tr><td><code>get_ticket</code></td><td><?php echo __('Retrieve full ticket details including thread, collaborators, and custom fields'); ?></td></tr>
                    <tr><td><code>create_ticket</code></td><td><?php echo __('Create a new support ticket on behalf of a user'); ?></td></tr>
                    <tr><td><code>reply_to_ticket</code></td><td><?php echo __('Post a reply to a ticket (visible to user)'); ?></td></tr>
                    <tr><td><code>add_note_to_ticket</code></td><td><?php echo __('Add an internal note to a ticket (staff only)'); ?></td></tr>
                    <tr><td><code>update_ticket_status</code></td><td><?php echo __('Change ticket status (open, closed, resolved, etc.)'); ?></td></tr>
                    <tr><td><code>assign_ticket</code></td><td><?php echo __('Assign a ticket to a staff member or team'); ?></td></tr>
                    <tr><td><code>search_tasks</code></td><td><?php echo __('Search and filter tasks'); ?></td></tr>
                    <tr><td><code>create_task</code></td><td><?php echo __('Create a new standalone or ticket-linked task'); ?></td></tr>
                    <tr><td><code>update_task</code></td><td><?php echo __('Update task details, status, or assignment'); ?></td></tr>
                </tbody>
            </table>
        </div>

        <div id="resources-tab" class="mcp-tab-content">
            <table class="mcp-tools-table">
                <thead>
                    <tr>
                        <th width="22%"><?php echo __('Resource'); ?></th>
                        <th><?php echo __('Description'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><code>departments</code></td><td><?php echo __('List of all departments with IDs and settings'); ?></td></tr>
                    <tr><td><code>help-topics</code></td><td><?php echo __('List of all help topics for ticket categorization'); ?></td></tr>
                    <tr><td><code>staff</code></td><td><?php echo __('List of all staff members with their departments'); ?></td></tr>
                    <tr><td><code>teams</code></td><td><?php echo __('List of all teams for ticket assignment'); ?></td></tr>
                    <tr><td><code>statuses</code></td><td><?php echo __('List of all ticket statuses and their states'); ?></td></tr>
                    <tr><td><code>priorities</code></td><td><?php echo __('List of all priority levels'); ?></td></tr>
                </tbody>
            </table>
        </div>

        <div id="methods-tab" class="mcp-tab-content">
            <p style="margin-bottom: 15px; color: #666;"><?php echo __('The MCP server implements JSON-RPC 2.0 with these methods:'); ?></p>
            <table class="mcp-tools-table">
                <thead>
                    <tr>
                        <th width="22%"><?php echo __('Method'); ?></th>
                        <th><?php echo __('Description'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><code>initialize</code></td><td><?php echo __('Initialize the MCP session and exchange capabilities'); ?></td></tr>
                    <tr><td><code>tools/list</code></td><td><?php echo __('List all available tools with their schemas'); ?></td></tr>
                    <tr><td><code>tools/call</code></td><td><?php echo __('Execute a tool with the provided arguments'); ?></td></tr>
                    <tr><td><code>resources/list</code></td><td><?php echo __('List all available resources'); ?></td></tr>
                    <tr><td><code>resources/read</code></td><td><?php echo __('Read the contents of a specific resource'); ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Configuration Examples -->
    <div class="mcp-section">
        <ul class="mcp-tabs">
            <li class="active"><a href="#claude-tab" onclick="switchTab(event, 'claude-tab')"><?php echo __('Claude Desktop'); ?></a></li>
            <li><a href="#curl-tab" onclick="switchTab(event, 'curl-tab')"><?php echo __('cURL / Direct API'); ?></a></li>
        </ul>

        <div id="claude-tab" class="mcp-tab-content active">
            <p style="margin-bottom: 10px;"><?php echo __('Add this to your Claude Desktop configuration:'); ?></p>
            <ul class="mcp-list" style="color: #666; font-size: 13px;">
                <li><strong>Windows:</strong> <code style="background:#f0f0f0; padding:2px 6px; border-radius:3px;">%APPDATA%\Claude\claude_desktop_config.json</code></li>
                <li><strong>macOS:</strong> <code style="background:#f0f0f0; padding:2px 6px; border-radius:3px;">~/Library/Application Support/Claude/claude_desktop_config.json</code></li>
            </ul>

            <div class="mcp-code-block">
<pre>{
  <span class="key">"mcpServers"</span>: {
    <span class="key">"osticket"</span>: {
      <span class="key">"command"</span>: <span class="string">"npx"</span>,
      <span class="key">"args"</span>: [
        <span class="string">"mcp-remote"</span>,
        <span class="string">"<?php echo Format::htmlchars($mcpEndpoint); ?>"</span>,
        <span class="string">"--header"</span>,
        <span class="string">"Authorization: Basic ${OSTICKET_AUTH}"</span>
      ],
      <span class="key">"env"</span>: {
        <span class="key">"OSTICKET_AUTH"</span>: <span class="string">"&lt;base64-encoded username:password&gt;"</span>
      }
    }
  }
}</pre>
            </div>

            <div class="mcp-note">
                <i class="icon-info-sign"></i>
                <strong><?php echo __('Generate Base64 auth string:'); ?></strong>
                <div class="mcp-code-block" style="margin-top: 10px; margin-bottom: 0;">
<pre><span class="comment"># Linux/macOS</span>
echo -n "username:password" | base64

<span class="comment"># PowerShell</span>
[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes("username:password"))</pre>
                </div>
            </div>
        </div>

        <div id="curl-tab" class="mcp-tab-content">
            <p style="margin-bottom: 10px;"><?php echo __('Interact with the MCP endpoint directly using JSON-RPC 2.0:'); ?></p>

            <div class="mcp-code-block">
<pre>curl -X POST <?php echo Format::htmlchars($mcpEndpoint); ?> \
  -H <span class="string">"Content-Type: application/json"</span> \
  -u <span class="string">"username:password"</span> \
  -d <span class="string">'{
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
  }'</span></pre>
            </div>

            <p style="margin: 15px 0 10px 0;"><?php echo __('List available tools:'); ?></p>
            <div class="mcp-code-block">
<pre>curl -X POST <?php echo Format::htmlchars($mcpEndpoint); ?> \
  -H <span class="string">"Content-Type: application/json"</span> \
  -u <span class="string">"username:password"</span> \
  -d <span class="string">'{"jsonrpc": "2.0", "id": 1, "method": "tools/list"}'</span></pre>
            </div>
        </div>
    </div>
</div>

<script>
function copyEndpoint() {
    var endpoint = document.getElementById('mcp-endpoint').textContent;
    navigator.clipboard.writeText(endpoint).then(function() {
        var btn = document.querySelector('.copy-btn');
        var originalText = btn.innerHTML;
        btn.innerHTML = '<i class="icon-ok"></i> <?php echo __('Copied!'); ?>';
        setTimeout(function() {
            btn.innerHTML = originalText;
        }, 2000);
    });
}

function switchTab(event, tabId) {
    event.preventDefault();
    var section = event.target.closest('.mcp-section');
    section.querySelectorAll('.mcp-tabs li').forEach(function(li) {
        li.classList.remove('active');
    });
    section.querySelectorAll('.mcp-tab-content').forEach(function(content) {
        content.classList.remove('active');
    });
    event.target.parentElement.classList.add('active');
    section.querySelector('#' + tabId).classList.add('active');
}
</script>
