<?php
/*********************************************************************
    class.mcp.php

    MCP (Model Context Protocol) Protocol Handler

    Implements JSON-RPC 2.0 methods for AI agent interactions with osTicket.
    Provides tools for ticket and task management, and resources for
    reference data.

    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class McpProtocolHandler {

    const SERVER_NAME = 'osTicket MCP Server';
    const SERVER_VERSION = '1.0.0';
    const PROTOCOL_VERSION = '2024-11-05';

    private $staff;
    private $tools;
    private $resources;

    public function __construct($staff) {
        $this->staff = $staff;
        $this->initializeTools();
        $this->initializeResources();
    }

    /**
     * Define available tools
     */
    private function initializeTools() {
        $this->tools = array(
            'search_tickets' => array(
                'name' => 'search_tickets',
                'description' => 'Search for tickets with various filters including status, department, user, date range, and text search',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'q' => array(
                            'type' => 'string',
                            'description' => 'Full-text search query'
                        ),
                        'status' => array(
                            'type' => 'string',
                            'description' => 'Filter by status state (open, closed, archived, deleted)',
                            'enum' => array('open', 'closed', 'archived', 'deleted')
                        ),
                        'dept_id' => array(
                            'type' => 'integer',
                            'description' => 'Filter by department ID'
                        ),
                        'staff_id' => array(
                            'type' => 'integer',
                            'description' => 'Filter by assigned agent ID'
                        ),
                        'team_id' => array(
                            'type' => 'integer',
                            'description' => 'Filter by assigned team ID'
                        ),
                        'topic_id' => array(
                            'type' => 'integer',
                            'description' => 'Filter by help topic ID'
                        ),
                        'user_email' => array(
                            'type' => 'string',
                            'description' => 'Filter by ticket owner email'
                        ),
                        'created_after' => array(
                            'type' => 'string',
                            'description' => 'Filter tickets created after this date (YYYY-MM-DD)'
                        ),
                        'created_before' => array(
                            'type' => 'string',
                            'description' => 'Filter tickets created before this date (YYYY-MM-DD)'
                        ),
                        'page' => array(
                            'type' => 'integer',
                            'description' => 'Page number for pagination (default: 1)',
                            'default' => 1
                        ),
                        'limit' => array(
                            'type' => 'integer',
                            'description' => 'Results per page (default: 25, max: 100)',
                            'default' => 25
                        ),
                        'sort' => array(
                            'type' => 'string',
                            'description' => 'Sort field (created, updated, priority, number)',
                            'enum' => array('created', 'updated', 'priority', 'number'),
                            'default' => 'created'
                        ),
                        'order' => array(
                            'type' => 'string',
                            'description' => 'Sort order (asc, desc)',
                            'enum' => array('asc', 'desc'),
                            'default' => 'desc'
                        )
                    )
                )
            ),
            'get_ticket' => array(
                'name' => 'get_ticket',
                'description' => 'Get full details of a ticket including thread entries, custom fields, and collaborators',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'ticket_id' => array(
                            'type' => 'integer',
                            'description' => 'Ticket ID'
                        ),
                        'ticket_number' => array(
                            'type' => 'string',
                            'description' => 'Ticket number (alternative to ticket_id)'
                        ),
                        'include_thread' => array(
                            'type' => 'boolean',
                            'description' => 'Include thread entries (messages, responses, notes)',
                            'default' => true
                        )
                    )
                )
            ),
            'create_ticket' => array(
                'name' => 'create_ticket',
                'description' => 'Create a new support ticket',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'subject' => array(
                            'type' => 'string',
                            'description' => 'Ticket subject'
                        ),
                        'message' => array(
                            'type' => 'string',
                            'description' => 'Initial ticket message/description'
                        ),
                        'name' => array(
                            'type' => 'string',
                            'description' => 'User full name'
                        ),
                        'email' => array(
                            'type' => 'string',
                            'description' => 'User email address'
                        ),
                        'topic_id' => array(
                            'type' => 'integer',
                            'description' => 'Help topic ID'
                        ),
                        'dept_id' => array(
                            'type' => 'integer',
                            'description' => 'Department ID (optional, defaults to topic default)'
                        ),
                        'priority_id' => array(
                            'type' => 'integer',
                            'description' => 'Priority ID'
                        ),
                        'source' => array(
                            'type' => 'string',
                            'description' => 'Ticket source (Phone, Email, Web, API, Other)',
                            'default' => 'API'
                        ),
                        'duedate' => array(
                            'type' => 'string',
                            'description' => 'Due date (YYYY-MM-DD HH:MM:SS)'
                        ),
                        'assign_to_staff_id' => array(
                            'type' => 'integer',
                            'description' => 'Assign ticket to this staff ID'
                        ),
                        'assign_to_team_id' => array(
                            'type' => 'integer',
                            'description' => 'Assign ticket to this team ID'
                        )
                    ),
                    'required' => array('subject', 'message', 'email', 'topic_id')
                )
            ),
            'reply_to_ticket' => array(
                'name' => 'reply_to_ticket',
                'description' => 'Post a reply to a ticket (visible to the ticket owner)',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'ticket_id' => array(
                            'type' => 'integer',
                            'description' => 'Ticket ID'
                        ),
                        'ticket_number' => array(
                            'type' => 'string',
                            'description' => 'Ticket number (alternative to ticket_id)'
                        ),
                        'message' => array(
                            'type' => 'string',
                            'description' => 'Reply message content'
                        ),
                        'status_id' => array(
                            'type' => 'integer',
                            'description' => 'Set ticket status after reply (optional)'
                        ),
                        'alert' => array(
                            'type' => 'boolean',
                            'description' => 'Send email notification to user',
                            'default' => true
                        )
                    ),
                    'required' => array('message')
                )
            ),
            'add_note_to_ticket' => array(
                'name' => 'add_note_to_ticket',
                'description' => 'Add an internal note to a ticket (not visible to the ticket owner)',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'ticket_id' => array(
                            'type' => 'integer',
                            'description' => 'Ticket ID'
                        ),
                        'ticket_number' => array(
                            'type' => 'string',
                            'description' => 'Ticket number (alternative to ticket_id)'
                        ),
                        'note' => array(
                            'type' => 'string',
                            'description' => 'Note content'
                        ),
                        'title' => array(
                            'type' => 'string',
                            'description' => 'Note title (optional)'
                        ),
                        'alert' => array(
                            'type' => 'boolean',
                            'description' => 'Send alert to assigned agents',
                            'default' => false
                        )
                    ),
                    'required' => array('note')
                )
            ),
            'update_ticket_status' => array(
                'name' => 'update_ticket_status',
                'description' => 'Change the status of a ticket (open, close, etc.)',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'ticket_id' => array(
                            'type' => 'integer',
                            'description' => 'Ticket ID'
                        ),
                        'ticket_number' => array(
                            'type' => 'string',
                            'description' => 'Ticket number (alternative to ticket_id)'
                        ),
                        'status_id' => array(
                            'type' => 'integer',
                            'description' => 'New status ID'
                        ),
                        'comments' => array(
                            'type' => 'string',
                            'description' => 'Comments for the status change'
                        )
                    ),
                    'required' => array('status_id')
                )
            ),
            'assign_ticket' => array(
                'name' => 'assign_ticket',
                'description' => 'Assign a ticket to a staff member or team',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'ticket_id' => array(
                            'type' => 'integer',
                            'description' => 'Ticket ID'
                        ),
                        'ticket_number' => array(
                            'type' => 'string',
                            'description' => 'Ticket number (alternative to ticket_id)'
                        ),
                        'staff_id' => array(
                            'type' => 'integer',
                            'description' => 'Staff member ID to assign to'
                        ),
                        'team_id' => array(
                            'type' => 'integer',
                            'description' => 'Team ID to assign to'
                        ),
                        'comments' => array(
                            'type' => 'string',
                            'description' => 'Assignment comments'
                        ),
                        'alert' => array(
                            'type' => 'boolean',
                            'description' => 'Send alert to assignee',
                            'default' => true
                        )
                    )
                )
            ),
            'search_tasks' => array(
                'name' => 'search_tasks',
                'description' => 'Search for tasks with various filters',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'status' => array(
                            'type' => 'string',
                            'description' => 'Filter by status (open, closed)',
                            'enum' => array('open', 'closed')
                        ),
                        'dept_id' => array(
                            'type' => 'integer',
                            'description' => 'Filter by department ID'
                        ),
                        'staff_id' => array(
                            'type' => 'integer',
                            'description' => 'Filter by assigned agent ID'
                        ),
                        'team_id' => array(
                            'type' => 'integer',
                            'description' => 'Filter by assigned team ID'
                        ),
                        'ticket_id' => array(
                            'type' => 'integer',
                            'description' => 'Filter by associated ticket ID'
                        ),
                        'page' => array(
                            'type' => 'integer',
                            'description' => 'Page number for pagination',
                            'default' => 1
                        ),
                        'limit' => array(
                            'type' => 'integer',
                            'description' => 'Results per page (max 100)',
                            'default' => 25
                        )
                    )
                )
            ),
            'create_task' => array(
                'name' => 'create_task',
                'description' => 'Create a new task, optionally linked to a ticket',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'title' => array(
                            'type' => 'string',
                            'description' => 'Task title'
                        ),
                        'description' => array(
                            'type' => 'string',
                            'description' => 'Task description'
                        ),
                        'dept_id' => array(
                            'type' => 'integer',
                            'description' => 'Department ID'
                        ),
                        'ticket_id' => array(
                            'type' => 'integer',
                            'description' => 'Link task to this ticket ID'
                        ),
                        'staff_id' => array(
                            'type' => 'integer',
                            'description' => 'Assign to staff member'
                        ),
                        'team_id' => array(
                            'type' => 'integer',
                            'description' => 'Assign to team'
                        ),
                        'duedate' => array(
                            'type' => 'string',
                            'description' => 'Due date (YYYY-MM-DD HH:MM:SS)'
                        )
                    ),
                    'required' => array('title', 'dept_id')
                )
            ),
            'update_task' => array(
                'name' => 'update_task',
                'description' => 'Update a task status or add a note',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'task_id' => array(
                            'type' => 'integer',
                            'description' => 'Task ID'
                        ),
                        'action' => array(
                            'type' => 'string',
                            'description' => 'Action to perform',
                            'enum' => array('close', 'reopen', 'add_note')
                        ),
                        'note' => array(
                            'type' => 'string',
                            'description' => 'Note content (required for add_note action)'
                        )
                    ),
                    'required' => array('task_id', 'action')
                )
            )
        );
    }

    /**
     * Define available resources
     */
    private function initializeResources() {
        $this->resources = array(
            'osticket://departments' => array(
                'uri' => 'osticket://departments',
                'name' => 'Departments',
                'description' => 'List of all departments in the system',
                'mimeType' => 'application/json'
            ),
            'osticket://help-topics' => array(
                'uri' => 'osticket://help-topics',
                'name' => 'Help Topics',
                'description' => 'List of all help topics',
                'mimeType' => 'application/json'
            ),
            'osticket://staff' => array(
                'uri' => 'osticket://staff',
                'name' => 'Staff Members',
                'description' => 'List of all staff members',
                'mimeType' => 'application/json'
            ),
            'osticket://teams' => array(
                'uri' => 'osticket://teams',
                'name' => 'Teams',
                'description' => 'List of all teams',
                'mimeType' => 'application/json'
            ),
            'osticket://statuses' => array(
                'uri' => 'osticket://statuses',
                'name' => 'Ticket Statuses',
                'description' => 'List of all ticket statuses',
                'mimeType' => 'application/json'
            ),
            'osticket://priorities' => array(
                'uri' => 'osticket://priorities',
                'name' => 'Priorities',
                'description' => 'List of all priority levels',
                'mimeType' => 'application/json'
            )
        );
    }

    /**
     * Handle incoming JSON-RPC request
     */
    public function handleRequest($method, $params, $id) {
        switch ($method) {
            case 'initialize':
                return $this->handleInitialize($params);

            case 'tools/list':
                return $this->handleToolsList();

            case 'tools/call':
                return $this->handleToolsCall($params);

            case 'resources/list':
                return $this->handleResourcesList();

            case 'resources/read':
                return $this->handleResourcesRead($params);

            default:
                throw new McpException(-32601, "Method not found: {$method}");
        }
    }

    /**
     * Handle initialize request
     */
    private function handleInitialize($params) {
        return array(
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => array(
                'tools' => new stdClass(),
                'resources' => new stdClass()
            ),
            'serverInfo' => array(
                'name' => self::SERVER_NAME,
                'version' => self::SERVER_VERSION
            )
        );
    }

    /**
     * Handle tools/list request
     */
    private function handleToolsList() {
        $tools = array();
        foreach ($this->tools as $tool) {
            $tools[] = array(
                'name' => $tool['name'],
                'description' => $tool['description'],
                'inputSchema' => $tool['inputSchema']
            );
        }
        return array('tools' => $tools);
    }

    /**
     * Handle tools/call request
     */
    private function handleToolsCall($params) {
        if (!isset($params['name'])) {
            throw new McpException(-32602, 'Missing required parameter: name');
        }

        $toolName = $params['name'];
        $arguments = $params['arguments'] ?? array();

        if (!isset($this->tools[$toolName])) {
            throw new McpException(-32602, "Unknown tool: {$toolName}");
        }

        $methodName = 'tool_' . str_replace('-', '_', $toolName);
        if (!method_exists($this, $methodName)) {
            throw new McpException(-32603, "Tool not implemented: {$toolName}");
        }

        try {
            $result = $this->$methodName($arguments);
            return array(
                'content' => array(
                    array(
                        'type' => 'text',
                        'text' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                    )
                )
            );
        } catch (McpException $e) {
            throw $e;
        } catch (Exception $e) {
            return array(
                'content' => array(
                    array(
                        'type' => 'text',
                        'text' => json_encode(array('error' => $e->getMessage()))
                    )
                ),
                'isError' => true
            );
        }
    }

    /**
     * Handle resources/list request
     */
    private function handleResourcesList() {
        $resources = array();
        foreach ($this->resources as $resource) {
            $resources[] = array(
                'uri' => $resource['uri'],
                'name' => $resource['name'],
                'description' => $resource['description'],
                'mimeType' => $resource['mimeType']
            );
        }
        return array('resources' => $resources);
    }

    /**
     * Handle resources/read request
     */
    private function handleResourcesRead($params) {
        if (!isset($params['uri'])) {
            throw new McpException(-32602, 'Missing required parameter: uri');
        }

        $uri = $params['uri'];
        if (!isset($this->resources[$uri])) {
            throw new McpException(-32602, "Unknown resource: {$uri}");
        }

        $methodName = 'resource_' . str_replace(array('osticket://', '-'), array('', '_'), $uri);
        if (!method_exists($this, $methodName)) {
            throw new McpException(-32603, "Resource not implemented: {$uri}");
        }

        $data = $this->$methodName();

        return array(
            'contents' => array(
                array(
                    'uri' => $uri,
                    'mimeType' => 'application/json',
                    'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                )
            )
        );
    }

    // =========================================================================
    // Tool Implementations
    // =========================================================================

    /**
     * Search tickets tool
     */
    private function tool_search_tickets($args) {
        $page = max(1, intval($args['page'] ?? 1));
        $limit = min(100, max(1, intval($args['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        // Start building query
        $tickets = Ticket::objects();

        // Apply visibility constraints based on staff permissions
        $tickets = $this->applyTicketVisibility($tickets);

        // Apply filters
        if (!empty($args['status'])) {
            $tickets->filter(array('status__state' => $args['status']));
        }

        if (!empty($args['dept_id'])) {
            $tickets->filter(array('dept_id' => intval($args['dept_id'])));
        }

        if (!empty($args['staff_id'])) {
            $tickets->filter(array('staff_id' => intval($args['staff_id'])));
        }

        if (!empty($args['team_id'])) {
            $tickets->filter(array('team_id' => intval($args['team_id'])));
        }

        if (!empty($args['topic_id'])) {
            $tickets->filter(array('topic_id' => intval($args['topic_id'])));
        }

        if (!empty($args['user_email'])) {
            $tickets->filter(array('user__default_email__address' => $args['user_email']));
        }

        if (!empty($args['created_after'])) {
            $tickets->filter(array('created__gte' => $args['created_after']));
        }

        if (!empty($args['created_before'])) {
            $tickets->filter(array('created__lte' => $args['created_before']));
        }

        // Full-text search
        if (!empty($args['q'])) {
            $q = $args['q'];
            $tickets->filter(Q::any(array(
                'cdata__subject__contains' => $q,
                'number__contains' => $q,
                'user__default_email__address__contains' => $q
            )));
        }

        // Sorting
        $sortField = $args['sort'] ?? 'created';
        $sortOrder = ($args['order'] ?? 'desc') === 'asc' ? '' : '-';
        $sortMap = array(
            'created' => 'created',
            'updated' => 'lastupdate',
            'priority' => 'cdata__priority',
            'number' => 'number'
        );
        $sortColumn = $sortMap[$sortField] ?? 'created';
        $tickets->order_by($sortOrder . $sortColumn);

        // Get total count before pagination
        $total = $tickets->count();

        // Apply pagination
        $tickets->limit($limit)->offset($offset);

        // Format results
        $results = array();
        foreach ($tickets as $ticket) {
            $results[] = $this->formatTicketSummary($ticket);
        }

        return array(
            'tickets' => $results,
            'pagination' => array(
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            )
        );
    }

    /**
     * Get ticket details tool
     */
    private function tool_get_ticket($args) {
        $ticket = $this->resolveTicket($args);

        if (!$this->canAccessTicket($ticket)) {
            throw new McpException(-32602, 'Permission denied: Cannot access this ticket');
        }

        $includeThread = $args['include_thread'] ?? true;
        return $this->formatTicketFull($ticket, $includeThread);
    }

    /**
     * Create ticket tool
     */
    private function tool_create_ticket($args) {
        // Check permission
        if (!$this->staff->hasPerm(Ticket::PERM_CREATE)) {
            throw new McpException(-32602, 'Permission denied: Cannot create tickets');
        }

        // Validate required fields
        foreach (array('subject', 'message', 'email', 'topic_id') as $field) {
            if (empty($args[$field])) {
                throw new McpException(-32602, "Missing required field: {$field}");
            }
        }

        // Prepare ticket data
        $vars = array(
            'subject' => $args['subject'],
            'message' => new TextThreadEntryBody($args['message']),
            'email' => $args['email'],
            'name' => $args['name'] ?? '',
            'topicId' => intval($args['topic_id']),
            'source' => $args['source'] ?? 'API',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        );

        if (!empty($args['dept_id'])) {
            $vars['deptId'] = intval($args['dept_id']);
        }

        if (!empty($args['priority_id'])) {
            $vars['priorityId'] = intval($args['priority_id']);
        }

        if (!empty($args['duedate'])) {
            $vars['duedate'] = $args['duedate'];
        }

        if (!empty($args['assign_to_staff_id'])) {
            $vars['staffId'] = intval($args['assign_to_staff_id']);
        }

        if (!empty($args['assign_to_team_id'])) {
            $vars['teamId'] = intval($args['assign_to_team_id']);
        }

        $errors = array();
        $ticket = Ticket::create($vars, $errors, 'API', true, true);

        if (!$ticket || $errors) {
            $errorMsg = is_array($errors) ? implode(', ', array_filter($errors)) : 'Unknown error';
            throw new McpException(-32602, "Failed to create ticket: {$errorMsg}");
        }

        return array(
            'success' => true,
            'ticket' => $this->formatTicketSummary($ticket)
        );
    }

    /**
     * Reply to ticket tool
     */
    private function tool_reply_to_ticket($args) {
        $ticket = $this->resolveTicket($args);

        // Check permission
        $role = $ticket->getRole($this->staff);
        if (!$role || !$role->hasPerm(Ticket::PERM_REPLY)) {
            throw new McpException(-32602, 'Permission denied: Cannot reply to this ticket');
        }

        if (empty($args['message'])) {
            throw new McpException(-32602, 'Missing required field: message');
        }

        $vars = array(
            'response' => new TextThreadEntryBody($args['message']),
            'staffId' => $this->staff->getId(),
            'poster' => $this->staff,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        );

        if (!empty($args['status_id'])) {
            $vars['reply_status_id'] = intval($args['status_id']);
        }

        $alert = $args['alert'] ?? true;
        $errors = array();

        // Temporarily set global staff for postReply
        global $thisstaff;
        $oldStaff = $thisstaff;
        $thisstaff = $this->staff;

        $response = $ticket->postReply($vars, $errors, $alert);

        $thisstaff = $oldStaff;

        if (!$response) {
            $errorMsg = is_array($errors) ? implode(', ', array_filter($errors)) : 'Unknown error';
            throw new McpException(-32602, "Failed to post reply: {$errorMsg}");
        }

        return array(
            'success' => true,
            'entry_id' => $response->getId(),
            'ticket_number' => $ticket->getNumber()
        );
    }

    /**
     * Add note to ticket tool
     */
    private function tool_add_note_to_ticket($args) {
        $ticket = $this->resolveTicket($args);

        // Check permission - staff can add notes if they can access the ticket
        if (!$this->canAccessTicket($ticket)) {
            throw new McpException(-32602, 'Permission denied: Cannot access this ticket');
        }

        if (empty($args['note'])) {
            throw new McpException(-32602, 'Missing required field: note');
        }

        $vars = array(
            'note' => new TextThreadEntryBody($args['note']),
            'staffId' => $this->staff->getId(),
            'poster' => $this->staff,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        );

        if (!empty($args['title'])) {
            $vars['title'] = $args['title'];
        }

        $alert = $args['alert'] ?? false;
        $errors = array();

        // Temporarily set global staff
        global $thisstaff;
        $oldStaff = $thisstaff;
        $thisstaff = $this->staff;

        $note = $ticket->postNote($vars, $errors, $this->staff, $alert);

        $thisstaff = $oldStaff;

        if (!$note) {
            $errorMsg = is_array($errors) ? implode(', ', array_filter($errors)) : 'Unknown error';
            throw new McpException(-32602, "Failed to add note: {$errorMsg}");
        }

        return array(
            'success' => true,
            'entry_id' => $note->getId(),
            'ticket_number' => $ticket->getNumber()
        );
    }

    /**
     * Update ticket status tool
     */
    private function tool_update_ticket_status($args) {
        $ticket = $this->resolveTicket($args);

        if (empty($args['status_id'])) {
            throw new McpException(-32602, 'Missing required field: status_id');
        }

        $status = TicketStatus::lookup(intval($args['status_id']));
        if (!$status) {
            throw new McpException(-32602, 'Invalid status_id');
        }

        // Check permission based on status state
        $role = $ticket->getRole($this->staff);
        if (!$role) {
            throw new McpException(-32602, 'Permission denied: No role for this ticket');
        }

        if ($status->getState() === 'closed' && !$role->hasPerm(Ticket::PERM_CLOSE)) {
            throw new McpException(-32602, 'Permission denied: Cannot close this ticket');
        }

        $comments = $args['comments'] ?? '';
        $errors = array();

        // Temporarily set global staff
        global $thisstaff;
        $oldStaff = $thisstaff;
        $thisstaff = $this->staff;

        $result = $ticket->setStatus($status, $comments, $errors);

        $thisstaff = $oldStaff;

        if (!$result) {
            $errorMsg = is_array($errors) && isset($errors['err']) ? $errors['err'] : 'Status change failed';
            throw new McpException(-32602, "Failed to update status: {$errorMsg}");
        }

        return array(
            'success' => true,
            'ticket_number' => $ticket->getNumber(),
            'new_status' => array(
                'id' => $status->getId(),
                'name' => $status->getName(),
                'state' => $status->getState()
            )
        );
    }

    /**
     * Assign ticket tool
     */
    private function tool_assign_ticket($args) {
        $ticket = $this->resolveTicket($args);

        // Check permission
        $role = $ticket->getRole($this->staff);
        if (!$role || !$role->hasPerm(Ticket::PERM_ASSIGN)) {
            throw new McpException(-32602, 'Permission denied: Cannot assign this ticket');
        }

        if (empty($args['staff_id']) && empty($args['team_id'])) {
            throw new McpException(-32602, 'Must specify staff_id or team_id');
        }

        // Create assignment form data
        $assignee = null;
        if (!empty($args['staff_id'])) {
            $assignee = Staff::lookup(intval($args['staff_id']));
            if (!$assignee) {
                throw new McpException(-32602, 'Invalid staff_id');
            }
        } elseif (!empty($args['team_id'])) {
            $assignee = Team::lookup(intval($args['team_id']));
            if (!$assignee) {
                throw new McpException(-32602, 'Invalid team_id');
            }
        }

        // Use AssignmentForm if available, otherwise direct assignment
        $errors = array();
        $alert = $args['alert'] ?? true;

        // Temporarily set global staff
        global $thisstaff;
        $oldStaff = $thisstaff;
        $thisstaff = $this->staff;

        // Direct assignment approach
        if ($assignee instanceof Staff) {
            $ticket->setStaffId($assignee->getId());
        } elseif ($assignee instanceof Team) {
            $ticket->setTeamId($assignee->getId());
        }

        $result = $ticket->save();

        // Log the assignment
        if ($result) {
            $ticket->logEvent('assigned', array(
                'staff' => $assignee instanceof Staff
                    ? array($assignee->getId(), (string) $assignee->getName())
                    : null,
                'team' => $assignee instanceof Team
                    ? $assignee->getId()
                    : null
            ));
        }

        $thisstaff = $oldStaff;

        if (!$result) {
            throw new McpException(-32602, 'Failed to assign ticket');
        }

        return array(
            'success' => true,
            'ticket_number' => $ticket->getNumber(),
            'assignee' => array(
                'type' => $assignee instanceof Staff ? 'staff' : 'team',
                'id' => $assignee->getId(),
                'name' => (string) $assignee->getName()
            )
        );
    }

    /**
     * Search tasks tool
     */
    private function tool_search_tasks($args) {
        $page = max(1, intval($args['page'] ?? 1));
        $limit = min(100, max(1, intval($args['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        $tasks = Task::objects();

        // Apply filters
        if (!empty($args['status'])) {
            if ($args['status'] === 'open') {
                $tasks->filter(array('flags__hasbit' => TaskModel::ISOPEN));
            } else {
                $tasks->filter(Q::not(array('flags__hasbit' => TaskModel::ISOPEN)));
            }
        }

        if (!empty($args['dept_id'])) {
            $tasks->filter(array('dept_id' => intval($args['dept_id'])));
        }

        if (!empty($args['staff_id'])) {
            $tasks->filter(array('staff_id' => intval($args['staff_id'])));
        }

        if (!empty($args['team_id'])) {
            $tasks->filter(array('team_id' => intval($args['team_id'])));
        }

        if (!empty($args['ticket_id'])) {
            $tasks->filter(array('object_id' => intval($args['ticket_id'])));
        }

        $tasks->order_by('-created');

        $total = $tasks->count();
        $tasks->limit($limit)->offset($offset);

        $results = array();
        foreach ($tasks as $task) {
            $results[] = $this->formatTaskSummary($task);
        }

        return array(
            'tasks' => $results,
            'pagination' => array(
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            )
        );
    }

    /**
     * Create task tool
     */
    private function tool_create_task($args) {
        // Check permission
        if (!$this->staff->hasPerm(TaskModel::PERM_CREATE)) {
            throw new McpException(-32602, 'Permission denied: Cannot create tasks');
        }

        if (empty($args['title'])) {
            throw new McpException(-32602, 'Missing required field: title');
        }

        if (empty($args['dept_id'])) {
            throw new McpException(-32602, 'Missing required field: dept_id');
        }

        $vars = array(
            'title' => $args['title'],
            'dept_id' => intval($args['dept_id']),
            'description' => $args['description'] ?? ''
        );

        if (!empty($args['staff_id'])) {
            $vars['staff_id'] = intval($args['staff_id']);
        }

        if (!empty($args['team_id'])) {
            $vars['team_id'] = intval($args['team_id']);
        }

        if (!empty($args['duedate'])) {
            $vars['duedate'] = $args['duedate'];
        }

        if (!empty($args['ticket_id'])) {
            $vars['object_id'] = intval($args['ticket_id']);
            $vars['object_type'] = 'T'; // Ticket
        }

        $errors = array();

        // Temporarily set global staff
        global $thisstaff;
        $oldStaff = $thisstaff;
        $thisstaff = $this->staff;

        $task = Task::create($vars, $errors);

        $thisstaff = $oldStaff;

        if (!$task || $errors) {
            $errorMsg = is_array($errors) ? implode(', ', array_filter($errors)) : 'Unknown error';
            throw new McpException(-32602, "Failed to create task: {$errorMsg}");
        }

        return array(
            'success' => true,
            'task' => $this->formatTaskSummary($task)
        );
    }

    /**
     * Update task tool
     */
    private function tool_update_task($args) {
        if (empty($args['task_id'])) {
            throw new McpException(-32602, 'Missing required field: task_id');
        }

        if (empty($args['action'])) {
            throw new McpException(-32602, 'Missing required field: action');
        }

        $task = Task::lookup(intval($args['task_id']));
        if (!$task) {
            throw new McpException(-32602, 'Task not found');
        }

        $errors = array();

        // Temporarily set global staff
        global $thisstaff;
        $oldStaff = $thisstaff;
        $thisstaff = $this->staff;

        $result = false;
        switch ($args['action']) {
            case 'close':
                if (!$this->staff->hasPerm(TaskModel::PERM_CLOSE)) {
                    throw new McpException(-32602, 'Permission denied: Cannot close tasks');
                }
                $result = $task->setStatus('closed', '', $errors);
                break;

            case 'reopen':
                if (!$this->staff->hasPerm(TaskModel::PERM_CLOSE)) {
                    throw new McpException(-32602, 'Permission denied: Cannot reopen tasks');
                }
                $result = $task->setStatus('open', '', $errors);
                break;

            case 'add_note':
                if (empty($args['note'])) {
                    throw new McpException(-32602, 'Missing required field: note for add_note action');
                }
                $noteVars = array(
                    'note' => new TextThreadEntryBody($args['note']),
                    'staffId' => $this->staff->getId(),
                    'poster' => $this->staff
                );
                $note = $task->postNote($noteVars, $errors);
                $result = !!$note;
                break;

            default:
                throw new McpException(-32602, "Invalid action: {$args['action']}");
        }

        $thisstaff = $oldStaff;

        if (!$result) {
            $errorMsg = is_array($errors) ? implode(', ', array_filter($errors)) : 'Operation failed';
            throw new McpException(-32602, "Failed to update task: {$errorMsg}");
        }

        return array(
            'success' => true,
            'task' => $this->formatTaskSummary($task)
        );
    }

    // =========================================================================
    // Resource Implementations
    // =========================================================================

    /**
     * Departments resource
     */
    private function resource_departments() {
        $depts = array();
        foreach (Dept::objects()->filter(array('flags__hasbit' => Dept::FLAG_ACTIVE)) as $dept) {
            $depts[] = array(
                'id' => $dept->getId(),
                'name' => $dept->getName(),
                'parent_id' => $dept->pid,
                'manager_id' => $dept->manager_id,
                'signature' => $dept->getSignature()
            );
        }
        return array('departments' => $depts);
    }

    /**
     * Help topics resource
     */
    private function resource_help_topics() {
        $topics = array();
        foreach (Topic::objects()->filter(array('isactive' => 1)) as $topic) {
            $topics[] = array(
                'id' => $topic->getId(),
                'name' => $topic->getName(),
                'parent_id' => $topic->topic_pid,
                'dept_id' => $topic->dept_id,
                'priority_id' => $topic->priority_id,
                'sla_id' => $topic->sla_id
            );
        }
        return array('help_topics' => $topics);
    }

    /**
     * Staff resource
     */
    private function resource_staff() {
        $staff = array();
        foreach (Staff::objects()->filter(array('isactive' => 1)) as $s) {
            $staff[] = array(
                'id' => $s->getId(),
                'username' => $s->getUserName(),
                'name' => (string) $s->getName(),
                'email' => $s->getEmail(),
                'dept_id' => $s->getDeptId(),
                'role_id' => $s->role_id,
                'isadmin' => (bool) $s->isAdmin()
            );
        }
        return array('staff' => $staff);
    }

    /**
     * Teams resource
     */
    private function resource_teams() {
        $teams = array();
        foreach (Team::objects()->filter(array('flags__hasbit' => Team::FLAG_ENABLED)) as $team) {
            $teams[] = array(
                'id' => $team->getId(),
                'name' => $team->getName(),
                'lead_id' => $team->lead_id
            );
        }
        return array('teams' => $teams);
    }

    /**
     * Statuses resource
     */
    private function resource_statuses() {
        $statuses = array();
        foreach (TicketStatus::objects() as $status) {
            $statuses[] = array(
                'id' => $status->getId(),
                'name' => $status->getName(),
                'state' => $status->getState()
            );
        }
        return array('statuses' => $statuses);
    }

    /**
     * Priorities resource
     */
    private function resource_priorities() {
        $priorities = array();
        foreach (Priority::objects() as $priority) {
            $priorities[] = array(
                'id' => $priority->getId(),
                'name' => $priority->getDesc(),
                'color' => $priority->getColor(),
                'urgency' => $priority->getUrgency()
            );
        }
        return array('priorities' => $priorities);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Resolve ticket from args (by ID or number)
     */
    private function resolveTicket($args) {
        $ticket = null;

        if (!empty($args['ticket_id'])) {
            $ticket = Ticket::lookup(intval($args['ticket_id']));
        } elseif (!empty($args['ticket_number'])) {
            $ticket = Ticket::lookupByNumber($args['ticket_number']);
        }

        if (!$ticket) {
            throw new McpException(-32602, 'Ticket not found');
        }

        return $ticket;
    }

    /**
     * Apply visibility constraints to ticket query based on staff permissions
     */
    private function applyTicketVisibility($tickets) {
        // If staff has department visibility permission, show all tickets
        // Otherwise, filter by accessible departments
        if (!$this->staff->hasPerm(Dept::PERM_DEPT)) {
            $depts = $this->staff->getDepts();
            if ($depts) {
                $tickets->filter(array('dept_id__in' => $depts));
            }
        }
        return $tickets;
    }

    /**
     * Check if staff can access a specific ticket
     */
    private function canAccessTicket($ticket) {
        // Admin or has department visibility permission can see all
        if ($this->staff->isAdmin() || $this->staff->hasPerm(Dept::PERM_DEPT)) {
            return true;
        }

        // Check if ticket is in accessible department
        $depts = $this->staff->getDepts();
        if ($depts && in_array($ticket->getDeptId(), $depts)) {
            return true;
        }

        // Check if staff is assigned to the ticket
        if ($ticket->getStaffId() == $this->staff->getId()) {
            return true;
        }

        // Check team assignment
        $staffTeams = array_map(function($t) { return $t->team_id; }, $this->staff->teams->all());
        if ($ticket->getTeamId() && in_array($ticket->getTeamId(), $staffTeams)) {
            return true;
        }

        return false;
    }

    /**
     * Format ticket for summary list
     */
    private function formatTicketSummary($ticket) {
        return array(
            'id' => $ticket->getId(),
            'number' => $ticket->getNumber(),
            'subject' => $ticket->getSubject(),
            'status' => array(
                'id' => $ticket->getStatusId(),
                'name' => $ticket->getStatus() ? $ticket->getStatus()->getName() : null,
                'state' => $ticket->getStatus() ? $ticket->getStatus()->getState() : null
            ),
            'department' => array(
                'id' => $ticket->getDeptId(),
                'name' => $ticket->getDept() ? $ticket->getDept()->getName() : null
            ),
            'user' => array(
                'name' => $ticket->getOwner() ? (string) $ticket->getOwner()->getName() : null,
                'email' => $ticket->getOwner() ? $ticket->getOwner()->getEmail() : null
            ),
            'assignee' => array(
                'staff' => $ticket->getStaff() ? array(
                    'id' => $ticket->getStaffId(),
                    'name' => (string) $ticket->getStaff()->getName()
                ) : null,
                'team' => $ticket->getTeam() ? array(
                    'id' => $ticket->getTeamId(),
                    'name' => $ticket->getTeam()->getName()
                ) : null
            ),
            'created' => $ticket->getCreateDate(),
            'updated' => $ticket->getEffectiveDate()
        );
    }

    /**
     * Format ticket with full details
     */
    private function formatTicketFull($ticket, $includeThread = true) {
        $data = $this->formatTicketSummary($ticket);

        // Add additional details
        $data['topic'] = $ticket->getTopic() ? array(
            'id' => $ticket->getTopicId(),
            'name' => $ticket->getTopic()->getName()
        ) : null;

        $data['sla'] = $ticket->getSLA() ? array(
            'id' => $ticket->getSLAId(),
            'name' => $ticket->getSLA()->getName()
        ) : null;

        $data['priority'] = $ticket->getPriority() ? array(
            'id' => $ticket->getPriorityId(),
            'name' => $ticket->getPriority()->getDesc()
        ) : null;

        $data['duedate'] = $ticket->getDueDate();
        $data['closed'] = $ticket->isClosed() ? $ticket->getCloseDate() : null;
        $data['isoverdue'] = $ticket->isOverdue();
        $data['isanswered'] = $ticket->isAnswered();

        // Thread entries
        if ($includeThread && $ticket->getThread()) {
            $data['thread'] = array();
            foreach ($ticket->getThread()->getEntries() as $entry) {
                $data['thread'][] = $this->formatThreadEntry($entry);
            }
        }

        // Collaborators
        $data['collaborators'] = array();
        if ($ticket->getThread() && ($collabs = $ticket->getThread()->getCollaborators())) {
            foreach ($collabs as $collab) {
                $data['collaborators'][] = array(
                    'id' => $collab->getId(),
                    'name' => (string) $collab->getName(),
                    'email' => $collab->getEmail(),
                    'isactive' => $collab->isActive()
                );
            }
        }

        return $data;
    }

    /**
     * Format thread entry
     */
    private function formatThreadEntry($entry) {
        $typeMap = array('M' => 'message', 'R' => 'response', 'N' => 'note');

        return array(
            'id' => $entry->getId(),
            'type' => $typeMap[$entry->type] ?? $entry->type,
            'poster' => $entry->poster,
            'body' => $entry->getBody() ? $entry->getBody()->getClean() : null,
            'created' => $entry->created,
            'staff_id' => $entry->staff_id,
            'user_id' => $entry->user_id
        );
    }

    /**
     * Format task for summary
     */
    private function formatTaskSummary($task) {
        return array(
            'id' => $task->getId(),
            'number' => $task->getNumber(),
            'title' => $task->getTitle(),
            'status' => $task->isOpen() ? 'open' : 'closed',
            'department' => array(
                'id' => $task->getDeptId(),
                'name' => $task->getDept() ? $task->getDept()->getName() : null
            ),
            'assignee' => array(
                'staff' => $task->getStaff() ? array(
                    'id' => $task->getStaffId(),
                    'name' => (string) $task->getStaff()->getName()
                ) : null,
                'team' => $task->getTeam() ? array(
                    'id' => $task->getTeamId(),
                    'name' => $task->getTeam()->getName()
                ) : null
            ),
            'ticket_id' => $task->object_id ?? null,
            'created' => $task->getCreateDate(),
            'duedate' => $task->getDueDate(),
            'closed' => $task->getCloseDate()
        );
    }
}

/**
 * MCP Exception for error handling
 */
class McpException extends Exception {
    private $jsonRpcCode;

    public function __construct($code, $message) {
        parent::__construct($message);
        $this->jsonRpcCode = $code;
    }

    public function getJsonRpcCode() {
        return $this->jsonRpcCode;
    }
}
?>
