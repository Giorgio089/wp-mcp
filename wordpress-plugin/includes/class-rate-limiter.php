<?php
/**
 * Rate Limiter — prevents API abuse via WordPress transients.
 *
 * @package MCPBridge
 */

namespace MCPBridge;

defined( 'ABSPATH' ) || exit;

class Rate_Limiter {

	private int $limit;
	private int $window = 60; // seconds.

	public function __construct() {
		$this->limit = (int) get_option( 'mcp_bridge_rate_limit', 60 );
	}

	/**
	 * Check if the current request is allowed under the rate limit.
	 * Uses a per-user sliding window stored in transients.
	 */
	public function allow(): bool {
		if ( $this->limit <= 0 ) {
			return true; // 0 = unlimited.
		}

		$user_id    = get_current_user_id();
		$key        = "mcp_bridge_rl_{$user_id}";
		$current    = (int) get_transient( $key );

		if ( $current >= $this->limit ) {
			return false;
		}

		if ( $current === 0 ) {
			// First request in this window — set TTL.
			set_transient( $key, 1, $this->window );
		} else {
			// Increment without resetting TTL (keep original expiry).
			set_transient( $key, $current + 1, $this->window );
		}

		return true;
	}
}
