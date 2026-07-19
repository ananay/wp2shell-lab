<?php
/**
 * WP_Query — the post query builder. Slimmed to the author-filter code path
 * used by the REST posts collection.
 *
 * @package mini-wp-rest
 */

class WP_Query {
	/** @var array Parsed query vars. */
	public $query_vars = array();

	/** @var string The assembled SQL SELECT statement. */
	public $request = '';

	/** @var array Result rows. */
	public $posts = array();

	public function __construct( $query = array() ) {
		if ( ! empty( $query ) ) {
			$this->query( $query );
		}
	}

	/**
	 * Normalize incoming query vars, applying core defaults.
	 *
	 * @param array $query Raw query vars (may come straight from REST args).
	 */
	public function parse_query( $query ) {
		$defaults = array(
			'author__in'     => array(),
			'author__not_in' => array(),
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
		);
		$this->query_vars = array_merge( $defaults, $query );
	}

	/**
	 * Run the query.
	 *
	 * @param array $query Query vars.
	 * @return array Posts.
	 */
	public function query( $query ) {
		$this->parse_query( $query );
		return $this->get_posts();
	}

	/**
	 * Build and execute the SQL for the current query vars.
	 *
	 * @return array
	 */
	public function get_posts() {
		global $wpdb;

		$q     = $this->query_vars;
		$where = '';

		// --- Author filters -------------------------------------------------
		//
		// author__in / author__not_in accept either an array of author IDs or
		// a scalar list. Both are normalized to a list of non-negative integers
		// via wp_parse_id_list() before reaching SQL, regardless of input type.

		if ( ! empty( $q['author__in'] ) ) {
			$author__in = implode( ',', wp_parse_id_list( $q['author__in'] ) );
			if ( '' !== $author__in ) {
				$where .= " AND {$wpdb->posts}.post_author IN ($author__in)";
			}
		}

		if ( ! empty( $q['author__not_in'] ) ) {
			$author__not_in = implode( ',', wp_parse_id_list( $q['author__not_in'] ) );
			if ( '' !== $author__not_in ) {
				$where .= " AND {$wpdb->posts}.post_author NOT IN ($author__not_in)";
			}
		}
		// --------------------------------------------------------------------

		$post_type   = $wpdb->prepare( '%s', $q['post_type'] );
		$post_status = $wpdb->prepare( '%s', $q['post_status'] );
		$limit       = absint( $q['posts_per_page'] );

		$this->request =
			"SELECT * FROM {$wpdb->posts} " .
			"WHERE 1=1 AND {$wpdb->posts}.post_type = $post_type " .
			"AND {$wpdb->posts}.post_status = $post_status" .
			$where .
			" ORDER BY {$wpdb->posts}.post_date DESC LIMIT $limit";

		$this->posts = $wpdb->get_results( $this->request );
		return $this->posts;
	}
}
