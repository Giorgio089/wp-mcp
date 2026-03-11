<?php
/**
 * Authenticator — validates Application Password credentials and HTTP requirements.
 *
 * @package MCPBridge
 */

namespace MCPBridge;

defined( 'ABSPATH' ) || exit;

class Authenticator {

	/**
	 * Verify the incoming request is authenticated and meets security requirements.
	 *
	 * @param \WP_REST_Request $request
	 * @return bool|\WP_Error
	 */
	public function verify( \WP_REST_Request $request ): bool|\WP_Error {

		// 1. Enforce HTTPS in production.
		if ( get_option( 'mcp_bridge_require_https', true ) && ! is_ssl() && ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return new \WP_Error(
				'mcp_https_required',
				__( 'MCP Bridge requires HTTPS. Enable HTTPS on your server or disable this check in plugin settings (not recommended for production).', 'mcp-bridge' ),
				[ 'status' => 403 ]
			);
		}

		// 2. Check that the user is logged in via Application Password.
		//    WordPress REST API authenticates via Application Passwords before this callback runs.
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'mcp_unauthorized',
				__( 'Authentication required. Use WordPress Application Passwords via HTTP Basic Auth.', 'mcp-bridge' ),
				[
					'status'                 => 401,
					'WWW-Authenticate'       => 'Basic realm="MCP Bridge"',
				]
			);
		}

		// 3. Must be authenticated via an Application Password (not a cookie session).
		//    The global WP_REST_APPLICATION_PASSWORD_STATUS constant is set by WP core.
		if ( ! $this->is_application_password_auth() ) {
			return new \WP_Error(
				'mcp_app_password_required',
				__( 'MCP Bridge requires authentication via WordPress Application Passwords. Cookie-based sessions are not accepted.', 'mcp-bridge' ),
				[ 'status' => 403 ]
			);
		}

		// 4. Check user capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'mcp_insufficient_permissions',
				__( 'The authenticated user does not have the required "manage_options" capability.', 'mcp-bridge' ),
				[ 'status' => 403 ]
			);
		}

		// 5. Optional IP Whitelist check.
		$ip_error = $this->check_ip_whitelist( $request );
		if ( is_wp_error( $ip_error ) ) {
			return $ip_error;
		}

		return true;
	}

	/**
	 * Determine if the current request was authenticated via Application Password.
	 */
	private function is_application_password_auth(): bool {
		// WP 5.6+ sets this global when APP password auth succeeds.
		return apply_filters( 'mcp_bridge_is_app_password_auth', isset( $GLOBALS['wp_rest_application_password_uuid'] ) );
	}

	/**
	 * Check if the request IP is in the configured whitelist.
	 *
	 * @param \WP_REST_Request $request
	 * @return true|\WP_Error
	 */
	private function check_ip_whitelist( \WP_REST_Request $request ): bool|\WP_Error {
		$whitelist = (array) get_option( 'mcp_bridge_ip_whitelist', [] );

		// Empty whitelist means "allow all".
		if ( empty( $whitelist ) ) {
			return true;
		}

		$client_ip = $this->get_client_ip();

		if ( ! in_array( $client_ip, $whitelist, true ) ) {
			return new \WP_Error(
				'mcp_ip_forbidden',
				sprintf( __( 'Access denied. IP address %s is not whitelisted.', 'mcp-bridge' ), esc_html( $client_ip ) ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Get the real client IP, respecting reverse proxies.
	 */
	private function get_client_ip(): string {
		$headers = [
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_REAL_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		];

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
				return trim( $ip[0] );
			}
		}

		return '0.0.0.0';
	}
}
