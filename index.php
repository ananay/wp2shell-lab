<?php
/**
 * Front controller — the unauthenticated HTTP entry point.
 *
 * Models WordPress's /wp-json REST bootstrap: an anonymous request comes in,
 * gets routed by WP_REST_Server, and (for the posts collection) reaches
 * WP_Query. No authentication is required for GET /wp/v2/posts or POST
 * /batch/v1 — both are public.
 *
 * Example requests:
 *   GET  /wp-json/wp/v2/posts?author_exclude[]=3
 *   POST /wp-json/batch/v1   {"requests":[...]}
 *
 * @package mini-wp-rest
 */

require __DIR__ . '/wp-includes/functions.php';
require __DIR__ . '/wp-includes/class-wpdb.php';
require __DIR__ . '/wp-includes/class-wp-query.php';
require __DIR__ . '/wp-includes/rest-api/class-wp-rest-request.php';
require __DIR__ . '/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php';
require __DIR__ . '/wp-includes/rest-api/class-wp-rest-server.php';

header( 'Content-Type: application/json' );

// Strip the /wp-json prefix to get the REST route.
$path  = parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ) ?: '/';
$route = preg_replace( '#^/wp-json#', '', $path );
if ( '' === $route ) {
	$route = '/';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Collect params from the query string and JSON/form body — straight from the
// wire, untrusted, no auth in front of this.
$params = $_GET;
$raw    = file_get_contents( 'php://input' );
if ( '' !== $raw ) {
	$json = json_decode( $raw, true );
	if ( is_array( $json ) ) {
		$params = array_merge( $params, $json );
	} else {
		$params = array_merge( $params, $_POST );
	}
}

$server   = new WP_REST_Server();
$response = $server->serve_request( $method, $route, $params );

echo json_encode( array( 'route' => $route, 'data' => $response ) );
