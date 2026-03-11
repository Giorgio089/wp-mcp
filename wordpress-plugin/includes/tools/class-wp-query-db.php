<?php
/**
 * Tool: wp_query_db
 * Read-only (and optionally write) access to the WordPress database.
 *
 * @package MCPBridge\Tools
 */

namespace MCPBridge\Tools;

defined( 'ABSPATH' ) || exit;

class WP_Query_DB implements Tool_Interface {

	// SQL statements that modify data — blocked unless write mode is explicitly enabled.
	private const WRITE_KEYWORDS = [ 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'REPLACE' ];

	public function execute( array $params ): array|\WP_Error {
		global $wpdb;

		$sql               = trim( sanitize_textarea_field( $params['sql'] ?? '' ) );
		$allow_writes      = (bool) get_option( 'mcp_bridge_db_allow_writes', false );
		$max_rows          = min( (int) ( $params['limit'] ?? 100 ), 500 ); // Hard cap at 500.

		if ( empty( $sql ) ) {
			return new \WP_Error( 'mcp_missing_sql', __( 'Parameter "sql" is required.', 'mcp-bridge' ), [ 'status' => 400 ] );
		}

		// Detect write operations.
		$upper_sql    = strtoupper( $sql );
		$is_write     = false;
		foreach ( self::WRITE_KEYWORDS as $keyword ) {
			if ( str_starts_with( ltrim( $upper_sql ), $keyword ) ) {
				$is_write = true;
				break;
			}
		}

		if ( $is_write && ! $allow_writes ) {
			return new \WP_Error(
				'mcp_db_readonly',
				__( 'Write operations (INSERT, UPDATE, DELETE, etc.) are disabled. Enable "Allow DB writes" in MCP Bridge settings.', 'mcp-bridge' ),
				[ 'status' => 403 ]
			);
		}

		// Inject LIMIT for SELECT queries to prevent memory exhaustion.
		if ( ! $is_write && ! str_contains( $upper_sql, 'LIMIT' ) ) {
			$sql .= " LIMIT {$max_rows}";
		}

		// Suppress errors and run query.
		$wpdb->hide_errors();

		if ( $is_write ) {
			$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
			$wpdb->show_errors();

			if ( $result === false ) {
				return new \WP_Error( 'mcp_db_error', $wpdb->last_error, [ 'status' => 500 ] );
			}

			return [
				'type'          => 'write',
				'rows_affected' => $result,
				'insert_id'     => $wpdb->insert_id,
				'last_error'    => $wpdb->last_error,
			];
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$wpdb->show_errors();

		if ( $rows === null ) {
			return new \WP_Error( 'mcp_db_error', $wpdb->last_error ?: 'Unknown DB error', [ 'status' => 500 ] );
		}

		return [
			'type'      => 'select',
			'row_count' => count( $rows ),
			'rows'      => $rows,
		];
	}

	public function get_description(): string {
		return 'Execute SQL queries against the WordPress database. Returns up to 500 rows for SELECT. Write operations (INSERT/UPDATE/DELETE) require explicit opt-in in settings.';
	}

	public function get_parameters_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'sql'   => [
					'type'        => 'string',
					'description' => 'The SQL query to execute. Use {prefix} as a placeholder for the wpdb table prefix.',
				],
				'limit' => [
					'type'        => 'integer',
					'description' => 'Max rows to return for SELECT queries. Default 100, capped at 500.',
					'default'     => 100,
				],
			],
			'required'   => [ 'sql' ],
		];
	}
}
