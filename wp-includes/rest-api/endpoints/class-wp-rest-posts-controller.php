<?php
/**
 * WP_REST_Posts_Controller — /wp/v2/posts. Slimmed to the collection query and
 * the author_exclude argument that maps to WP_Query's author__not_in.
 *
 * @package wp2shell-lab
 */

class WP_REST_Posts_Controller {

	/**
	 * Argument schema for the collection endpoint. The REST layer is supposed
	 * to coerce author_exclude to an array of integers *before* it reaches
	 * WP_Query — this is the validation the route-confusion bug bypasses.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'author_exclude' => array(
				'description'       => 'Exclude posts by these author IDs.',
				'type'              => 'array',
				'items'             => array( 'type' => 'integer' ),
				'default'           => array(),
				'sanitize_callback' => 'wp_parse_id_list',
			),
			'per_page' => array(
				'type'              => 'integer',
				'default'           => 10,
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Route registration used by the server to match handlers.
	 *
	 * @return array
	 */
	public function get_route_args() {
		return array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_items' ),
			'permission_callback' => '__return_true', // public endpoint, unauthenticated
			'args'                => $this->get_collection_params(),
		);
	}

	/**
	 * Handle GET /wp/v2/posts.
	 *
	 * @param WP_REST_Request $request
	 * @return array
	 */
	public function get_items( $request ) {
		$args = array();

		// Map REST params onto WP_Query vars. author_exclude -> author__not_in.
		$author_exclude = $request->get_param( 'author_exclude' );
		if ( null !== $author_exclude ) {
			$args['author__not_in'] = $author_exclude;
		}

		$per_page = $request->get_param( 'per_page' );
		if ( null !== $per_page ) {
			$args['posts_per_page'] = $per_page;
		}

		$query = new WP_Query( $args );
		return $query->posts;
	}
}
