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
            'transfer_ticket' => array(
                'name' => 'transfer_ticket',
                'description' => 'Transfer a ticket to a different department',
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
                        'dept_id' => array(
                            'type' => 'integer',
                            'description' => 'Target department ID'
                        ),
                        'comments' => array(
                            'type' => 'string',
                            'description' => 'Optional reason for the transfer (will be posted as internal note)'
                        ),
                        'alert' => array(
                            'type' => 'boolean',
                            'description' => 'Send alert to new department members',
                            'default' => true
                        )
                    ),
                    'required' => array('dept_id')
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
            ),
            // Knowledge Base (FAQ) Tools
            'search_faqs' => array(
                'name' => 'search_faqs',
                'description' => 'Search FAQ articles with filters for category, visibility, and text search',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'q' => array(
                            'type' => 'string',
                            'description' => 'Full-text search in question/answer/keywords'
                        ),
                        'category_id' => array(
                            'type' => 'integer',
                            'description' => 'Filter by category ID'
                        ),
                        'visibility' => array(
                            'type' => 'string',
                            'description' => 'Filter by visibility (internal, public, featured)',
                            'enum' => array('internal', 'public', 'featured')
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
                        )
                    )
                )
            ),
            'get_faq' => array(
                'name' => 'get_faq',
                'description' => 'Get full details of an FAQ article including question, answer, keywords, notes, and associated help topics',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'faq_id' => array(
                            'type' => 'integer',
                            'description' => 'FAQ ID'
                        )
                    ),
                    'required' => array('faq_id')
                )
            ),
            'create_faq' => array(
                'name' => 'create_faq',
                'description' => 'Create a new FAQ article',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'question' => array(
                            'type' => 'string',
                            'description' => 'FAQ question'
                        ),
                        'answer' => array(
                            'type' => 'string',
                            'description' => 'FAQ answer (supports HTML)'
                        ),
                        'category_id' => array(
                            'type' => 'integer',
                            'description' => 'Category ID'
                        ),
                        'visibility' => array(
                            'type' => 'string',
                            'description' => 'Visibility (internal, public, featured)',
                            'enum' => array('internal', 'public', 'featured'),
                            'default' => 'internal'
                        ),
                        'keywords' => array(
                            'type' => 'string',
                            'description' => 'Search keywords'
                        ),
                        'notes' => array(
                            'type' => 'string',
                            'description' => 'Internal notes'
                        ),
                        'topic_ids' => array(
                            'type' => 'array',
                            'items' => array('type' => 'integer'),
                            'description' => 'Associated help topic IDs'
                        )
                    ),
                    'required' => array('question', 'answer', 'category_id')
                )
            ),
            'update_faq' => array(
                'name' => 'update_faq',
                'description' => 'Update an existing FAQ article',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'faq_id' => array(
                            'type' => 'integer',
                            'description' => 'FAQ ID'
                        ),
                        'question' => array(
                            'type' => 'string',
                            'description' => 'FAQ question'
                        ),
                        'answer' => array(
                            'type' => 'string',
                            'description' => 'FAQ answer (supports HTML)'
                        ),
                        'category_id' => array(
                            'type' => 'integer',
                            'description' => 'Category ID'
                        ),
                        'visibility' => array(
                            'type' => 'string',
                            'description' => 'Visibility (internal, public, featured)',
                            'enum' => array('internal', 'public', 'featured')
                        ),
                        'keywords' => array(
                            'type' => 'string',
                            'description' => 'Search keywords'
                        ),
                        'notes' => array(
                            'type' => 'string',
                            'description' => 'Internal notes'
                        ),
                        'topic_ids' => array(
                            'type' => 'array',
                            'items' => array('type' => 'integer'),
                            'description' => 'Associated help topic IDs'
                        )
                    ),
                    'required' => array('faq_id')
                )
            ),
            'delete_faq' => array(
                'name' => 'delete_faq',
                'description' => 'Delete an FAQ article',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'faq_id' => array(
                            'type' => 'integer',
                            'description' => 'FAQ ID'
                        )
                    ),
                    'required' => array('faq_id')
                )
            ),
            // FAQ Category Tools
            'search_faq_categories' => array(
                'name' => 'search_faq_categories',
                'description' => 'Search FAQ categories',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'q' => array(
                            'type' => 'string',
                            'description' => 'Search by category name'
                        ),
                        'visibility' => array(
                            'type' => 'string',
                            'description' => 'Filter by visibility (private, public, featured)',
                            'enum' => array('private', 'public', 'featured')
                        ),
                        'parent_id' => array(
                            'type' => 'integer',
                            'description' => 'Filter by parent category ID (0 for root categories)'
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
                        )
                    )
                )
            ),
            'get_faq_category' => array(
                'name' => 'get_faq_category',
                'description' => 'Get full details of an FAQ category',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'category_id' => array(
                            'type' => 'integer',
                            'description' => 'Category ID'
                        )
                    ),
                    'required' => array('category_id')
                )
            ),
            'create_faq_category' => array(
                'name' => 'create_faq_category',
                'description' => 'Create a new FAQ category',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'name' => array(
                            'type' => 'string',
                            'description' => 'Category name (min 3 characters)'
                        ),
                        'description' => array(
                            'type' => 'string',
                            'description' => 'Category description'
                        ),
                        'visibility' => array(
                            'type' => 'string',
                            'description' => 'Visibility (private, public, featured)',
                            'enum' => array('private', 'public', 'featured'),
                            'default' => 'private'
                        ),
                        'parent_id' => array(
                            'type' => 'integer',
                            'description' => 'Parent category ID (optional, for subcategories)'
                        ),
                        'notes' => array(
                            'type' => 'string',
                            'description' => 'Internal notes'
                        )
                    ),
                    'required' => array('name', 'description')
                )
            ),
            'update_faq_category' => array(
                'name' => 'update_faq_category',
                'description' => 'Update an existing FAQ category',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'category_id' => array(
                            'type' => 'integer',
                            'description' => 'Category ID'
                        ),
                        'name' => array(
                            'type' => 'string',
                            'description' => 'Category name (min 3 characters)'
                        ),
                        'description' => array(
                            'type' => 'string',
                            'description' => 'Category description'
                        ),
                        'visibility' => array(
                            'type' => 'string',
                            'description' => 'Visibility (private, public, featured)',
                            'enum' => array('private', 'public', 'featured')
                        ),
                        'parent_id' => array(
                            'type' => 'integer',
                            'description' => 'Parent category ID (0 to make root category)'
                        ),
                        'notes' => array(
                            'type' => 'string',
                            'description' => 'Internal notes'
                        )
                    ),
                    'required' => array('category_id')
                )
            ),
            'delete_faq_category' => array(
                'name' => 'delete_faq_category',
                'description' => 'Delete an FAQ category (must have no FAQs)',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'category_id' => array(
                            'type' => 'integer',
                            'description' => 'Category ID'
                        )
                    ),
                    'required' => array('category_id')
                )
            ),
            // Email Ban List Tools
            'list_banned_emails' => array(
                'name' => 'list_banned_emails',
                'description' => 'List all banned email addresses (admin only)',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'page' => array(
                            'type' => 'integer',
                            'description' => 'Page number for pagination (default: 1)',
                            'default' => 1
                        ),
                        'limit' => array(
                            'type' => 'integer',
                            'description' => 'Results per page (default: 25, max: 100)',
                            'default' => 25
                        )
                    )
                )
            ),
            'add_banned_email' => array(
                'name' => 'add_banned_email',
                'description' => 'Add an email address to the ban list (admin only)',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'email' => array(
                            'type' => 'string',
                            'description' => 'Email address to ban'
                        )
                    ),
                    'required' => array('email')
                )
            ),
            'remove_banned_email' => array(
                'name' => 'remove_banned_email',
                'description' => 'Remove an email address from the ban list (admin only)',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'email' => array(
                            'type' => 'string',
                            'description' => 'Email address to unban'
                        )
                    ),
                    'required' => array('email')
                )
            ),
            'check_banned_email' => array(
                'name' => 'check_banned_email',
                'description' => 'Check if an email address is banned',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'email' => array(
                            'type' => 'string',
                            'description' => 'Email address to check'
                        )
                    ),
                    'required' => array('email')
                )
            ),
            // Organization Tools
            'search_organizations' => array(
                'name' => 'search_organizations',
                'description' => 'Search organizations with filters',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'q' => array(
                            'type' => 'string',
                            'description' => 'Search by organization name'
                        ),
                        'domain' => array(
                            'type' => 'string',
                            'description' => 'Filter by email domain'
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
                        )
                    )
                )
            ),
            'get_organization' => array(
                'name' => 'get_organization',
                'description' => 'Get full details of an organization including members and settings',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'org_id' => array(
                            'type' => 'integer',
                            'description' => 'Organization ID'
                        ),
                        'include_members' => array(
                            'type' => 'boolean',
                            'description' => 'Include list of organization members',
                            'default' => true
                        )
                    ),
                    'required' => array('org_id')
                )
            ),
            'create_organization' => array(
                'name' => 'create_organization',
                'description' => 'Create a new organization',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'name' => array(
                            'type' => 'string',
                            'description' => 'Organization name'
                        ),
                        'domain' => array(
                            'type' => 'string',
                            'description' => 'Email domain(s) for auto-assignment (comma-separated)'
                        ),
                        'manager' => array(
                            'type' => 'string',
                            'description' => 'Account manager: "s{staff_id}" for staff or "t{team_id}" for team'
                        ),
                        'auto_add_members_as_collabs' => array(
                            'type' => 'boolean',
                            'description' => 'Auto-add organization members as ticket collaborators',
                            'default' => false
                        ),
                        'auto_add_primary_contacts_as_collabs' => array(
                            'type' => 'boolean',
                            'description' => 'Auto-add primary contacts as ticket collaborators',
                            'default' => false
                        ),
                        'auto_assign_account_manager' => array(
                            'type' => 'boolean',
                            'description' => 'Auto-assign tickets to account manager',
                            'default' => false
                        ),
                        'sharing' => array(
                            'type' => 'string',
                            'description' => 'Ticket sharing: "none", "primary_contacts", or "all_members"',
                            'enum' => array('none', 'primary_contacts', 'all_members'),
                            'default' => 'primary_contacts'
                        )
                    ),
                    'required' => array('name')
                )
            ),
            'update_organization' => array(
                'name' => 'update_organization',
                'description' => 'Update an existing organization',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'org_id' => array(
                            'type' => 'integer',
                            'description' => 'Organization ID'
                        ),
                        'name' => array(
                            'type' => 'string',
                            'description' => 'Organization name'
                        ),
                        'domain' => array(
                            'type' => 'string',
                            'description' => 'Email domain(s) for auto-assignment (comma-separated)'
                        ),
                        'manager' => array(
                            'type' => 'string',
                            'description' => 'Account manager: "s{staff_id}" for staff or "t{team_id}" for team, empty to clear'
                        ),
                        'auto_add_members_as_collabs' => array(
                            'type' => 'boolean',
                            'description' => 'Auto-add organization members as ticket collaborators'
                        ),
                        'auto_add_primary_contacts_as_collabs' => array(
                            'type' => 'boolean',
                            'description' => 'Auto-add primary contacts as ticket collaborators'
                        ),
                        'auto_assign_account_manager' => array(
                            'type' => 'boolean',
                            'description' => 'Auto-assign tickets to account manager'
                        ),
                        'sharing' => array(
                            'type' => 'string',
                            'description' => 'Ticket sharing: "none", "primary_contacts", or "all_members"',
                            'enum' => array('none', 'primary_contacts', 'all_members')
                        )
                    ),
                    'required' => array('org_id')
                )
            ),
            'add_user_to_organization' => array(
                'name' => 'add_user_to_organization',
                'description' => 'Add an existing user to an organization',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'user_id' => array(
                            'type' => 'integer',
                            'description' => 'User ID'
                        ),
                        'user_email' => array(
                            'type' => 'string',
                            'description' => 'User email (alternative to user_id)'
                        ),
                        'org_id' => array(
                            'type' => 'integer',
                            'description' => 'Organization ID'
                        ),
                        'primary_contact' => array(
                            'type' => 'boolean',
                            'description' => 'Set user as primary contact for the organization',
                            'default' => false
                        )
                    ),
                    'required' => array('org_id')
                )
            ),
            'remove_user_from_organization' => array(
                'name' => 'remove_user_from_organization',
                'description' => 'Remove a user from their organization',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'user_id' => array(
                            'type' => 'integer',
                            'description' => 'User ID'
                        ),
                        'user_email' => array(
                            'type' => 'string',
                            'description' => 'User email (alternative to user_id)'
                        )
                    )
                )
            ),
            // Ticket Status Tools
            'get_ticket_statuses' => array(
                'name' => 'get_ticket_statuses',
                'description' => 'Get available ticket statuses with optional filtering by state',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'state' => array(
                            'type' => 'string',
                            'description' => 'Filter by state (open, closed, archived, deleted)',
                            'enum' => array('open', 'closed', 'archived', 'deleted')
                        ),
                        'enabled_only' => array(
                            'type' => 'boolean',
                            'description' => 'Only return enabled statuses (default: true)',
                            'default' => true
                        )
                    )
                )
            ),
            // Current User Tool
            'get_current_user' => array(
                'name' => 'get_current_user',
                'description' => 'Get information about the currently authenticated staff member, their role, and permissions',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array()
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
            ),
            'osticket://faq-categories' => array(
                'uri' => 'osticket://faq-categories',
                'name' => 'FAQ Categories',
                'description' => 'List of all FAQ categories',
                'mimeType' => 'application/json'
            ),
            'osticket://organizations' => array(
                'uri' => 'osticket://organizations',
                'name' => 'Organizations',
                'description' => 'List of all organizations',
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
        // Check permission - admins can always create tickets
        if (!$this->staff->isAdmin() && !$this->staff->hasPerm(Ticket::PERM_CREATE)) {
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

        // Check permission - admins can always reply
        $role = $ticket->getRole($this->staff);
        if (!$this->staff->isAdmin() && (!$role || !$role->hasPerm(Ticket::PERM_REPLY))) {
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

        // Check permission based on status state - admins can always change status
        $role = $ticket->getRole($this->staff);
        if (!$this->staff->isAdmin() && !$role) {
            throw new McpException(-32602, 'Permission denied: No role for this ticket');
        }

        if (!$this->staff->isAdmin() && $status->getState() === 'closed' && (!$role || !$role->hasPerm(Ticket::PERM_CLOSE))) {
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

        // Check permission - admins can always assign
        $role = $ticket->getRole($this->staff);
        if (!$this->staff->isAdmin() && (!$role || !$role->hasPerm(Ticket::PERM_ASSIGN))) {
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
     * Transfer ticket to different department
     */
    private function tool_transfer_ticket($args) {
        $ticket = $this->resolveTicket($args);

        // Check permission - admins can always transfer
        $role = $ticket->getRole($this->staff);
        if (!$this->staff->isAdmin() && (!$role || !$role->hasPerm(Ticket::PERM_TRANSFER))) {
            throw new McpException(-32602, 'Permission denied: Cannot transfer this ticket');
        }

        if (empty($args['dept_id'])) {
            throw new McpException(-32602, 'Missing required field: dept_id');
        }

        $deptId = intval($args['dept_id']);
        $dept = Dept::lookup($deptId);
        if (!$dept) {
            throw new McpException(-32602, 'Invalid dept_id: Department not found');
        }

        // Check if already in this department
        if ($ticket->getDeptId() == $deptId) {
            throw new McpException(-32602, 'Ticket is already in the specified department');
        }

        $currentDept = $ticket->getDept();
        $alert = $args['alert'] ?? true;

        // Temporarily set global staff for logging
        global $thisstaff;
        $oldStaff = $thisstaff;
        $thisstaff = $this->staff;

        // Update department
        $ticket->dept_id = $deptId;

        // If ticket is assigned to staff who is not member of new department
        // and department requires members-only assignment, unassign
        if ($ticket->isAssigned() && ($staff = $ticket->getStaff())) {
            if ($dept->assignMembersOnly() && !$dept->isMember($staff)) {
                $ticket->staff_id = 0;
            }
        }

        // Recalculate SLA based on new department
        $ticket->selectSLAId();

        $result = $ticket->save();

        if (!$result) {
            $thisstaff = $oldStaff;
            throw new McpException(-32602, 'Failed to transfer ticket');
        }

        // Log transfer event
        $ticket->logEvent('transferred', array('dept' => $dept->getName()));

        // Post internal note if comments provided
        if (!empty($args['comments'])) {
            $title = sprintf('%s transferred from %s to %s',
                'Ticket',
                $currentDept ? $currentDept->getName() : 'Unknown',
                $dept->getName()
            );

            $noteErrors = array();
            $ticket->postNote(
                array('note' => $args['comments'], 'title' => $title),
                $noteErrors,
                $thisstaff,
                false
            );
        }

        $thisstaff = $oldStaff;

        return array(
            'success' => true,
            'ticket_number' => $ticket->getNumber(),
            'department' => array(
                'id' => $dept->getId(),
                'name' => $dept->getName()
            ),
            'previous_department' => $currentDept ? array(
                'id' => $currentDept->getId(),
                'name' => $currentDept->getName()
            ) : null
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
        // Check permission - admins can always create tasks
        if (!$this->staff->isAdmin() && !$this->staff->hasPerm(TaskModel::PERM_CREATE)) {
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
                if (!$this->staff->isAdmin() && !$this->staff->hasPerm(TaskModel::PERM_CLOSE)) {
                    throw new McpException(-32602, 'Permission denied: Cannot close tasks');
                }
                $result = $task->setStatus('closed', '', $errors);
                break;

            case 'reopen':
                if (!$this->staff->isAdmin() && !$this->staff->hasPerm(TaskModel::PERM_CLOSE)) {
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

    /**
     * Search FAQs tool
     */
    private function tool_search_faqs($args) {
        $page = max(1, intval($args['page'] ?? 1));
        $limit = min(100, max(1, intval($args['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        $faqs = FAQ::objects();

        // Apply filters
        if (!empty($args['category_id'])) {
            $faqs->filter(array('category_id' => intval($args['category_id'])));
        }

        if (!empty($args['visibility'])) {
            $visibilityMap = array(
                'internal' => FAQ::VISIBILITY_PRIVATE,
                'public' => FAQ::VISIBILITY_PUBLIC,
                'featured' => FAQ::VISIBILITY_FEATURED
            );
            if (isset($visibilityMap[$args['visibility']])) {
                $faqs->filter(array('ispublished' => $visibilityMap[$args['visibility']]));
            }
        }

        // Full-text search
        if (!empty($args['q'])) {
            $q = $args['q'];
            $faqs->filter(Q::any(array(
                'question__contains' => $q,
                'answer__contains' => $q,
                'keywords__contains' => $q
            )));
        }

        $faqs->order_by('question');

        $total = $faqs->count();
        $faqs->limit($limit)->offset($offset);

        $results = array();
        foreach ($faqs as $faq) {
            $results[] = $this->formatFaqSummary($faq);
        }

        return array(
            'faqs' => $results,
            'pagination' => array(
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            )
        );
    }

    /**
     * Get FAQ details tool
     */
    private function tool_get_faq($args) {
        if (empty($args['faq_id'])) {
            throw new McpException(-32602, 'Missing required field: faq_id');
        }

        $faq = FAQ::lookup(intval($args['faq_id']));
        if (!$faq) {
            throw new McpException(-32602, 'FAQ not found');
        }

        return $this->formatFaqFull($faq);
    }

    /**
     * Create FAQ tool
     */
    private function tool_create_faq($args) {
        // Check permission - admins can always manage FAQs
        if (!$this->staff->isAdmin() && !$this->staff->hasPerm(FAQ::PERM_MANAGE)) {
            throw new McpException(-32602, 'Permission denied: Cannot manage FAQs');
        }

        // Validate required fields
        foreach (array('question', 'answer', 'category_id') as $field) {
            if (empty($args[$field])) {
                throw new McpException(-32602, "Missing required field: {$field}");
            }
        }

        // Validate category exists
        $category = Category::lookup(intval($args['category_id']));
        if (!$category) {
            throw new McpException(-32602, 'Invalid category_id');
        }

        // Map visibility
        $visibilityMap = array(
            'internal' => FAQ::VISIBILITY_PRIVATE,
            'public' => FAQ::VISIBILITY_PUBLIC,
            'featured' => FAQ::VISIBILITY_FEATURED
        );
        $visibility = $visibilityMap[$args['visibility'] ?? 'internal'] ?? FAQ::VISIBILITY_PRIVATE;

        // Create FAQ
        $faq = FAQ::create();
        $vars = array(
            'question' => $args['question'],
            'answer' => $args['answer'],
            'category_id' => intval($args['category_id']),
            'ispublished' => $visibility,
            'keywords' => $args['keywords'] ?? '',
            'notes' => $args['notes'] ?? '',
            'topics' => $args['topic_ids'] ?? array()
        );

        $errors = array();
        if (!$faq->update($vars, $errors)) {
            $errorMsg = is_array($errors) ? implode(', ', array_filter($errors)) : 'Unknown error';
            throw new McpException(-32602, "Failed to create FAQ: {$errorMsg}");
        }

        return array(
            'success' => true,
            'faq' => $this->formatFaqSummary($faq)
        );
    }

    /**
     * Update FAQ tool
     */
    private function tool_update_faq($args) {
        // Check permission - admins can always manage FAQs
        if (!$this->staff->isAdmin() && !$this->staff->hasPerm(FAQ::PERM_MANAGE)) {
            throw new McpException(-32602, 'Permission denied: Cannot manage FAQs');
        }

        if (empty($args['faq_id'])) {
            throw new McpException(-32602, 'Missing required field: faq_id');
        }

        $faq = FAQ::lookup(intval($args['faq_id']));
        if (!$faq) {
            throw new McpException(-32602, 'FAQ not found');
        }

        // Build update vars - only include provided fields
        $vars = array(
            'id' => $faq->getId(),
            'question' => $args['question'] ?? $faq->getQuestion(),
            'answer' => $args['answer'] ?? $faq->getAnswer(),
            'category_id' => isset($args['category_id']) ? intval($args['category_id']) : $faq->getCategoryId(),
            'keywords' => $args['keywords'] ?? $faq->getKeywords(),
            'notes' => $args['notes'] ?? $faq->getNotes()
        );

        // Handle visibility
        if (isset($args['visibility'])) {
            $visibilityMap = array(
                'internal' => FAQ::VISIBILITY_PRIVATE,
                'public' => FAQ::VISIBILITY_PUBLIC,
                'featured' => FAQ::VISIBILITY_FEATURED
            );
            $vars['ispublished'] = $visibilityMap[$args['visibility']] ?? $faq->ispublished;
        } else {
            $vars['ispublished'] = $faq->ispublished;
        }

        // Handle topics
        if (isset($args['topic_ids'])) {
            $vars['topics'] = $args['topic_ids'];
        } else {
            $vars['topics'] = $faq->getHelpTopicsIds();
        }

        // Validate category if changed
        if (isset($args['category_id'])) {
            $category = Category::lookup(intval($args['category_id']));
            if (!$category) {
                throw new McpException(-32602, 'Invalid category_id');
            }
        }

        $errors = array();
        if (!$faq->update($vars, $errors)) {
            $errorMsg = is_array($errors) ? implode(', ', array_filter($errors)) : 'Unknown error';
            throw new McpException(-32602, "Failed to update FAQ: {$errorMsg}");
        }

        return array(
            'success' => true,
            'faq' => $this->formatFaqSummary($faq)
        );
    }

    /**
     * Delete FAQ tool
     */
    private function tool_delete_faq($args) {
        // Check permission - admins can always manage FAQs
        if (!$this->staff->isAdmin() && !$this->staff->hasPerm(FAQ::PERM_MANAGE)) {
            throw new McpException(-32602, 'Permission denied: Cannot manage FAQs');
        }

        if (empty($args['faq_id'])) {
            throw new McpException(-32602, 'Missing required field: faq_id');
        }

        $faq = FAQ::lookup(intval($args['faq_id']));
        if (!$faq) {
            throw new McpException(-32602, 'FAQ not found');
        }

        $faqId = $faq->getId();
        $question = $faq->getQuestion();

        if (!$faq->delete()) {
            throw new McpException(-32602, 'Failed to delete FAQ');
        }

        return array(
            'success' => true,
            'deleted' => array(
                'id' => $faqId,
                'question' => $question
            )
        );
    }

    /**
     * Search FAQ categories tool
     */
    private function tool_search_faq_categories($args) {
        $page = max(1, intval($args['page'] ?? 1));
        $limit = min(100, max(1, intval($args['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        $categories = Category::objects();

        // Apply filters
        if (!empty($args['q'])) {
            $categories->filter(array('name__contains' => $args['q']));
        }

        if (!empty($args['visibility'])) {
            $visibilityMap = array(
                'private' => Category::VISIBILITY_PRIVATE,
                'public' => Category::VISIBILITY_PUBLIC,
                'featured' => Category::VISIBILITY_FEATURED
            );
            if (isset($visibilityMap[$args['visibility']])) {
                $categories->filter(array('ispublic' => $visibilityMap[$args['visibility']]));
            }
        }

        if (isset($args['parent_id'])) {
            $parentId = intval($args['parent_id']);
            if ($parentId === 0) {
                $categories->filter(Q::any(array(
                    'category_pid' => 0,
                    'category_pid__isnull' => true
                )));
            } else {
                $categories->filter(array('category_pid' => $parentId));
            }
        }

        $categories->order_by('name');

        $total = $categories->count();
        $categories->limit($limit)->offset($offset);

        $results = array();
        foreach ($categories as $cat) {
            $results[] = $this->formatCategorySummary($cat);
        }

        return array(
            'categories' => $results,
            'pagination' => array(
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            )
        );
    }

    /**
     * Get FAQ category details tool
     */
    private function tool_get_faq_category($args) {
        if (empty($args['category_id'])) {
            throw new McpException(-32602, 'Missing required field: category_id');
        }

        $category = Category::lookup(intval($args['category_id']));
        if (!$category) {
            throw new McpException(-32602, 'Category not found');
        }

        return $this->formatCategoryFull($category);
    }

    /**
     * Create FAQ category tool
     */
    private function tool_create_faq_category($args) {
        // Check permission - admins can always manage FAQs
        if (!$this->staff->isAdmin() && !$this->staff->hasPerm(FAQ::PERM_MANAGE)) {
            throw new McpException(-32602, 'Permission denied: Cannot manage FAQs');
        }

        // Validate required fields
        if (empty($args['name'])) {
            throw new McpException(-32602, 'Missing required field: name');
        }
        if (empty($args['description'])) {
            throw new McpException(-32602, 'Missing required field: description');
        }

        // Map visibility
        $visibilityMap = array(
            'private' => Category::VISIBILITY_PRIVATE,
            'public' => Category::VISIBILITY_PUBLIC,
            'featured' => Category::VISIBILITY_FEATURED
        );
        $visibility = $visibilityMap[$args['visibility'] ?? 'private'] ?? Category::VISIBILITY_PRIVATE;

        // Create category
        $category = Category::create();
        $vars = array(
            'name' => $args['name'],
            'description' => $args['description'],
            'ispublic' => $visibility,
            'pid' => $args['parent_id'] ?? 0,
            'notes' => $args['notes'] ?? ''
        );

        $errors = array();
        if (!$category->update($vars, $errors)) {
            $errorMsg = is_array($errors) ? implode(', ', array_filter($errors)) : 'Unknown error';
            throw new McpException(-32602, "Failed to create category: {$errorMsg}");
        }

        return array(
            'success' => true,
            'category' => $this->formatCategorySummary($category)
        );
    }

    /**
     * Update FAQ category tool
     */
    private function tool_update_faq_category($args) {
        // Check permission - admins can always manage FAQs
        if (!$this->staff->isAdmin() && !$this->staff->hasPerm(FAQ::PERM_MANAGE)) {
            throw new McpException(-32602, 'Permission denied: Cannot manage FAQs');
        }

        if (empty($args['category_id'])) {
            throw new McpException(-32602, 'Missing required field: category_id');
        }

        $category = Category::lookup(intval($args['category_id']));
        if (!$category) {
            throw new McpException(-32602, 'Category not found');
        }

        // Build update vars - only include provided fields
        $vars = array(
            'id' => $category->getId(),
            'name' => $args['name'] ?? $category->getName(),
            'description' => $args['description'] ?? $category->getDescription(),
            'notes' => $args['notes'] ?? $category->getNotes(),
            'pid' => isset($args['parent_id']) ? intval($args['parent_id']) : $category->category_pid
        );

        // Handle visibility
        if (isset($args['visibility'])) {
            $visibilityMap = array(
                'private' => Category::VISIBILITY_PRIVATE,
                'public' => Category::VISIBILITY_PUBLIC,
                'featured' => Category::VISIBILITY_FEATURED
            );
            $vars['ispublic'] = $visibilityMap[$args['visibility']] ?? $category->ispublic;
        } else {
            $vars['ispublic'] = $category->ispublic;
        }

        $errors = array();
        if (!$category->update($vars, $errors)) {
            $errorMsg = is_array($errors) ? implode(', ', array_filter($errors)) : 'Unknown error';
            throw new McpException(-32602, "Failed to update category: {$errorMsg}");
        }

        return array(
            'success' => true,
            'category' => $this->formatCategorySummary($category)
        );
    }

    /**
     * Delete FAQ category tool
     */
    private function tool_delete_faq_category($args) {
        // Check permission - admins can always manage FAQs
        if (!$this->staff->isAdmin() && !$this->staff->hasPerm(FAQ::PERM_MANAGE)) {
            throw new McpException(-32602, 'Permission denied: Cannot manage FAQs');
        }

        if (empty($args['category_id'])) {
            throw new McpException(-32602, 'Missing required field: category_id');
        }

        $category = Category::lookup(intval($args['category_id']));
        if (!$category) {
            throw new McpException(-32602, 'Category not found');
        }

        // Check if category has FAQs
        if ($category->getNumFAQs(true) > 0) {
            throw new McpException(-32602, 'Cannot delete category: it contains FAQs. Move or delete FAQs first.');
        }

        // Check if category has subcategories
        $subcategories = Category::objects()->filter(array('category_pid' => $category->getId()));
        if ($subcategories->count() > 0) {
            throw new McpException(-32602, 'Cannot delete category: it has subcategories. Delete subcategories first.');
        }

        $catId = $category->getId();
        $catName = $category->getName();

        if (!$category->delete()) {
            throw new McpException(-32602, 'Failed to delete category');
        }

        return array(
            'success' => true,
            'deleted' => array(
                'id' => $catId,
                'name' => $catName
            )
        );
    }

    /**
     * List banned emails tool
     */
    private function tool_list_banned_emails($args) {
        // Admin only
        if (!$this->staff->isAdmin()) {
            throw new McpException(-32602, 'Permission denied: Admin access required');
        }

        $page = max(1, intval($args['page'] ?? 1));
        $limit = min(100, max(1, intval($args['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        $filter = Banlist::getSystemBanList();
        if (!$filter) {
            return array(
                'banned_emails' => array(),
                'pagination' => array(
                    'page' => $page,
                    'limit' => $limit,
                    'total' => 0,
                    'pages' => 0
                )
            );
        }

        // Get rules where what='email'
        $rules = $filter->rules->filter(array('what' => 'email', 'isactive' => 1));
        $total = $rules->count();

        $rules->limit($limit)->offset($offset);

        $results = array();
        foreach ($rules as $rule) {
            $results[] = array(
                'id' => $rule->getId(),
                'email' => $rule->val,
                'how' => $rule->how
            );
        }

        return array(
            'banned_emails' => $results,
            'pagination' => array(
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            )
        );
    }

    /**
     * Add banned email tool
     */
    private function tool_add_banned_email($args) {
        // Admin only
        if (!$this->staff->isAdmin()) {
            throw new McpException(-32602, 'Permission denied: Admin access required');
        }

        if (empty($args['email'])) {
            throw new McpException(-32602, 'Missing required field: email');
        }

        $email = trim($args['email']);

        // Validate email format
        if (!Validator::is_email($email)) {
            throw new McpException(-32602, 'Invalid email address format');
        }

        // Check if already banned
        if (Banlist::includes($email)) {
            throw new McpException(-32602, 'Email address is already banned');
        }

        $result = Banlist::add($email);
        if (!$result) {
            throw new McpException(-32602, 'Failed to add email to ban list');
        }

        return array(
            'success' => true,
            'email' => $email,
            'message' => 'Email address has been banned'
        );
    }

    /**
     * Remove banned email tool
     */
    private function tool_remove_banned_email($args) {
        // Admin only
        if (!$this->staff->isAdmin()) {
            throw new McpException(-32602, 'Permission denied: Admin access required');
        }

        if (empty($args['email'])) {
            throw new McpException(-32602, 'Missing required field: email');
        }

        $email = trim($args['email']);

        // Check if actually banned
        if (!Banlist::includes($email)) {
            throw new McpException(-32602, 'Email address is not in the ban list');
        }

        $result = Banlist::remove($email);
        if (!$result) {
            throw new McpException(-32602, 'Failed to remove email from ban list');
        }

        return array(
            'success' => true,
            'email' => $email,
            'message' => 'Email address has been unbanned'
        );
    }

    /**
     * Check banned email tool
     */
    private function tool_check_banned_email($args) {
        if (empty($args['email'])) {
            throw new McpException(-32602, 'Missing required field: email');
        }

        $email = trim($args['email']);
        $banned = Banlist::includes($email);

        return array(
            'email' => $email,
            'banned' => (bool) $banned
        );
    }

    /**
     * Search organizations tool
     */
    private function tool_search_organizations($args) {
        $page = max(1, intval($args['page'] ?? 1));
        $limit = min(100, max(1, intval($args['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        $orgs = Organization::objects();

        // Apply filters
        if (!empty($args['q'])) {
            $orgs->filter(array('name__contains' => $args['q']));
        }

        if (!empty($args['domain'])) {
            $orgs->filter(array('domain__contains' => $args['domain']));
        }

        $orgs->order_by('name');

        $total = $orgs->count();
        $orgs->limit($limit)->offset($offset);

        $results = array();
        foreach ($orgs as $org) {
            $results[] = $this->formatOrganizationSummary($org);
        }

        return array(
            'organizations' => $results,
            'pagination' => array(
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            )
        );
    }

    /**
     * Get organization details tool
     */
    private function tool_get_organization($args) {
        if (empty($args['org_id'])) {
            throw new McpException(-32602, 'Missing required field: org_id');
        }

        $org = Organization::lookup(intval($args['org_id']));
        if (!$org) {
            throw new McpException(-32602, 'Organization not found');
        }

        $includeMembers = $args['include_members'] ?? true;
        return $this->formatOrganizationFull($org, $includeMembers);
    }

    /**
     * Create organization tool
     */
    private function tool_create_organization($args) {
        // Check permission - admins can always create organizations
        if (!$this->staff->isAdmin() && !$this->staff->hasPerm(OrganizationModel::PERM_CREATE)) {
            throw new McpException(-32602, 'Permission denied: Cannot create organizations');
        }

        if (empty($args['name'])) {
            throw new McpException(-32602, 'Missing required field: name');
        }

        // Check if organization name already exists
        if (Organization::lookup(array('name' => $args['name']))) {
            throw new McpException(-32602, 'Organization with this name already exists');
        }

        // Build status flags
        $status = Organization::SHARE_PRIMARY_CONTACT; // Default sharing

        if (!empty($args['auto_add_members_as_collabs'])) {
            $status |= Organization::COLLAB_ALL_MEMBERS;
        }
        if (!empty($args['auto_add_primary_contacts_as_collabs'])) {
            $status |= Organization::COLLAB_PRIMARY_CONTACT;
        }
        if (!empty($args['auto_assign_account_manager'])) {
            $status |= Organization::ASSIGN_AGENT_MANAGER;
        }

        // Handle sharing setting
        if (isset($args['sharing'])) {
            // Clear default sharing flags first
            $status &= ~(Organization::SHARE_PRIMARY_CONTACT | Organization::SHARE_EVERYBODY);
            switch ($args['sharing']) {
                case 'primary_contacts':
                    $status |= Organization::SHARE_PRIMARY_CONTACT;
                    break;
                case 'all_members':
                    $status |= Organization::SHARE_EVERYBODY;
                    break;
                // 'none' leaves both flags cleared
            }
        }

        // Validate manager if provided
        if (!empty($args['manager'])) {
            $manager = $args['manager'];
            if ($manager[0] === 's') {
                if (!Staff::lookup(substr($manager, 1))) {
                    throw new McpException(-32602, 'Invalid staff ID in manager field');
                }
            } elseif ($manager[0] === 't') {
                if (!Team::lookup(substr($manager, 1))) {
                    throw new McpException(-32602, 'Invalid team ID in manager field');
                }
            } else {
                throw new McpException(-32602, 'Manager must be "s{staff_id}" or "t{team_id}"');
            }
        }

        $org = Organization::create(array(
            'name' => $args['name'],
            'domain' => $args['domain'] ?? '',
            'manager' => $args['manager'] ?? '',
            'status' => $status
        ));

        if (!$org->save(true)) {
            throw new McpException(-32602, 'Failed to create organization');
        }

        // Add dynamic data
        $org->addDynamicData(array('name' => $args['name']));

        return array(
            'success' => true,
            'organization' => $this->formatOrganizationSummary($org)
        );
    }

    /**
     * Update organization tool
     */
    private function tool_update_organization($args) {
        // Check permission - admins can always edit organizations
        if (!$this->staff->isAdmin() && !$this->staff->hasPerm(OrganizationModel::PERM_EDIT)) {
            throw new McpException(-32602, 'Permission denied: Cannot edit organizations');
        }

        if (empty($args['org_id'])) {
            throw new McpException(-32602, 'Missing required field: org_id');
        }

        $org = Organization::lookup(intval($args['org_id']));
        if (!$org) {
            throw new McpException(-32602, 'Organization not found');
        }

        // Check if name is being changed and if it's unique
        if (isset($args['name']) && $args['name'] !== $org->getName()) {
            if (Organization::lookup(array('name' => $args['name']))) {
                throw new McpException(-32602, 'Organization with this name already exists');
            }
            $org->name = $args['name'];
        }

        // Update domain if provided
        if (isset($args['domain'])) {
            $org->domain = $args['domain'];
        }

        // Update manager if provided
        if (isset($args['manager'])) {
            if ($args['manager'] === '' || $args['manager'] === null) {
                $org->manager = '';
            } else {
                $manager = $args['manager'];
                if ($manager[0] === 's') {
                    if (!Staff::lookup(substr($manager, 1))) {
                        throw new McpException(-32602, 'Invalid staff ID in manager field');
                    }
                } elseif ($manager[0] === 't') {
                    if (!Team::lookup(substr($manager, 1))) {
                        throw new McpException(-32602, 'Invalid team ID in manager field');
                    }
                } else {
                    throw new McpException(-32602, 'Manager must be "s{staff_id}" or "t{team_id}"');
                }
                $org->manager = $manager;
            }
        }

        // Update flags - need to modify status directly since setStatus/clearStatus are protected
        $status = $org->status;

        if (isset($args['auto_add_members_as_collabs'])) {
            if ($args['auto_add_members_as_collabs']) {
                $status |= Organization::COLLAB_ALL_MEMBERS;
            } else {
                $status &= ~Organization::COLLAB_ALL_MEMBERS;
            }
        }

        if (isset($args['auto_add_primary_contacts_as_collabs'])) {
            if ($args['auto_add_primary_contacts_as_collabs']) {
                $status |= Organization::COLLAB_PRIMARY_CONTACT;
            } else {
                $status &= ~Organization::COLLAB_PRIMARY_CONTACT;
            }
        }

        if (isset($args['auto_assign_account_manager'])) {
            if ($args['auto_assign_account_manager']) {
                $status |= Organization::ASSIGN_AGENT_MANAGER;
            } else {
                $status &= ~Organization::ASSIGN_AGENT_MANAGER;
            }
        }

        if (isset($args['sharing'])) {
            // Clear sharing flags first
            $status &= ~(Organization::SHARE_PRIMARY_CONTACT | Organization::SHARE_EVERYBODY);

            switch ($args['sharing']) {
                case 'primary_contacts':
                    $status |= Organization::SHARE_PRIMARY_CONTACT;
                    break;
                case 'all_members':
                    $status |= Organization::SHARE_EVERYBODY;
                    break;
            }
        }

        $org->status = $status;

        if (!$org->save()) {
            throw new McpException(-32602, 'Failed to update organization');
        }

        return array(
            'success' => true,
            'organization' => $this->formatOrganizationSummary($org)
        );
    }

    /**
     * Add user to organization tool
     */
    private function tool_add_user_to_organization($args) {
        // Check permission - admins can always edit organizations
        if (!$this->staff->isAdmin() && !$this->staff->hasPerm(OrganizationModel::PERM_EDIT)) {
            throw new McpException(-32602, 'Permission denied: Cannot edit organizations');
        }

        if (empty($args['org_id'])) {
            throw new McpException(-32602, 'Missing required field: org_id');
        }

        if (empty($args['user_id']) && empty($args['user_email'])) {
            throw new McpException(-32602, 'Must specify user_id or user_email');
        }

        $org = Organization::lookup(intval($args['org_id']));
        if (!$org) {
            throw new McpException(-32602, 'Organization not found');
        }

        // Find user
        $user = null;
        if (!empty($args['user_id'])) {
            $user = User::lookup(intval($args['user_id']));
        } elseif (!empty($args['user_email'])) {
            $user = User::lookupByEmail($args['user_email']);
        }

        if (!$user) {
            throw new McpException(-32602, 'User not found');
        }

        // Check if user is already in this organization
        if ($user->getOrgId() == $org->getId()) {
            throw new McpException(-32602, 'User is already a member of this organization');
        }

        // Set organization
        if (!$user->setOrganization($org)) {
            throw new McpException(-32602, 'Failed to add user to organization');
        }

        // Set as primary contact if requested
        if (!empty($args['primary_contact'])) {
            $user->setPrimaryContact(true);
            $user->save();
        }

        return array(
            'success' => true,
            'user' => array(
                'id' => $user->getId(),
                'name' => (string) $user->getName(),
                'email' => $user->getEmail()
            ),
            'organization' => array(
                'id' => $org->getId(),
                'name' => $org->getName()
            ),
            'primary_contact' => !empty($args['primary_contact'])
        );
    }

    /**
     * Remove user from organization tool
     */
    private function tool_remove_user_from_organization($args) {
        // Check permission - admins can always edit organizations
        if (!$this->staff->isAdmin() && !$this->staff->hasPerm(OrganizationModel::PERM_EDIT)) {
            throw new McpException(-32602, 'Permission denied: Cannot edit organizations');
        }

        if (empty($args['user_id']) && empty($args['user_email'])) {
            throw new McpException(-32602, 'Must specify user_id or user_email');
        }

        // Find user
        $user = null;
        if (!empty($args['user_id'])) {
            $user = User::lookup(intval($args['user_id']));
        } elseif (!empty($args['user_email'])) {
            $user = User::lookupByEmail($args['user_email']);
        }

        if (!$user) {
            throw new McpException(-32602, 'User not found');
        }

        $org = $user->getOrganization();
        if (!$org) {
            throw new McpException(-32602, 'User is not a member of any organization');
        }

        $orgName = $org->getName();
        $orgId = $org->getId();

        // Remove from organization
        if (!$org->removeUser($user)) {
            throw new McpException(-32602, 'Failed to remove user from organization');
        }

        return array(
            'success' => true,
            'user' => array(
                'id' => $user->getId(),
                'name' => (string) $user->getName(),
                'email' => $user->getEmail()
            ),
            'removed_from' => array(
                'id' => $orgId,
                'name' => $orgName
            )
        );
    }

    /**
     * Get ticket statuses tool
     */
    private function tool_get_ticket_statuses($args) {
        $enabledOnly = $args['enabled_only'] ?? true;

        $statuses = TicketStatus::objects();

        // Filter by state if specified
        if (!empty($args['state'])) {
            $statuses->filter(array('state' => $args['state']));
        }

        // Filter by enabled status
        if ($enabledOnly) {
            $statuses->filter(array('mode__hasbit' => TicketStatus::ENABLED));
        }

        $statuses->order_by('sort', 'name');

        $results = array();
        foreach ($statuses as $status) {
            $results[] = array(
                'id' => $status->getId(),
                'name' => $status->getName(),
                'state' => $status->getState(),
                'sort_order' => $status->getSortOrder(),
                'enabled' => $status->isEnabled(),
                'internal' => $status->isInternal(),
                'default' => $status->isDefault(),
                'reopenable' => $status->isReopenable(),
                'ticket_count' => $status->getNumTickets()
            );
        }

        return array(
            'statuses' => $results,
            'total' => count($results)
        );
    }

    /**
     * Get current user tool
     */
    private function tool_get_current_user($args) {
        $staff = $this->staff;
        $role = $staff->getRole();

        // Get all permissions for this staff member
        $permissions = array();

        // Define permission categories and their permissions
        $permissionDefs = array(
            'tickets' => array(
                Ticket::PERM_CREATE => 'create',
                Ticket::PERM_EDIT => 'edit',
                Ticket::PERM_ASSIGN => 'assign',
                Ticket::PERM_TRANSFER => 'transfer',
                Ticket::PERM_REFER => 'refer',
                Ticket::PERM_MERGE => 'merge',
                Ticket::PERM_LINK => 'link',
                Ticket::PERM_REPLY => 'reply',
                Ticket::PERM_CLOSE => 'close',
                Ticket::PERM_DELETE => 'delete',
            ),
            'tasks' => array(
                TaskModel::PERM_CREATE => 'create',
                TaskModel::PERM_EDIT => 'edit',
                TaskModel::PERM_ASSIGN => 'assign',
                TaskModel::PERM_TRANSFER => 'transfer',
                TaskModel::PERM_CLOSE => 'close',
                TaskModel::PERM_DELETE => 'delete',
            ),
            'knowledgebase' => array(
                FAQ::PERM_MANAGE => 'manage',
            ),
            'organizations' => array(
                OrganizationModel::PERM_CREATE => 'create',
                OrganizationModel::PERM_EDIT => 'edit',
                OrganizationModel::PERM_DELETE => 'delete',
            ),
        );

        foreach ($permissionDefs as $category => $perms) {
            $permissions[$category] = array();
            foreach ($perms as $permKey => $permName) {
                $permissions[$category][$permName] = $staff->hasPerm($permKey);
            }
        }

        // Get departments access
        $departments = array();
        $deptIds = $staff->getDepts();
        if ($deptIds) {
            foreach ($deptIds as $deptId) {
                $dept = Dept::lookup($deptId);
                if ($dept) {
                    $departments[] = array(
                        'id' => $dept->getId(),
                        'name' => $dept->getName()
                    );
                }
            }
        }

        // Get teams
        $teams = array();
        foreach ($staff->teams as $tm) {
            if ($tm->team) {
                $teams[] = array(
                    'id' => $tm->team->getId(),
                    'name' => $tm->team->getName()
                );
            }
        }

        return array(
            'staff' => array(
                'id' => $staff->getId(),
                'username' => $staff->getUserName(),
                'name' => (string) $staff->getName(),
                'email' => $staff->getEmail(),
                'isadmin' => $staff->isAdmin(),
                'isactive' => $staff->isActive(),
                'onvacation' => $staff->onVacation()
            ),
            'role' => $role ? array(
                'id' => $role->getId(),
                'name' => $role->getName()
            ) : null,
            'department' => array(
                'id' => $staff->getDeptId(),
                'name' => $staff->getDept() ? $staff->getDept()->getName() : null
            ),
            'permissions' => $permissions,
            'departments_access' => $departments,
            'teams' => $teams
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
        foreach (TicketStatus::objects()->order_by('sort', 'name') as $status) {
            $statuses[] = array(
                'id' => $status->getId(),
                'name' => $status->getName(),
                'state' => $status->getState(),
                'sort_order' => $status->getSortOrder(),
                'enabled' => $status->isEnabled(),
                'internal' => $status->isInternal(),
                'default' => $status->isDefault()
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

    /**
     * FAQ Categories resource
     */
    private function resource_faq_categories() {
        $categories = array();
        foreach (Category::objects() as $cat) {
            $visibilityMap = array(
                Category::VISIBILITY_PRIVATE => 'private',
                Category::VISIBILITY_PUBLIC => 'public',
                Category::VISIBILITY_FEATURED => 'featured'
            );
            $categories[] = array(
                'id' => $cat->getId(),
                'name' => $cat->getName(),
                'description' => $cat->getDescription(),
                'visibility' => $visibilityMap[$cat->ispublic] ?? 'private',
                'parent_id' => $cat->category_pid ?: null,
                'faq_count' => $cat->getNumFAQs(true),
                'created' => $cat->getCreateDate(),
                'updated' => $cat->getUpdateDate()
            );
        }
        return array('categories' => $categories);
    }

    /**
     * Organizations resource
     */
    private function resource_organizations() {
        $orgs = array();
        foreach (Organization::objects() as $org) {
            $orgs[] = $this->formatOrganizationSummary($org);
        }
        return array('organizations' => $orgs);
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

        $data = array(
            'id' => $entry->getId(),
            'type' => $typeMap[$entry->type] ?? $entry->type,
            'poster' => $entry->poster,
            'body' => $entry->getBody() ? $entry->getBody()->getClean() : null,
            'created' => $entry->created,
            'staff_id' => $entry->staff_id,
            'user_id' => $entry->user_id,
            'attachments' => array()
        );

        // Add all attachments (both inline and separate)
        if ($entry->getAttachments()) {
            foreach ($entry->getAttachments()->getAll() as $att) {
                $data['attachments'][] = $this->formatAttachment($att);
            }
        }

        return $data;
    }

    /**
     * Format attachment
     */
    private function formatAttachment($att) {
        $file = $att->getFile();
        return array(
            'id' => $att->getId(),
            'file_id' => $att->getFileId(),
            'filename' => $att->getFilename(),
            'size' => $file ? $file->getSize() : null,
            'type' => $file ? $file->getType() : null,
            'inline' => (bool) $att->inline,
            'cid' => $file ? $file->getKey() : null,
            'download_url' => $file ? $file->getExternalDownloadUrl() : null
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

    /**
     * Format FAQ for summary list
     */
    private function formatFaqSummary($faq) {
        $visibilityMap = array(
            FAQ::VISIBILITY_PRIVATE => 'internal',
            FAQ::VISIBILITY_PUBLIC => 'public',
            FAQ::VISIBILITY_FEATURED => 'featured'
        );
        return array(
            'id' => $faq->getId(),
            'question' => $faq->getQuestion(),
            'teaser' => $faq->getTeaser(),
            'visibility' => $visibilityMap[$faq->ispublished] ?? 'internal',
            'category' => array(
                'id' => $faq->getCategoryId(),
                'name' => $faq->getCategory() ? $faq->getCategory()->getName() : null
            ),
            'created' => $faq->getCreateDate(),
            'updated' => $faq->getUpdateDate()
        );
    }

    /**
     * Format FAQ with full details
     */
    private function formatFaqFull($faq) {
        $data = $this->formatFaqSummary($faq);

        // Add full content
        $data['answer'] = $faq->getAnswer();
        $data['keywords'] = $faq->getKeywords();
        $data['notes'] = $faq->getNotes();

        // Add help topics
        $data['help_topics'] = array();
        foreach ($faq->getHelpTopics() as $ft) {
            if ($ft->topic) {
                $data['help_topics'][] = array(
                    'id' => $ft->topic->getId(),
                    'name' => $ft->topic->getName()
                );
            }
        }

        // Add attachment count
        $data['attachment_count'] = $faq->getNumAttachments();

        return $data;
    }

    /**
     * Format FAQ category for summary list
     */
    private function formatCategorySummary($cat) {
        $visibilityMap = array(
            Category::VISIBILITY_PRIVATE => 'private',
            Category::VISIBILITY_PUBLIC => 'public',
            Category::VISIBILITY_FEATURED => 'featured'
        );
        return array(
            'id' => $cat->getId(),
            'name' => $cat->getName(),
            'visibility' => $visibilityMap[$cat->ispublic] ?? 'private',
            'parent_id' => $cat->category_pid ?: null,
            'faq_count' => $cat->getNumFAQs(true),
            'created' => $cat->getCreateDate(),
            'updated' => $cat->getUpdateDate()
        );
    }

    /**
     * Format FAQ category with full details
     */
    private function formatCategoryFull($cat) {
        $data = $this->formatCategorySummary($cat);

        // Add description and notes
        $data['description'] = $cat->getDescription();
        $data['notes'] = $cat->getNotes();

        // Add parent category info
        if ($cat->category_pid && ($parent = Category::lookup($cat->category_pid))) {
            $data['parent'] = array(
                'id' => $parent->getId(),
                'name' => $parent->getName()
            );
        } else {
            $data['parent'] = null;
        }

        // Add subcategories
        $data['subcategories'] = array();
        $subcats = Category::objects()->filter(array('category_pid' => $cat->getId()));
        foreach ($subcats as $subcat) {
            $data['subcategories'][] = array(
                'id' => $subcat->getId(),
                'name' => $subcat->getName(),
                'faq_count' => $subcat->getNumFAQs(true)
            );
        }

        // Add total FAQ count including subcategories
        $data['total_faq_count'] = $cat->getNumFAQs(false);

        return $data;
    }

    /**
     * Format organization for summary list
     */
    private function formatOrganizationSummary($org) {
        // Determine sharing mode
        $sharing = 'none';
        if ($org->shareWithEverybody()) {
            $sharing = 'all_members';
        } elseif ($org->shareWithPrimaryContacts()) {
            $sharing = 'primary_contacts';
        }

        return array(
            'id' => $org->getId(),
            'name' => $org->getName(),
            'domain' => $org->domain ?: null,
            'member_count' => $org->getNumUsers(),
            'manager' => $this->formatAccountManager($org),
            'sharing' => $sharing,
            'created' => $org->getCreateDate(),
            'updated' => $org->getUpdateDate()
        );
    }

    /**
     * Format organization with full details
     */
    private function formatOrganizationFull($org, $includeMembers = true) {
        $data = $this->formatOrganizationSummary($org);

        // Add settings
        $data['settings'] = array(
            'auto_add_members_as_collabs' => $org->autoAddMembersAsCollabs(),
            'auto_add_primary_contacts_as_collabs' => $org->autoAddPrimaryContactsAsCollabs(),
            'auto_assign_account_manager' => $org->autoAssignAccountManager()
        );

        // Add members if requested
        if ($includeMembers) {
            $data['members'] = array();
            foreach ($org->allMembers() as $user) {
                $data['members'][] = array(
                    'id' => $user->getId(),
                    'name' => (string) $user->getName(),
                    'email' => $user->getEmail(),
                    'primary_contact' => $user->isPrimaryContact()
                );
            }
        }

        return $data;
    }

    /**
     * Format account manager (staff or team)
     */
    private function formatAccountManager($org) {
        $manager = $org->getAccountManager();
        if (!$manager) {
            return null;
        }

        if ($manager instanceof Staff) {
            return array(
                'type' => 'staff',
                'id' => $manager->getId(),
                'name' => (string) $manager->getName()
            );
        } elseif ($manager instanceof Team) {
            return array(
                'type' => 'team',
                'id' => $manager->getId(),
                'name' => $manager->getName()
            );
        }

        return null;
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
