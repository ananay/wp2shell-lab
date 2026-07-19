<?php
/**
 * A tiny subset of WordPress core helper functions, faithful to core behavior.
 *
 * @package mini-wp-rest
 */

/**
 * Convert a value to a non-negative integer.
 *
 * @param mixed $maybeint Data to convert.
 * @return int
 */
function absint( $maybeint ) {
	return abs( (int) $maybeint );
}

/**
 * Clean up an array, comma- or space-separated list of scalar values.
 *
 * @param array|string $list List of values.
 * @return array Sanitized array of values.
 */
function wp_parse_list( $list ) {
	if ( ! is_array( $list ) ) {
		return preg_split( '/[\s,]+/', (string) $list, -1, PREG_SPLIT_NO_EMPTY );
	}

	return $list;
}

/**
 * Clean up an array, comma- or space-separated list of IDs.
 *
 * Every element is forced through absint(), so the return value is always a
 * list of non-negative integers regardless of input type.
 *
 * @param array|string $list List of IDs.
 * @return int[] Sanitized array of IDs.
 */
function wp_parse_id_list( $list ) {
	$list = wp_parse_list( $list );

	return array_unique( array_map( 'absint', $list ) );
}

/**
 * A permissive wrapper around PHP's parse_url().
 *
 * @param string $url       The URL to parse.
 * @param int    $component The specific component to retrieve.
 * @return mixed False on parse failure.
 */
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

/**
 * Minimal WP_Error stand-in.
 */
class WP_Error {
	public $code;
	public $message;
	public $data;

	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
