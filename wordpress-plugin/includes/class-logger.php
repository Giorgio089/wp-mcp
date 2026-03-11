<?php
/**
 * Logger — writes tool calls to the mcp_bridge_log table.
 *
 * @package MCPBridge
 */

namespace MCPBridge;

defined( 'ABSPATH' ) || exit;

class Logger {

	/**
	 * Write a log entry (silently fails if logging is disabled).
	 *
	 * @param array{tool: string, params: array, status_code: int, response: mixed} $data
	 */
	public static function log( array $data ): void {
		if ( ! get_option( 'mcp_bridge_logging', true ) ) {
			return;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'mcp_bridge_log';

		$wpdb->insert(
			$table,
			[
				'ip_address'  => self::get_client_ip(),
				'tool'        => sanitize_text_field( $data['tool'] ?? '' ),
				'params'      => wp_json_encode( $data['params'] ?? [] ),
				'status_code' => (int) ( $data['status_code'] ?? 200 ),
				'response'    => wp_json_encode( $data['response'] ?? [] ),
			],
			[ '%s', '%s', '%s', '%d', '%s' ]
		);
	}

	/**
	 * Retrieve the last N log entries.
	 */
	public static function get_entries( int $limit = 50 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'mcp_bridge_log';
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ),
			ARRAY_A
		);
	}

	/**
	 * Purge all log entries older than $days days.
	 */
	public static function purge( int $days = 30 ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'mcp_bridge_log';
		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);
	}

	private static function get_client_ip(): string {
		$headers = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ];
		foreach ( $headers as $h ) {
			if ( ! empty( $_SERVER[ $h ] ) ) {
				$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $h ] ) ) );
				return trim( $parts[0] );
			}
		}
		return '0.0.0.0';
	}
}
