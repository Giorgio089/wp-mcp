<?php
/**
 * REST API Controller — registers the /execute endpoint and dispatches tool calls.
 *
 * @package MCPBridge
 */

namespace MCPBridge;

defined( 'ABSPATH' ) || exit;

class REST_Controller extends \WP_REST_Controller {

	protected $namespace = 'mcp-bridge/v1';
	protected $rest_base = 'execute';

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'execute_tool' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => $this->get_endpoint_args(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		// Endpoint to list available (enabled) tools.
		register_rest_route(
			$this->namespace,
			'/tools',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_tools' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);
	}

	/**
	 * Verify Application Password authentication and capabilities.
	 */
	public function check_permission( \WP_REST_Request $request ): bool|\WP_Error {
		$authenticator = new Authenticator();
		return $authenticator->verify( $request );
	}

	/**
	 * Execute a tool call.
	 */
	public function execute_tool( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tool_name = sanitize_text_field( $request->get_param( 'tool' ) );
		$params    = (array) $request->get_param( 'params' );
		$request_id = sanitize_text_field( $request->get_param( 'id' ) ?? uniqid( 'mcp_', true ) );

		$registry = new Tool_Registry();

		if ( ! $registry->is_tool_enabled( $tool_name ) ) {
			return new \WP_Error(
				'mcp_tool_disabled',
				sprintf( __( 'Tool "%s" is not enabled or does not exist.', 'mcp-bridge' ), $tool_name ),
				[ 'status' => 403 ]
			);
		}

		// Rate limit check.
		$rate_checker = new Rate_Limiter();
		if ( ! $rate_checker->allow() ) {
			return new \WP_Error(
				'mcp_rate_limit',
				__( 'Rate limit exceeded. Please slow down.', 'mcp-bridge' ),
				[ 'status' => 429 ]
			);
		}

		$result = $registry->dispatch( $tool_name, $params );

		// Log the request.
		Logger::log( [
			'tool'        => $tool_name,
			'params'      => $params,
			'status_code' => $result instanceof \WP_Error ? 500 : 200,
			'response'    => $result,
		] );

		if ( $result instanceof \WP_Error ) {
			return $result;
		}

		// JSON-RPC 2.0 compatible response.
		return rest_ensure_response( [
			'jsonrpc' => '2.0',
			'id'      => $request_id,
			'result'  => $result,
		] );
	}

	/**
	 * List all currently enabled tools with their schemas.
	 */
	public function list_tools( \WP_REST_Request $request ): \WP_REST_Response {
		$registry = new Tool_Registry();
		return rest_ensure_response( [
			'tools' => $registry->get_enabled_tools_schema(),
		] );
	}

	/**
	 * Endpoint argument definitions.
	 */
	private function get_endpoint_args(): array {
		return [
			'tool'   => [
				'required'    => true,
				'type'        => 'string',
				'description' => 'The name of the tool to execute.',
				'pattern'     => '^[a-z_]+$',
			],
			'params' => [
				'required'    => false,
				'type'        => 'object',
				'description' => 'Tool-specific parameters.',
				'default'     => [],
			],
			'id'     => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'JSON-RPC request ID for correlation.',
			],
		];
	}
}
