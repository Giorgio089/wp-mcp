<?php
/**
 * Tool: wp_site_health
 * Returns a comprehensive health overview of the WordPress installation.
 *
 * @package MCPBridge\Tools
 */

namespace MCPBridge\Tools;

defined( 'ABSPATH' ) || exit;

class WP_Site_Health implements Tool_Interface {

	public function execute( array $params ): array|\WP_Error {
		global $wpdb;

		// Load required WP internals.
		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		if ( ! class_exists( '\WP_Debug_Data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		}

		$active_plugins = get_option( 'active_plugins', [] );
		$updates        = get_core_updates();
		$core_ok        = ( ! is_wp_error( $updates ) && empty( $updates ) );

		// Check HTTP response of the site's home URL (basic liveness check).
		$http_check = wp_remote_get( home_url( '/' ), [
			'timeout'    => 10,
			'user-agent' => 'MCPBridge/1.0',
			'sslverify'  => apply_filters( 'https_local_ssl_verify', false ),
		] );

		$http_status  = is_wp_error( $http_check ) ? 0 : wp_remote_retrieve_response_code( $http_check );
		$http_ok      = ( $http_status === 200 );

		// DB table count.
		$db_tables = $wpdb->get_var( "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()" );

		// DB size in MB.
		$db_size_mb = $wpdb->get_var( $wpdb->prepare(
			"SELECT ROUND( SUM( data_length + index_length ) / 1024 / 1024, 2 )
			 FROM information_schema.TABLES
			 WHERE TABLE_SCHEMA = %s",
			DB_NAME
		) );

		// Plugin update count.
		$plugin_updates = get_site_transient( 'update_plugins' );
		$updates_count  = 0;
		if ( ! is_wp_error( $plugin_updates ) && isset( $plugin_updates->response ) ) {
			$updates_count = count( $plugin_updates->response );
		}

		return [
			'wordpress' => [
				'version'           => get_bloginfo( 'version' ),
				'core_up_to_date'   => $core_ok,
				'multisite'         => is_multisite(),
				'home_url'          => home_url(),
				'admin_email'       => get_option( 'admin_email' ),
				'active_theme'      => get_stylesheet(),
			],
			'php'       => [
				'version'           => PHP_VERSION,
				'memory_limit'      => ini_get( 'memory_limit' ),
				'max_execution_time'=> ini_get( 'max_execution_time' ),
				'upload_max_size'   => ini_get( 'upload_max_filesize' ),
			],
			'database'  => [
				'version'           => $wpdb->db_version(),
				'table_count'       => (int) $db_tables,
				'size_mb'           => (float) $db_size_mb,
				'charset'           => DB_CHARSET,
			],
			'server'    => [
				'http_status'       => $http_status,
				'http_ok'           => $http_ok,
				'server_software'   => sanitize_text_field( $_SERVER['SERVER_SOFTWARE'] ?? 'unknown' ),
				'https'             => is_ssl(),
			],
			'plugins'   => [
				'active_count'      => count( $active_plugins ),
				'updates_available' => $updates_count,
			],
		];
	}

	public function get_description(): string {
		return 'Returns a comprehensive health overview of the WordPress installation: WP version, PHP info, DB stats, HTTP status, and plugin update counts.';
	}

	public function get_parameters_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [],
			'required'   => [],
		];
	}
}
