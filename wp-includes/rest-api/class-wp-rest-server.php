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

		$settings = new WP_REST_Settings_Controller();
		$this->routes['/wp/v2/settings'] = $settings->get_route_args();
	}

	/**
	 * Find the handler whose route + method match a request.
	 *
	 * @param WP_REST_Request $request
	 * @return array|WP_Error Handler attributes, or WP_Error if no match.
	 */
	public function match_request_to_handler( $request ) {
		$route = $request->get_route();
		if ( '' !== $route && isset( $this->routes[ $route ] ) ) {
			$handler = $this->routes[ $route ];
			if ( strtoupper( $handler['methods'] ) === $request->get_method() ) {
				return $handler;
			}
		}
		return new WP_Error( 'rest_no_route', 'No route was found matching the URL and request method.' );
	}

	/**
	 * Bind a handler to a request and run its permission + argument validators.
	 *
	 * @param WP_REST_Request $request
	 * @param array           $handler
	 * @return true|WP_Error
	 */
	protected function validate_request( $request, $handler ) {
		$request->set_attributes( $handler );
		if ( isset( $handler['permission_callback'] ) ) {
			$allowed = call_user_func( $handler['permission_callback'], $request );
			if ( is_wp_error( $allowed ) || false === $allowed ) {
				return new WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to do that.' );
			}
		}
		$request->sanitize_params();
		return true;
	}

	/**
	 * Resolve a request's route and run its handler callback. Assumes the
	 * request's params were already validated by the caller.
	 *
	 * @param WP_REST_Request $request
	 * @return mixed
	 */
	public function dispatch( $request ) {
		$handler = $this->match_request_to_handler( $request );
		if ( is_wp_error( $handler ) ) {
			return $handler;
		}
		if ( isset( $handler['permission_callback'] ) ) {
			$allowed = call_user_func( $handler['permission_callback'], $request );
			if ( is_wp_error( $allowed ) || false === $allowed ) {
				return new WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to do that.' );
			}
		}
		return call_user_func( $handler['callback'], $request );
	}

	/**
	 * POST /batch/v1 — run many sub-requests in one round trip.
	 *
	 * Each sub-request keeps its index in both the request and match arrays —
	 * including entries with no matchable route, which get a WP_Error in the
	 * same slot — so a request is always validated against the handler it
	 * matched, then dispatched.
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
			$route  = ( false === $parsed || ! isset( $parsed['path'] ) ) ? '' : $parsed['path'];

			$single = new WP_REST_Request(
				isset( $sub['method'] ) ? $sub['method'] : 'GET',
				$route,
				isset( $sub['params'] ) ? $sub['params'] : array()
			);
			$requests[ $i ] = $single;

			if ( '' === $route ) {
				// Keep the slot aligned with the request array.
				$matches[ $i ] = new WP_Error( 'rest_invalid_url', 'Invalid URL.' );
				continue;
			}
			$matches[ $i ] = $this->match_request_to_handler( $single );
		}

		$responses = array();
		foreach ( $requests as $i => $single ) {
			$handler = $matches[ $i ];

			// Validate the sub-request against its matched handler, then run it.
			if ( is_array( $handler ) ) {
				$this->validate_request( $single, $handler );
			}
			$responses[ $i ] = $this->dispatch( $single );
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
		$valid = $this->validate_request( $request, $handler );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		return call_user_func( $handler['callback'], $request );
	}
}
