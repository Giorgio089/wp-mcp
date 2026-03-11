<?php
/**
 * Main Plugin class — sets up hooks and initializes subsystems.
 *
 * @package MCPBridge
 */

namespace MCPBridge;

defined( 'ABSPATH' ) || exit;

class Plugin {

	public function init(): void {
		$this->load_textdomain();
		$this->register_rest_routes();

		if ( is_admin() ) {
			$settings = new Settings();
			$settings->init();
		}
	}

	private function load_textdomain(): void {
		load_plugin_textdomain(
			'mcp-bridge',
			false,
			dirname( plugin_basename( MCP_BRIDGE_PLUGIN_FILE ) ) . '/languages'
		);
	}

	private function register_rest_routes(): void {
		add_action( 'rest_api_init', function () {
			$controller = new REST_Controller();
			$controller->register_routes();
		} );
	}
}
