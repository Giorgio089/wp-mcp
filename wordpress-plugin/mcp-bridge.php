<?php
/**
 * Plugin Name:       MCP Bridge for WordPress
 * Plugin URI:        https://leanplugins.dev/wp-mcp-bridge
 * Description:       Connect your WordPress site to local AI agents (like Claude, Codex etc.) via the Model Context Protocol. Enables headless WP management through natural language.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            LeanPlugins
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

	// Split relative class into namespace parts and class name.
	// e.g. "Tools\WP_Site_Health" → subdir "tools", filename "class-wp-site-health.php"
	$relative_class = substr( $class, $len );
	$parts          = explode( '\\', $relative_class );
	$class_name     = array_pop( $parts );
	$subdir         = $parts ? strtolower( implode( '/', $parts ) ) . '/' : '';
	$file           = $base_dir . $subdir . 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * Sanitize callback for the mcp_bridge_enabled_tools option.
 * Ensures only known tool names are stored.
 *
 * @param mixed $value Raw submitted value (array of tool slugs from checkboxes).
 * @return array Filtered array containing only valid tool names.
 */
function mcp_bridge_sanitize_tools( mixed $value ): array {
	$allowed = [ 'wp_site_health', 'wp_query_db', 'wp_manage_plugins', 'wp_run_cli', 'wp_file_system' ];

	if ( ! is_array( $value ) ) {
		return [];
	}

	return array_values( array_intersect( array_map( 'sanitize_text_field', $value ), $allowed ) );
}

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
