<?php
/**
 * Tool: wp_file_system
 * Read and write WordPress theme/plugin files via the WP Filesystem API.
 * Disabled by default — must be explicitly enabled in settings.
 *
 * @package MCPBridge\Tools
 */

namespace MCPBridge\Tools;

defined( 'ABSPATH' ) || exit;

class WP_File_System implements Tool_Interface {

	/** Paths that are always off-limits regardless of settings. */
	private const BLOCKED_PATHS = [
		'/wp-config.php',
		'/wp-settings.php',
		'/.htaccess',
		'/wp-cron.php',
	];

	/** Allowed root directories for file operations. */
	private const ALLOWED_ROOTS = [
		WP_CONTENT_DIR . '/themes/',
		WP_CONTENT_DIR . '/plugins/',
		WP_CONTENT_DIR . '/mu-plugins/',
		WP_CONTENT_DIR . '/uploads/',
	];

	public function execute( array $params ): array|\WP_Error {
		$action = sanitize_text_field( $params['action'] ?? '' );

		switch ( $action ) {
			case 'read':
				return $this->read_file( $params );
			case 'write':
				return $this->write_file( $params );
			case 'list':
				return $this->list_dir( $params );
			default:
				return new \WP_Error(
					'mcp_invalid_action',
					__( 'Valid actions: read, write, list.', 'mcp-bridge' ),
					[ 'status' => 400 ]
				);
		}
	}

	// -------------------------------------------------------------------------
	// Private action handlers
	// -------------------------------------------------------------------------

	private function read_file( array $params ): array|\WP_Error {
		$path = $this->resolve_and_validate( $params['path'] ?? '' );
		if ( is_wp_error( $path ) ) return $path;

		$fs = $this->get_fs();

		if ( ! $fs->exists( $path ) ) {
			return new \WP_Error( 'mcp_file_not_found', sprintf( __( 'File not found: %s', 'mcp-bridge' ), esc_html( $path ) ), [ 'status' => 404 ] );
		}
		if ( ! $fs->is_file( $path ) ) {
			return new \WP_Error( 'mcp_not_a_file', __( 'Path is not a file.', 'mcp-bridge' ), [ 'status' => 400 ] );
		}

		$size = $fs->size( $path );
		$max  = (int) get_option( 'mcp_bridge_fs_max_file_size', 512 * 1024 ); // 512 KB default.

		if ( $size > $max ) {
			return new \WP_Error(
				'mcp_file_too_large',
				sprintf( __( 'File size (%d bytes) exceeds limit (%d bytes).', 'mcp-bridge' ), $size, $max ),
				[ 'status' => 413 ]
			);
		}

		$contents = $fs->get_contents( $path );
		return [
			'path'     => $path,
			'size'     => $size,
			'contents' => $contents,
			'encoding' => 'utf8',
		];
	}

	private function write_file( array $params ): array|\WP_Error {
		$path     = $this->resolve_and_validate( $params['path'] ?? '' );
		if ( is_wp_error( $path ) ) return $path;

		$contents = $params['contents'] ?? null;
		$confirm  = (bool) ( $params['confirm'] ?? false );

		if ( $contents === null ) {
			return new \WP_Error( 'mcp_missing_param', __( 'Parameter "contents" is required for write action.', 'mcp-bridge' ), [ 'status' => 400 ] );
		}
		if ( ! $confirm ) {
			return new \WP_Error(
				'mcp_confirm_required',
				__( 'Writing files is destructive. Re-send with "confirm": true to proceed.', 'mcp-bridge' ),
				[ 'status' => 400, 'requires_confirm' => true ]
			);
		}

		$fs = $this->get_fs();

		// Create a backup before overwriting.
		if ( $fs->exists( $path ) ) {
			$backup_path = $path . '.mcp-backup-' . time();
			$fs->copy( $path, $backup_path );
		}

		$result = $fs->put_contents( $path, $contents, FS_CHMOD_FILE );
		if ( ! $result ) {
			return new \WP_Error( 'mcp_write_failed', sprintf( __( 'Failed to write file: %s', 'mcp-bridge' ), esc_html( $path ) ), [ 'status' => 500 ] );
		}

		return [
			'success'       => true,
			'path'          => $path,
			'bytes_written' => strlen( $contents ),
			'backup_created'=> isset( $backup_path ),
		];
	}

