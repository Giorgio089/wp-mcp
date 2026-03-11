<?php
/**
 * Tool Registry — discovers, registers and dispatches all MCP tools.
 *
 * @package MCPBridge
 */

namespace MCPBridge;

defined( 'ABSPATH' ) || exit;

class Tool_Registry {

	/** @var array<string, Tools\Tool_Interface> */
	private array $tools = [];

	public function __construct() {
		$this->register_all();
	}

	/**
	 * Register all available tools.
	 */
	private function register_all(): void {
		$available = [
			'wp_site_health'    => Tools\WP_Site_Health::class,
			'wp_query_db'       => Tools\WP_Query_DB::class,
			'wp_manage_plugins' => Tools\WP_Manage_Plugins::class,
			'wp_run_cli'        => Tools\WP_Run_CLI::class,
			'wp_file_system'    => Tools\WP_File_System::class,
		];

		foreach ( $available as $name => $class ) {
			if ( class_exists( $class ) ) {
				$this->tools[ $name ] = new $class();
			}
		}
	}

	/**
	 * Check if a tool is enabled in settings.
	 */
	public function is_tool_enabled( string $tool_name ): bool {
		if ( ! isset( $this->tools[ $tool_name ] ) ) {
			return false;
		}

		$enabled = (array) get_option( 'mcp_bridge_enabled_tools', [] );
		return in_array( $tool_name, $enabled, true );
	}

	/**
	 * Dispatch execution to the requested tool.
	 *
	 * @param string $tool_name
	 * @param array  $params
	 * @return array|\WP_Error
	 */
	public function dispatch( string $tool_name, array $params ): array|\WP_Error {
		if ( ! isset( $this->tools[ $tool_name ] ) ) {
			return new \WP_Error(
				'mcp_unknown_tool',
				sprintf( __( 'Unknown tool: %s', 'mcp-bridge' ), esc_html( $tool_name ) ),
				[ 'status' => 404 ]
			);
		}

		try {
			return $this->tools[ $tool_name ]->execute( $params );
		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'mcp_tool_exception',
				$e->getMessage(),
				[ 'status' => 500, 'exception_class' => get_class( $e ) ]
			);
		}
	}

	/**
	 * Return schemas for all enabled tools (used by /tools endpoint).
	 */
	public function get_enabled_tools_schema(): array {
		$schemas = [];
		$enabled = (array) get_option( 'mcp_bridge_enabled_tools', [] );

		foreach ( $enabled as $name ) {
			if ( isset( $this->tools[ $name ] ) ) {
				$schemas[] = [
					'name'        => $name,
					'description' => $this->tools[ $name ]->get_description(),
					'parameters'  => $this->tools[ $name ]->get_parameters_schema(),
				];
			}
		}

		return $schemas;
	}
}
