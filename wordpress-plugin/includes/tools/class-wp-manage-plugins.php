<?php
/**
 * Tool: wp_manage_plugins
 * Activate, deactivate, update or list WordPress plugins.
 *
 * @package MCPBridge\Tools
 */

namespace MCPBridge\Tools;

defined( 'ABSPATH' ) || exit;

class WP_Manage_Plugins implements Tool_Interface {

	public function execute( array $params ): array|\WP_Error {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}

		$action = sanitize_text_field( $params['action'] ?? '' );

		switch ( $action ) {
			case 'list':
				return $this->list_plugins( $params );
			case 'activate':
				return $this->activate( $params );
			case 'deactivate':
				return $this->deactivate( $params );
			case 'update':
				return $this->update( $params );
			default:
				return new \WP_Error(
					'mcp_invalid_action',
					sprintf( __( 'Unknown action "%s". Valid: list, activate, deactivate, update.', 'mcp-bridge' ), esc_html( $action ) ),
					[ 'status' => 400 ]
				);
		}
	}

	// -------------------------------------------------------------------------
	// Private action handlers
	// -------------------------------------------------------------------------

	private function list_plugins( array $params ): array {
		$status = sanitize_text_field( $params['status'] ?? 'all' );
		$all    = get_plugins();
		$active = get_option( 'active_plugins', [] );

		$out = [];
		foreach ( $all as $file => $data ) {
			$is_active = in_array( $file, $active, true );

			if ( $status === 'active' && ! $is_active ) continue;
			if ( $status === 'inactive' && $is_active ) continue;

			$out[] = [
				'plugin_file' => $file,
				'name'        => $data['Name'],
				'version'     => $data['Version'],
				'author'      => $data['Author'],
				'active'      => $is_active,
				'update_url'  => $data['PluginURI'] ?? '',
			];
		}

		// Check for updates available.
		$update_data = get_site_transient( 'update_plugins' );
		foreach ( $out as &$plugin ) {
			$plugin['update_available'] = isset( $update_data->response[ $plugin['plugin_file'] ] );
			if ( $plugin['update_available'] ) {
				$plugin['new_version'] = $update_data->response[ $plugin['plugin_file'] ]->new_version ?? null;
			}
		}
		unset( $plugin );

		return [
			'count'   => count( $out ),
			'plugins' => $out,
		];
	}

	private function activate( array $params ): array|\WP_Error {
		$plugin_file = sanitize_text_field( $params['plugin'] ?? '' );
		if ( empty( $plugin_file ) ) {
			return new \WP_Error( 'mcp_missing_param', __( 'Parameter "plugin" required (e.g. "akismet/akismet.php").', 'mcp-bridge' ), [ 'status' => 400 ] );
		}

		$result = activate_plugin( $plugin_file );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [ 'success' => true, 'plugin' => $plugin_file, 'status' => 'activated' ];
	}

	private function deactivate( array $params ): array|\WP_Error {
		$plugin_file = sanitize_text_field( $params['plugin'] ?? '' );
		if ( empty( $plugin_file ) ) {
			return new \WP_Error( 'mcp_missing_param', __( 'Parameter "plugin" required.', 'mcp-bridge' ), [ 'status' => 400 ] );
		}

		deactivate_plugins( $plugin_file );
		return [ 'success' => true, 'plugin' => $plugin_file, 'status' => 'deactivated' ];
	}

	private function update( array $params ): array|\WP_Error {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';

		$plugin_file = sanitize_text_field( $params['plugin'] ?? '' );

		// "all" — update every plugin with an available update.
		if ( $plugin_file === 'all' ) {
			$update_data = get_site_transient( 'update_plugins' );
			if ( empty( $update_data->response ) ) {
				return [ 'success' => true, 'message' => 'All plugins are up to date.', 'updated' => [] ];
			}

			$results = [];
			$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
			foreach ( array_keys( $update_data->response ) as $file ) {
				$result     = $upgrader->upgrade( $file );
				$results[]  = [
					'plugin'  => $file,
					'success' => ( $result === true ),
					'error'   => is_wp_error( $result ) ? $result->get_error_message() : null,
				];
			}
			return [ 'success' => true, 'updated' => $results ];
		}

		if ( empty( $plugin_file ) ) {
			return new \WP_Error( 'mcp_missing_param', __( 'Parameter "plugin" required, or use "all".', 'mcp-bridge' ), [ 'status' => 400 ] );
		}

		$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
		$result   = $upgrader->upgrade( $plugin_file );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [ 'success' => (bool) $result, 'plugin' => $plugin_file, 'status' => 'updated' ];
	}

	public function get_description(): string {
		return 'List, activate, deactivate, or update WordPress plugins. Use action=update with plugin="all" to update everything at once.';
	}

	public function get_parameters_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'action' => [
					'type'        => 'string',
					'enum'        => [ 'list', 'activate', 'deactivate', 'update' ],
					'description' => 'The action to perform.',
				],
				'plugin' => [
					'type'        => 'string',
					'description' => 'Plugin file (e.g. "akismet/akismet.php"). Use "all" for update action.',
				],
				'status' => [
					'type'        => 'string',
					'enum'        => [ 'all', 'active', 'inactive' ],
					'description' => 'Filter for list action. Default: all.',
					'default'     => 'all',
				],
			],
			'required'   => [ 'action' ],
		];
	}
}
