<?php
/**
 * Minimal $wpdb — the database access layer.
 *
 * get_results() runs a SQL string and returns rows; prepare() interpolates
 * bound values using %d / %s / %f placeholders. Mirrors the parts of core
 * $wpdb this slice relies on.
 *
 * @package mini-wp-rest
 */

class wpdb {
	/** @var string Fully-qualified posts table name, e.g. "wp_posts". */
	public $posts = 'wp_posts';

	/** @var string Fully-qualified users table name. */
	public $users = 'wp_users';

	/** @var string Table prefix. */
	public $prefix = 'wp_';

	/** @var mysqli|null */
	private $dbh;

	public function __construct( $host = 'localhost', $user = 'wp', $pass = 'wp', $name = 'wp' ) {
		// Connection is lazy / best-effort; this lab does not require a live DB
		// to be scanned, only to model the data flow.
		$this->dbh = @mysqli_connect( $host, $user, $pass, $name );
	}

	/**
	 * Prepare a SQL query, binding %d / %s / %f placeholders to the given args.
	 *
	 * @param string $query Query with %d / %s / %f placeholders.
	 * @param mixed  ...$args Values to bind.
	 * @return string
	 */
	public function prepare( $query, ...$args ) {
		// Simplified: real core escapes each arg by placeholder type.
		$escaped = array_map(
			function ( $a ) {
				if ( is_int( $a ) ) {
					return (int) $a;
				}
				return "'" . mysqli_real_escape_string( $this->dbh, (string) $a ) . "'";
			},
			$args
		);
		$query = str_replace( array( '%d', '%s', '%f' ), '%s', $query );
		return vsprintf( $query, $escaped );
	}

	/**
	 * Execute a SELECT and return all rows.
	 *
	 * @param string $query SQL to run.
	 * @return array
	 */
	public function get_results( $query ) {
		if ( ! $this->dbh ) {
			// No database configured — return the query that would have run so
			// callers/tests can inspect the generated SQL.
			return array( '__executed_sql' => $query );
		}
		$res  = mysqli_query( $this->dbh, $query );
		$rows = array();
		if ( $res ) {
			while ( $row = mysqli_fetch_object( $res ) ) {
				$rows[] = $row;
			}
		}
		return $rows;
	}
}

$GLOBALS['wpdb'] = new wpdb();