	private function list_dir( array $params ): array|\WP_Error {
		$path = $this->resolve_and_validate( $params['path'] ?? '' );
		if ( is_wp_error( $path ) ) return $path;

		$fs = $this->get_fs();

		if ( ! $fs->is_dir( $path ) ) {
			return new \WP_Error( 'mcp_not_a_dir', __( 'Path is not a directory.', 'mcp-bridge' ), [ 'status' => 400 ] );
		}

		$items = $fs->dirlist( $path );
		if ( $items === false ) {
			return new \WP_Error( 'mcp_dir_read_failed', __( 'Could not read directory.', 'mcp-bridge' ), [ 'status' => 500 ] );
		}

		$entries = [];
		foreach ( $items as $name => $info ) {
			$entries[] = [
				'name'  => $name,
				'type'  => $info['type'] === 'f' ? 'file' : 'directory',
				'size'  => (int) ( $info['size'] ?? 0 ),
			];
		}

		usort( $entries, fn( $a, $b ) => strcmp( $a['type'] . $a['name'], $b['type'] . $b['name'] ) );

		return [ 'path' => $path, 'count' => count( $entries ), 'entries' => $entries ];
	}

	// -------------------------------------------------------------------------
	// Security helpers
	// -------------------------------------------------------------------------

	/**
	 * Resolve a relative path and validate it is within allowed roots.
	 *
	 * @return string|WP_Error  Absolute resolved path or error.
	 */
	private function resolve_and_validate( string $path ): string|\WP_Error {
		if ( empty( $path ) ) {
			return new \WP_Error( 'mcp_missing_param', __( 'Parameter "path" is required.', 'mcp-bridge' ), [ 'status' => 400 ] );
		}

		// Make absolute if given a relative path under WP_CONTENT_DIR.
		if ( ! str_starts_with( $path, '/' ) ) {
			$path = WP_CONTENT_DIR . '/' . ltrim( $path, '/' );
		}

		// Canonicalise (resolve ../, symlinks etc.).
		$real = realpath( $path ) ?: $path;

		// Block hard-coded dangerous paths.
		foreach ( self::BLOCKED_PATHS as $blocked ) {
			if ( str_ends_with( $real, $blocked ) ) {
				return new \WP_Error( 'mcp_path_blocked', sprintf( __( 'Access to "%s" is permanently blocked.', 'mcp-bridge' ), esc_html( $blocked ) ), [ 'status' => 403 ] );
			}
		}

		// Ensure path is within an allowed root.
		foreach ( self::ALLOWED_ROOTS as $root ) {
			if ( str_starts_with( $real, realpath( $root ) ?: $root ) ) {
				return $real;
			}
		}

		return new \WP_Error(
			'mcp_path_forbidden',
			__( 'Path is outside the allowed directories (themes, plugins, mu-plugins, uploads).', 'mcp-bridge' ),
			[ 'status' => 403 ]
		);
	}

	private function get_fs(): \WP_Filesystem_Base {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		return $wp_filesystem;
	}

	public function get_description(): string {
		return 'Read, write, or list files within WordPress theme, plugin, mu-plugin, and uploads directories. Write requires confirm=true and creates an automatic .mcp-backup before overwriting.';
	}

	public function get_parameters_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'action'   => [
					'type'        => 'string',
					'enum'        => [ 'read', 'write', 'list' ],
					'description' => 'File system action to perform.',
				],
				'path'     => [
					'type'        => 'string',
					'description' => 'Absolute or WP-content-relative path. E.g. "themes/my-theme/functions.php".',
				],
				'contents' => [
					'type'        => 'string',
					'description' => 'New file contents (required for write action).',
				],
				'confirm'  => [
					'type'        => 'boolean',
					'description' => 'Must be true for write operations.',
					'default'     => false,
				],
			],
			'required'   => [ 'action', 'path' ],
		];
	}
}
