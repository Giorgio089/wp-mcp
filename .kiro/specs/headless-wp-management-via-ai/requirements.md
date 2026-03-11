# Requirements Document

## Introduction

The Headless WP Management via AI system enables WordPress administrators to manage, debug, and control WordPress instances through local AI agents using natural language commands via terminal. The system bridges WordPress REST API with the Model Context Protocol (MCP) standard, allowing AI assistants like Claude to execute administrative tasks without using the WordPress dashboard.

## Glossary

- **MCP_Server**: The local Node.js or Python script running on the developer's machine that communicates with AI agents via stdio
- **WP_Plugin**: The WordPress plugin installed on the remote WordPress instance that exposes REST API endpoints
- **AI_Agent**: The local AI assistant (e.g., Claude Code) that sends commands through the MCP_Server
- **REST_Endpoint**: The WordPress REST API endpoint at /wp-json/mcp-bridge/v1/execute
- **Application_Password**: WordPress native authentication mechanism for REST API access
- **JSON_RPC_Command**: The structured command format sent from MCP_Server to WP_Plugin
- **Actor**: The server-side component that executes commands within WordPress
- **Fleet**: A collection of multiple WordPress instances managed by the same user

## Requirements

### Requirement 1: Secure REST API Endpoint

**User Story:** As a WordPress administrator, I want a secure REST API endpoint, so that only authorized AI agents can execute commands on my WordPress instance.

#### Acceptance Criteria

1. THE WP_Plugin SHALL register a REST_Endpoint at /wp-json/mcp-bridge/v1/execute
2. WHEN a request is received without valid Application_Password credentials, THE WP_Plugin SHALL return HTTP 401 Unauthorized
3. WHEN a request is received with valid Application_Password credentials, THE WP_Plugin SHALL authenticate the user
4. THE WP_Plugin SHALL verify that the authenticated user has administrator capabilities before executing commands
5. WHEN a non-administrator user attempts to execute commands, THE WP_Plugin SHALL return HTTP 403 Forbidden

### Requirement 2: JSON-RPC Command Processing

**User Story:** As a system integrator, I want standardized command processing, so that the MCP_Server can reliably communicate with the WP_Plugin.

#### Acceptance Criteria

1. WHEN a JSON_RPC_Command is received at the REST_Endpoint, THE Actor SHALL parse the command structure
2. IF the JSON_RPC_Command is malformed, THEN THE Actor SHALL return an error response with descriptive message
3. WHEN a valid JSON_RPC_Command is parsed, THE Actor SHALL identify the requested tool operation
4. THE Actor SHALL execute the requested operation and return results in JSON format
5. WHEN an operation fails, THE Actor SHALL return an error response with error code and message

### Requirement 3: Local MCP Server Communication

**User Story:** As an AI agent, I want to communicate via the MCP standard, so that I can control WordPress instances through natural language commands.

#### Acceptance Criteria

1. THE MCP_Server SHALL communicate with the AI_Agent via stdio according to MCP specification
2. WHEN the AI_Agent invokes a tool, THE MCP_Server SHALL translate it into an HTTP POST request
3. THE MCP_Server SHALL include Application_Password credentials in the HTTP request headers
4. WHEN the WP_Plugin returns a response, THE MCP_Server SHALL format it according to MCP protocol
5. THE MCP_Server SHALL handle connection errors and return appropriate error messages to the AI_Agent

### Requirement 4: Database Query Tool

**User Story:** As a WordPress administrator, I want to query the database through AI commands, so that I can quickly analyze data without using phpMyAdmin.

#### Acceptance Criteria

1. THE WP_Plugin SHALL expose a wp_query_db tool through the REST_Endpoint
2. WHEN a SELECT query is received, THE Actor SHALL execute it against the WordPress database
3. WHEN a query returns results, THE Actor SHALL return the results as JSON array
4. IF a query contains syntax errors, THEN THE Actor SHALL return a descriptive error message
5. THE Actor SHALL restrict queries to read-only operations by default
6. WHERE write access is configured, THE Actor SHALL allow INSERT, UPDATE, and DELETE operations
7. THE Actor SHALL sanitize all query inputs to prevent SQL injection

### Requirement 5: Plugin Management Tool

**User Story:** As a WordPress administrator, I want to manage plugins through AI commands, so that I can update, activate, or deactivate plugins without accessing the dashboard.

#### Acceptance Criteria

1. THE WP_Plugin SHALL expose a wp_manage_plugins tool through the REST_Endpoint
2. WHEN a plugin list request is received, THE Actor SHALL return all installed plugins with their status
3. WHEN a plugin activation command is received, THE Actor SHALL activate the specified plugin
4. WHEN a plugin deactivation command is received, THE Actor SHALL deactivate the specified plugin
5. WHEN a plugin update command is received, THE Actor SHALL update the specified plugin to the latest version
6. IF a plugin operation fails, THEN THE Actor SHALL return an error with the failure reason
7. WHEN a plugin is updated, THE Actor SHALL verify the site returns HTTP 200 status after update

