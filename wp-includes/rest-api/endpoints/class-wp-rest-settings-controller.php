<?php
/**
 * WP_REST_Settings_Controller — /wp/v2/settings. A small public read endpoint
 * exposing a couple of site settings. Its argument schema is unrelated to the
 * posts collection's.
 *
 * @package mini-wp-rest
 */

class WP_REST_Settings_Controller {

	/**
	 * Argument schema for the settings endpoint.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'context' => array(
				'type'              => 'string',
				'default'           => 'view',
				'sanitize_callback' => 'strval',
			),
		);
	}

	public function get_route_args() {
		return array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_items' ),
			'permission_callback' => '__return_true',
			'args'                => $this->get_collection_params(),
		);
	}

	/**
	 * Handle GET /wp/v2/settings.
	 *
	 * @param WP_REST_Request $request
	 * @return array
	 */
	public function get_items( $request ) {
		return array(
			'title'       => 'mini-wp-rest',
			'description' => 'Just another mini-wp-rest site',
			'url'         => 'http://127.0.0.1:8080',
		);
	}
}
