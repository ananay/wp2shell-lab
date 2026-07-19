<?php
/**
 * WP_REST_Server — request routing plus the /batch/v1 multi-request endpoint.
 *
 * @package mini-wp-rest
 */

class WP_REST_Server {

	/** @var array route => handler-attributes */
	protected $routes = array();

	public function __construct() {
		$this->register_core_routes();
	}

	protected function register_core_routes() {
		$posts = new WP_REST_Posts_Controller();
		$this->routes['/wp/v2/posts'] = $posts->get_route_args();
	}

	/**
	 * Find the handler whose route + method match a request.
	 *
	 * @param WP_REST_Request $request
	 * @return array|WP_Error Handler attributes, or WP_Error if no match.
	 */
	public function match_request_to_handler( $request ) {
		$route = $request->get_route();
		if ( isset( $this->routes[ $route ] ) ) {
			$handler = $this->routes[ $route ];
			if ( strtoupper( $handler['methods'] ) === $request->get_method() ) {
				return $handler;
			}
		}
		return new WP_Error( 'rest_no_route', 'No route was found matching the URL and request method.' );
	}

	/**
	 * Dispatch a single request against its matched handler.
	 *
	 * @param WP_REST_Request $request
	 * @param array           $handler Attributes bound to THIS request.
	 * @return mixed
	 */
	public function dispatch( $request, $handler ) {
		$request->set_attributes( $handler );

		// Run the permission gate, then the handler's own arg sanitizers.
		if ( isset( $handler['permission_callback'] ) ) {
			$allowed = call_user_func( $handler['permission_callback'], $request );
			if ( is_wp_error( $allowed ) || false === $allowed ) {
				return new WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to do that.' );
			}
		}
		$request->sanitize_params();

		return call_user_func( $handler['callback'], $request );
	}

	/**
	 * POST /batch/v1 — run many sub-requests in one round trip.
	 *
	 * Parse each sub-request path, match it to a handler, then dispatch them
	 * all. We skip requests whose path fails to parse so a single bad entry
	 * doesn't abort the whole batch.
	 *
	 * @param WP_REST_Request $batch
	 * @return array
	 */
	public function serve_batch_request_v1( $batch ) {
		$body     = $batch->get_param( 'requests' );
		$requests = array();
		$matches  = array();

		foreach ( (array) $body as $sub ) {
			$parsed = wp_parse_url( isset( $sub['path'] ) ? $sub['path'] : '' );

			if ( false === $parsed || ! isset( $parsed['path'] ) ) {
				// Skip sub-requests we can't parse a route from.
				continue;
			}

			$single = new WP_REST_Request(
				isset( $sub['method'] ) ? $sub['method'] : 'GET',
				$parsed['path'],
				isset( $sub['params'] ) ? $sub['params'] : array()
			);

			// Collect the request and the handler it matched.
			$requests[] = $single;
			$matches[]  = $this->match_request_to_handler( $single );
		}

		$responses = array();
		foreach ( $body as $i => $sub ) {
			if ( ! isset( $requests[ $i ] ) ) {
				continue;
			}
			$single  = $requests[ $i ];
			$handler = $matches[ $i ];
			if ( is_wp_error( $handler ) ) {
				$responses[ $i ] = $handler;
				continue;
			}
			// Bind the handler, run its validators, then dispatch.
			$single->set_attributes( $handler );
			$single->sanitize_params();
			$responses[ $i ] = $this->dispatch( $single, $handler );
		}

		return $responses;
	}

	/**
	 * Top-level entry: route an inbound HTTP request.
	 *
	 * @param string $method
	 * @param string $route
	 * @param array  $params
	 * @return mixed
	 */
	public function serve_request( $method, $route, $params ) {
		if ( '/batch/v1' === $route ) {
			$batch = new WP_REST_Request( $method, $route, $params );
			return $this->serve_batch_request_v1( $batch );
		}

		$request = new WP_REST_Request( $method, $route, $params );
		$handler = $this->match_request_to_handler( $request );
		if ( is_wp_error( $handler ) ) {
			return $handler;
		}
		return $this->dispatch( $request, $handler );
	}
}
