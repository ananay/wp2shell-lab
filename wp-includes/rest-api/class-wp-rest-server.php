<?php
/**
 * WP_REST_Server — routing + the /batch/v1 endpoint that carried the
 * CVE-2026-63030 route-confusion bug.
 *
 * @package wp2shell-lab
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
	 * PATCHED (7.0.2): every sub-request contributes exactly one entry to
	 * $matches, INCLUDING parse failures (stored as WP_Error). Because $matches
	 * and $requests stay index-aligned, dispatch always pairs a request with
	 * the handler it was actually validated against — no route confusion.
	 *
	 * @param WP_REST_Request $batch
	 * @return array
	 */
	public function serve_batch_request_v1( $batch ) {
		$body     = $batch->get_param( 'requests' );
		$requests = array();
		$matches  = array();

		foreach ( (array) $body as $i => $sub ) {
			$parsed = wp_parse_url( isset( $sub['path'] ) ? $sub['path'] : '' );

			if ( false === $parsed || ! isset( $parsed['path'] ) ) {
				// Keep the slot: a failed parse still occupies index $i in BOTH
				// arrays, so nothing downstream shifts.
				$requests[ $i ] = null;
				$matches[ $i ]  = new WP_Error( 'rest_invalid_url', 'Invalid URL.' );
				continue;
			}

			$single = new WP_REST_Request(
				isset( $sub['method'] ) ? $sub['method'] : 'GET',
				$parsed['path'],
				isset( $sub['params'] ) ? $sub['params'] : array()
			);

			$requests[ $i ] = $single;
			$matches[ $i ]  = $this->match_request_to_handler( $single );
		}

		$responses = array();
		foreach ( $requests as $i => $single ) {
			$handler = $matches[ $i ];
			if ( null === $single || is_wp_error( $handler ) ) {
				$responses[ $i ] = $handler; // propagate the per-slot error
				continue;
			}
			// $handler is guaranteed to be the one $single matched.
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
