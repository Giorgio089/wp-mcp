<?php
/**
 * Base interface all MCP tools must implement.
 *
 * @package MCPBridge\Tools
 */

namespace MCPBridge\Tools;

defined( 'ABSPATH' ) || exit;

interface Tool_Interface {

	/**
	 * Execute the tool with the provided parameters.
	 *
	 * @param array $params
	 * @return array|\WP_Error  Structured result data or WP_Error on failure.
	 */
	public function execute( array $params ): array|\WP_Error;

	/**
	 * Return a human-readable description of what this tool does.
	 */
	public function get_description(): string;

	/**
	 * Return a JSON Schema (as PHP array) describing accepted parameters.
	 * Compatible with the MCP tool-call spec.
	 */
	public function get_parameters_schema(): array;
}
