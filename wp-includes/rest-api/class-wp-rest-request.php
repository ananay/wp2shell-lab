<?php
/**
 * Minimal WP_REST_Request — carries method, route and params through dispatch.
 *
 * @package mini-wp-rest
 */

class WP_REST_Request {
	protected $method;
	protected $route;
	protected $params;
	protected $attributes = array();

	public function __construct( $method = 'GET', $route = '', $params = array() ) {
		$this->method = strtoupper( $method );
		$this->route  = $route;
		$this->params = $params;
	}

	public function get_method() {
		return $this->method;
	}

	public function get_route() {
		return $this->route;
	}

	public function get_param( $key ) {
		return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
	}

	public function get_params() {
		return $this->params;
	}

	public function set_attributes( $attributes ) {
		$this->attributes = $attributes;
	}

	public function get_attributes() {
		return $this->attributes;
	}

	/**
	 * Run the registered argument validators/sanitizers for this request's
	 * matched handler. The arg set comes from whichever handler was bound via
	 * set_attributes().
	 *
	 * @return true|WP_Error
	 */
	public function sanitize_params() {
		$args = isset( $this->attributes['args'] ) ? $this->attributes['args'] : array();
		foreach ( $args as $key => $arg ) {
			if ( ! isset( $this->params[ $key ] ) ) {
				continue;
			}
			if ( isset( $arg['sanitize_callback'] ) && is_callable( $arg['sanitize_callback'] ) ) {
				$this->params[ $key ] = call_user_func( $arg['sanitize_callback'], $this->params[ $key ] );
			}
		}
		return true;
	}
}
