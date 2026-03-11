<?php
/**
 * Handles plugin activation and deactivation tasks.
 *
 * @package MCPBridge
 */

namespace MCPBridge;

defined( 'ABSPATH' ) || exit;

class Installer {

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'mcp_bridge_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			ip_address  VARCHAR(45)         NOT NULL DEFAULT '',
			tool        VARCHAR(100)        NOT NULL DEFAULT '',
			params      LONGTEXT            NOT NULL,
			status_code SMALLINT            NOT NULL DEFAULT 200,
			response    LONGTEXT            NOT NULL,
			PRIMARY KEY (id),
			KEY tool (tool),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Store the plugin version for future migrations.
		update_option( 'mcp_bridge_version', MCP_BRIDGE_VERSION );

		// Set default options.
		$defaults = [
			'enabled_tools'  => [ 'wp_site_health', 'wp_manage_plugins', 'wp_query_db' ],
			'logging'        => true,
			'rate_limit'     => 60,    // requests per minute.
			'ip_whitelist'   => [],
			'require_https'  => true,
		];

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( "mcp_bridge_{$key}" ) ) {
				update_option( "mcp_bridge_{$key}", $value );
			}
		}
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate(): void {
		// Nothing to clean up on deactivation — data is preserved.
	}
}
