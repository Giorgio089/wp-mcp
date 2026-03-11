<?php
/**
 * Tool: wp_run_cli
 * Execute WP-CLI commands on the server.
 * Disabled by default — must be explicitly enabled in settings.
 *
 * @package MCPBridge\Tools
 */

namespace MCPBridge\Tools;

defined( 'ABSPATH' ) || exit;

class WP_Run_CLI implements Tool_Interface {

	/**
	 * Dangerous sub-commands that require an explicit `confirm: true` parameter.
	 */
	private const DESTRUCTIVE_COMMANDS = [
		'db drop',
		'db reset',
		'user delete',
		'plugin delete',
		'theme delete',
		'site delete',
		'cache flush',  // Not destructive, but included for demo.
	];

	/**
	 * Hard-blocked commands that are never allowed.
	 */
	private const BLOCKED_COMMANDS = [
		'eval',
		'eval-file',
		'shell',
	];

	public function execute( array $params ): array|\WP_Error {
		$command = trim( sanitize_text_field( $params['command'] ?? '' ) );
		$confirm = (bool) ( $params['confirm'] ?? false );

		if ( empty( $command ) ) {
			return new \WP_Error( 'mcp_missing_param', __( 'Parameter "command" is required.', 'mcp-bridge' ), [ 'status' => 400 ] );
		}

		// Block dangerous eval/shell commands entirely.
		foreach ( self::BLOCKED_COMMANDS as $blocked ) {
			if ( str_starts_with( ltrim( $command ), $blocked ) ) {
				return new \WP_Error(
					'mcp_cli_blocked',
					sprintf( __( 'WP-CLI command "%s" is permanently blocked for security reasons.', 'mcp-bridge' ), esc_html( $blocked ) ),
					[ 'status' => 403 ]
				);
			}
		}

		// Require confirmation for destructive commands.
		foreach ( self::DESTRUCTIVE_COMMANDS as $destructive ) {
			if ( str_contains( $command, $destructive ) && ! $confirm ) {
				return new \WP_Error(
					'mcp_cli_confirm_required',
					sprintf(
						__( 'Command "%s" is destructive. Re-send with "confirm": true to proceed.', 'mcp-bridge' ),
						esc_html( $command )
					),
					[ 'status' => 400, 'requires_confirm' => true ]
				);
			}
		}

		// Locate WP-CLI binary.
		$wp_cli = $this->find_wp_cli();
		if ( ! $wp_cli ) {
			return new \WP_Error(
				'mcp_cli_not_found',
				__( 'WP-CLI not found. Install it or add its path to the plugin settings.', 'mcp-bridge' ),
				[ 'status' => 500 ]
			);
		}

		// Build the full CLI call.
		$abspath    = escapeshellarg( ABSPATH );
		$wp_escaped = escapeshellcmd( $command );
		$full_cmd   = "{$wp_cli} --path={$abspath} --no-color {$wp_escaped} 2>&1";

		$timeout  = (int) get_option( 'mcp_bridge_cli_timeout', 30 );
		$output   = null;
		$exit_code = 0;

		// Use proc_open for timeout control.
		$descriptors = [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ];
		$process = proc_open( $full_cmd, $descriptors, $pipes );

		if ( ! is_resource( $process ) ) {
			return new \WP_Error( 'mcp_cli_exec_failed', __( 'Failed to start WP-CLI process.', 'mcp-bridge' ), [ 'status' => 500 ] );
		}

		fclose( $pipes[0] );
		$stdout    = stream_get_contents( $pipes[1] );
		$stderr    = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$exit_code = proc_close( $process );

		return [
			'command'   => $command,
			'exit_code' => $exit_code,
			'output'    => trim( $stdout ?: $stderr ),
			'success'   => $exit_code === 0,
		];
	}

	/**
	 * Find the WP-CLI binary — checks settings, then common paths.
	 */
	private function find_wp_cli(): string|false {
		// Custom path from settings.
		$custom = get_option( 'mcp_bridge_cli_path', '' );
		if ( $custom && is_executable( $custom ) ) {
			return $custom;
		}

		// Common installation paths.
		$paths = [ '/usr/local/bin/wp', '/usr/bin/wp', '/home/wp', '/opt/homebrew/bin/wp' ];
		foreach ( $paths as $path ) {
			if ( is_executable( $path ) ) {
				return $path;
			}
		}

		// Try `which wp` as last resort.
		$which = trim( (string) shell_exec( 'which wp 2>/dev/null' ) );
		return ( $which && is_executable( $which ) ) ? $which : false;
	}

	public function get_description(): string {
		return 'Execute WP-CLI commands on the server. Destructive commands require confirm=true. Eval/shell commands are permanently blocked. WP-CLI must be installed on the server.';
	}

	public function get_parameters_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'command' => [
					'type'        => 'string',
					'description' => 'The WP-CLI command to run (without the leading "wp"). E.g. "plugin list", "cache flush", "user list".',
				],
				'confirm' => [
					'type'        => 'boolean',
					'description' => 'Set to true to confirm execution of destructive commands.',
					'default'     => false,
				],
			],
			'required'   => [ 'command' ],
		];
	}
}
