# mini-wp-rest

A compact, dependency-free reimplementation of a small slice of WordPress's
REST API — just enough to serve a public posts collection and run several
sub-requests in one round trip. Handy for teaching how WordPress routing,
`WP_Query`, and `$wpdb` fit together without pulling in all of core.

## Endpoints

| Method | Route | Purpose |
|--------|-------|---------|
| `GET`  | `/wp-json/wp/v2/posts` | List posts. Supports `author_exclude`, `per_page`. |
| `POST` | `/wp-json/batch/v1`    | Run multiple sub-requests in one request. |

Both routes are public (no authentication), matching the defaults for the core
posts collection.

## Layout

```
index.php                                          front controller / router
wp-includes/
  functions.php                                    absint / wp_parse_id_list / wp_parse_url
  class-wpdb.php                                    thin $wpdb (prepare / get_results)
  class-wp-query.php                               post query builder
  rest-api/
    class-wp-rest-request.php
    class-wp-rest-server.php                        routing + /batch/v1
    endpoints/class-wp-rest-posts-controller.php    /wp/v2/posts
```

## Running

```
php -S 127.0.0.1:8080
curl 'http://127.0.0.1:8080/wp-json/wp/v2/posts?author_exclude[]=3'
```

Requires PHP ≥ 8.0. A MySQL database is optional — without one, `$wpdb`
returns the SQL it would have run so you can see the generated queries.

<!-- recovery verify -->
