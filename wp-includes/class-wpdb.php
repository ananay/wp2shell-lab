<?php
/**
 * Minimal $wpdb reproduction — the SQL execution sink.
 *
 * Faithful to core in the ways that matter for this repro: get_results() runs
 * whatever SQL string it is handed, and prepare() is the *only* parameterizing
 * path. Anything concatenated into a query before it reaches get_results() is
 * unparameterized and injectable.
 *
 * @package wp2shell-lab
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
	 * Prepare a SQL query for safe execution. THIS is the parameterizing path.
	 * Queries that never pass through prepare() are unsanitized.
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
	 * Execute a SELECT and return all rows. Runs the SQL verbatim.
	 *
	 * @param string $query Raw SQL. Whatever reaches here is executed as-is.
	 * @return array
	 */
	public function get_results( $query ) {
		if ( ! $this->dbh ) {
			// No DB in the scan environment; echo the executed SQL so the sink
			// is observable. In production this line is `mysqli_query()`.
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