### Requirement 6: WP-CLI Command Execution Tool

**User Story:** As a WordPress administrator, I want to execute WP-CLI commands through AI, so that I can perform advanced WordPress operations programmatically.

#### Acceptance Criteria

1. THE WP_Plugin SHALL expose a wp_run_cli tool through the REST_Endpoint
2. WHEN a WP-CLI command is received, THE Actor SHALL verify WP-CLI is available on the server
3. IF WP-CLI is not available, THEN THE Actor SHALL return an error message
4. WHEN WP-CLI is available, THE Actor SHALL execute the command with appropriate permissions
5. THE Actor SHALL capture both stdout and stderr from the WP-CLI execution
6. WHEN the WP-CLI command completes, THE Actor SHALL return the output and exit code
7. THE Actor SHALL enforce a timeout of 300 seconds for WP-CLI command execution
8. IF the command exceeds the timeout, THEN THE Actor SHALL terminate the process and return a timeout error

### Requirement 7: File System Access Tool

**User Story:** As a WordPress developer, I want to read and write theme files through AI commands, so that I can make quick fixes without using FTP or SSH.

#### Acceptance Criteria

1. THE WP_Plugin SHALL expose a wp_file_system tool through the REST_Endpoint
2. WHEN a file read request is received, THE Actor SHALL verify the path is within wp-content directory
3. IF the path is outside wp-content, THEN THE Actor SHALL return an error message
4. WHEN a valid file path is provided, THE Actor SHALL read the file contents and return them
5. WHEN a file write request is received, THE Actor SHALL verify write permissions
6. THE Actor SHALL create a backup of the original file before writing changes
7. WHEN file write completes, THE Actor SHALL return success confirmation with backup location
8. IF file operations fail due to permissions, THEN THE Actor SHALL return a descriptive error

### Requirement 8: Multi-Instance Fleet Management

**User Story:** As an agency administrator, I want to manage multiple WordPress instances, so that I can control my entire Fleet through a single AI interface.

#### Acceptance Criteria

1. THE MCP_Server SHALL support configuration for multiple WordPress instances
2. WHEN the AI_Agent specifies a target instance, THE MCP_Server SHALL route the command to the correct REST_Endpoint
3. THE MCP_Server SHALL maintain separate Application_Password credentials for each instance
4. WHEN no instance is specified, THE MCP_Server SHALL prompt the AI_Agent to select a target
5. THE MCP_Server SHALL allow batch operations across multiple instances in the Fleet

### Requirement 9: Configuration Management

**User Story:** As a system administrator, I want to configure the MCP_Server easily, so that I can add WordPress instances without editing code.

#### Acceptance Criteria

1. THE MCP_Server SHALL read configuration from a JSON or YAML file
2. THE configuration file SHALL store WordPress instance URLs and Application_Password credentials
3. WHEN the configuration file is missing, THE MCP_Server SHALL create a template configuration
4. THE MCP_Server SHALL validate configuration on startup and report any errors
5. WHERE environment variables are set, THE MCP_Server SHALL override file-based configuration

### Requirement 10: Error Handling and Logging

**User Story:** As a WordPress administrator, I want comprehensive error logging, so that I can troubleshoot issues when commands fail.

#### Acceptance Criteria

1. THE WP_Plugin SHALL log all REST_Endpoint requests to WordPress debug log
2. WHEN an error occurs, THE Actor SHALL log the error with timestamp and user context
3. THE MCP_Server SHALL log all HTTP requests and responses when debug mode is enabled
4. IF a WordPress instance is unreachable, THEN THE MCP_Server SHALL return a connection error to the AI_Agent
5. THE WP_Plugin SHALL include request IDs in responses for correlation with logs

### Requirement 11: Health Check and Status Verification

**User Story:** As a WordPress administrator, I want to verify site health after operations, so that I can ensure changes didn't break the site.

#### Acceptance Criteria

1. THE WP_Plugin SHALL expose a health check endpoint at /wp-json/mcp-bridge/v1/health
2. WHEN a health check is requested, THE Actor SHALL verify the WordPress site returns HTTP 200
3. THE Actor SHALL check database connectivity and return status
4. THE Actor SHALL report PHP version, WordPress version, and plugin version
5. WHEN critical errors are detected, THE Actor SHALL include them in the health check response

### Requirement 12: Rate Limiting and Security

**User Story:** As a security administrator, I want rate limiting on API requests, so that the system is protected from abuse.

#### Acceptance Criteria

1. THE WP_Plugin SHALL implement rate limiting on the REST_Endpoint
2. WHEN a client exceeds 60 requests per minute, THE WP_Plugin SHALL return HTTP 429 Too Many Requests
3. THE WP_Plugin SHALL allow administrators to configure rate limit thresholds
4. THE WP_Plugin SHALL log rate limit violations with client IP and user information
5. WHERE an IP is repeatedly blocked, THE WP_Plugin SHALL support temporary IP blocking for 15 minutes
