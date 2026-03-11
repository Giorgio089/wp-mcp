<?php
/**
 * Admin Settings Page for MCP Bridge.
 *
 * @package MCPBridge
 */

namespace MCPBridge;

defined( 'ABSPATH' ) || exit;

class Settings {

	private const OPTION_GROUP = 'mcp_bridge_settings';
	private const PAGE_SLUG    = 'mcp-bridge-settings';

	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function add_menu_page(): void {
		add_options_page(
			__( 'MCP Bridge Settings', 'mcp-bridge' ),
			__( 'MCP Bridge', 'mcp-bridge' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function register_settings(): void {
		$fields = [
			'mcp_bridge_enabled_tools'   => 'mcp_bridge_sanitize_tools',
			'mcp_bridge_logging'         => 'rest_sanitize_boolean',
			'mcp_bridge_rate_limit'      => 'absint',
			'mcp_bridge_ip_whitelist'    => [ $this, 'sanitize_ip_whitelist' ],
			'mcp_bridge_require_https'   => 'rest_sanitize_boolean',
			'mcp_bridge_db_allow_writes' => 'rest_sanitize_boolean',
			'mcp_bridge_cli_path'        => 'sanitize_text_field',
			'mcp_bridge_cli_timeout'     => 'absint',
			'mcp_bridge_fs_max_file_size'=> 'absint',
		];

		foreach ( $fields as $option => $callback ) {
			register_setting( self::OPTION_GROUP, $option, [ 'sanitize_callback' => $callback ] );
		}
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) return;
		wp_enqueue_style( 'mcp-bridge-admin', MCP_BRIDGE_PLUGIN_URL . 'assets/admin.css', [], MCP_BRIDGE_VERSION );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$enabled_tools = (array) get_option( 'mcp_bridge_enabled_tools', [] );
		$all_tools     = [ 'wp_site_health', 'wp_query_db', 'wp_manage_plugins', 'wp_run_cli', 'wp_file_system' ];
		$high_risk     = [ 'wp_run_cli', 'wp_file_system' ];
		$tool_labels   = [
			'wp_site_health'    => __( 'Site Health — Read system info, HTTP status, DB stats', 'mcp-bridge' ),
			'wp_query_db'       => __( 'Query DB — Run SQL queries', 'mcp-bridge' ),
			'wp_manage_plugins' => __( 'Manage Plugins — List, activate, deactivate, update', 'mcp-bridge' ),
			'wp_run_cli'        => __( 'Run WP-CLI — Execute WP-CLI commands (⚠️ high risk)', 'mcp-bridge' ),
			'wp_file_system'    => __( 'File System — Read/write theme & plugin files (⚠️ high risk)', 'mcp-bridge' ),
		];

		?>
		<div class="wrap mcp-bridge-settings">
			<h1>
				<span class="mcp-bridge-logo">⚡</span>
				<?php esc_html_e( 'MCP Bridge Settings', 'mcp-bridge' ); ?>
				<span class="mcp-bridge-version">v<?php echo esc_html( MCP_BRIDGE_VERSION ); ?></span>
			</h1>

			<div class="mcp-bridge-endpoint-notice">
				<strong><?php esc_html_e( 'REST Endpoint:', 'mcp-bridge' ); ?></strong>
				<code><?php echo esc_html( rest_url( 'mcp-bridge/v1/execute' ) ); ?></code>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<div class="mcp-bridge-card">
					<h2><?php esc_html_e( 'Enabled Tools', 'mcp-bridge' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Only enabled tools can be called via the API.', 'mcp-bridge' ); ?></p>
					<?php foreach ( $all_tools as $tool ) : ?>
						<label class="mcp-bridge-tool-toggle <?php echo in_array( $tool, $high_risk, true ) ? 'is-danger' : ''; ?>">
							<input
								type="checkbox"
								name="mcp_bridge_enabled_tools[]"
								value="<?php echo esc_attr( $tool ); ?>"
								<?php checked( in_array( $tool, $enabled_tools, true ) ); ?>
							/>
							<span class="tool-name"><code><?php echo esc_html( $tool ); ?></code></span>
							<span class="tool-desc"><?php echo esc_html( $tool_labels[ $tool ] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>

				<div class="mcp-bridge-card">
					<h2><?php esc_html_e( 'Security', 'mcp-bridge' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Require HTTPS', 'mcp-bridge' ); ?></th>
							<td>
								<input type="checkbox" name="mcp_bridge_require_https" value="1" <?php checked( get_option( 'mcp_bridge_require_https', true ) ); ?> />
								<p class="description"><?php esc_html_e( 'Reject requests over HTTP (strongly recommended).', 'mcp-bridge' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Rate Limit', 'mcp-bridge' ); ?></th>
							<td>
								<input type="number" name="mcp_bridge_rate_limit" value="<?php echo esc_attr( get_option( 'mcp_bridge_rate_limit', 60 ) ); ?>" min="0" max="1000" class="small-text" />
								<p class="description"><?php esc_html_e( 'Max requests per minute per user. 0 = unlimited.', 'mcp-bridge' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'IP Whitelist', 'mcp-bridge' ); ?></th>
							<td>
								<textarea name="mcp_bridge_ip_whitelist" rows="4" class="large-text"><?php echo esc_textarea( implode( "\n", (array) get_option( 'mcp_bridge_ip_whitelist', [] ) ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'One IP per line. Leave empty to allow all IPs.', 'mcp-bridge' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="mcp-bridge-card">
					<h2><?php esc_html_e( 'Advanced Options', 'mcp-bridge' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Allow DB Writes', 'mcp-bridge' ); ?></th>
							<td>
								<input type="checkbox" name="mcp_bridge_db_allow_writes" value="1" <?php checked( get_option( 'mcp_bridge_db_allow_writes', false ) ); ?> />
								<p class="description"><?php esc_html_e( 'Allows INSERT/UPDATE/DELETE via wp_query_db. Use with extreme caution.', 'mcp-bridge' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'WP-CLI Binary Path', 'mcp-bridge' ); ?></th>
							<td>
								<input type="text" name="mcp_bridge_cli_path" value="<?php echo esc_attr( get_option( 'mcp_bridge_cli_path', '' ) ); ?>" class="large-text" placeholder="/usr/local/bin/wp" />
								<p class="description"><?php esc_html_e( 'Leave empty for auto-detection.', 'mcp-bridge' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'WP-CLI Timeout (s)', 'mcp-bridge' ); ?></th>
							<td>
								<input type="number" name="mcp_bridge_cli_timeout" value="<?php echo esc_attr( get_option( 'mcp_bridge_cli_timeout', 30 ) ); ?>" min="5" max="300" class="small-text" />
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Max File Size for Read (bytes)', 'mcp-bridge' ); ?></th>
							<td>
								<input type="number" name="mcp_bridge_fs_max_file_size" value="<?php echo esc_attr( get_option( 'mcp_bridge_fs_max_file_size', 524288 ) ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Default: 524288 (512 KB).', 'mcp-bridge' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="mcp-bridge-card">
					<h2><?php esc_html_e( 'Logging', 'mcp-bridge' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Enable Logging', 'mcp-bridge' ); ?></th>
							<td>
								<input type="checkbox" name="mcp_bridge_logging" value="1" <?php checked( get_option( 'mcp_bridge_logging', true ) ); ?> />
								<p class="description"><?php esc_html_e( 'Log all tool calls to the database.', 'mcp-bridge' ); ?></p>
							</td>
						</tr>
					</table>

					<?php if ( get_option( 'mcp_bridge_logging', true ) ) : ?>
						<h3><?php esc_html_e( 'Recent Log Entries', 'mcp-bridge' ); ?></h3>
						<table class="widefat striped mcp-bridge-log-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Time', 'mcp-bridge' ); ?></th>
									<th><?php esc_html_e( 'Tool', 'mcp-bridge' ); ?></th>
									<th><?php esc_html_e( 'IP', 'mcp-bridge' ); ?></th>
									<th><?php esc_html_e( 'Status', 'mcp-bridge' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( Logger::get_entries( 20 ) as $entry ) : ?>
									<tr>
										<td><?php echo esc_html( $entry['created_at'] ); ?></td>
										<td><code><?php echo esc_html( $entry['tool'] ); ?></code></td>
										<td><?php echo esc_html( $entry['ip_address'] ); ?></td>
										<td>
											<span class="mcp-bridge-status-<?php echo $entry['status_code'] === '200' ? 'ok' : 'error'; ?>">
												<?php echo esc_html( $entry['status_code'] ); ?>
											</span>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

				<?php submit_button( __( 'Save Settings', 'mcp-bridge' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function sanitize_ip_whitelist( mixed $value ): array {
		if ( is_array( $value ) ) {
			return array_filter( array_map( 'sanitize_text_field', $value ) );
		}
		// From textarea — split by newlines.
		$lines = preg_split( '/\r\n|\r|\n/', (string) $value );
		return array_filter( array_map( 'trim', $lines ) );
	}
}
