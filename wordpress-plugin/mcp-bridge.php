<?php
/**
 * Plugin Name:       MCP Bridge for WordPress
 * Plugin URI:        https://github.com/georgsimic/mcp-server-wp
 * Description:       Connect your WordPress site to local AI agents (Claude, Gemini) via the Model Context Protocol. Enables headless WP management through natural language.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Georg Simic
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mcp-bridge
 * Domain Path:       /languages
 *
 * @package MCPBridge
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'MCP_BRIDGE_VERSION', '1.0.0' );
define( 'MCP_BRIDGE_PLUGIN_FILE', __FILE__ );
define( 'MCP_BRIDGE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MCP_BRIDGE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MCP_BRIDGE_NAMESPACE', 'mcp-bridge/v1' );

// Autoloader.
spl_autoload_register( function ( string $class ) {
	$prefix   = 'MCPBridge\\';
	$base_dir = MCP_BRIDGE_PLUGIN_DIR . 'includes/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file           = $base_dir . 'class-' . strtolower( str_replace( [ '\\', '_' ], [ '/', '-' ], $relative_class ) ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * Initialize the plugin.
 */
function mcp_bridge_init(): void {
	$plugin = new MCPBridge\Plugin();
	$plugin->init();
}

add_action( 'plugins_loaded', 'mcp_bridge_init' );

/**
 * Activation hook — create DB log table if logging is enabled.
 */
register_activation_hook( __FILE__, function () {
	MCPBridge\Installer::activate();
} );

/**
 * Deactivation hook.
 */
register_deactivation_hook( __FILE__, function () {
	MCPBridge\Installer::deactivate();
} );
