# wp2shell-lab

A **minimal, self-contained slice of WordPress core** reproducing the code paths
behind the `wp2shell` pre-auth RCE chain (WordPress 6.9.0–7.0.1), fixed in
WordPress **7.0.2** / **6.9.5**.

The chain is two CVEs:

| CVE | Component | Bug |
|-----|-----------|-----|
| **CVE-2026-60137** | `WP_Query` `author__not_in` handling | String values bypass the `is_array()` sanitization gate and are concatenated raw into the SQL `WHERE` clause → **unauthenticated SQL injection**. |
| **CVE-2026-63030** | `WP_REST_Server::serve_batch_request_v1()` | An index desync between the parsed-request array and the matched-handler array lets a sub-request be **validated against one handler but executed against another** (route confusion), letting an unvalidated `author_exclude` string reach `WP_Query`. |

Chained: an anonymous `POST /wp-json/batch/v1` seeds a parse-error request to
shift indices, then a `GET /wp/v2/posts?author_exclude=<sql>` is dispatched with
the wrong validation schema, so the raw string reaches the `author__not_in`
SQL sink → SQL injection → RCE on a default install.

## Layout

```
index.php                                          front controller (unauth HTTP entry)
wp-includes/
  functions.php                                    absint / wp_parse_id_list / wp_parse_url
  class-wpdb.php                                    $wpdb->get_results() sink
  class-wp-query.php                                author__not_in WHERE builder
  rest-api/
    class-wp-rest-request.php
    class-wp-rest-server.php                        batch endpoint /batch/v1
    endpoints/class-wp-rest-posts-controller.php    registers /wp/v2/posts
```

## Purpose

This repo is a validation target: run a scanner against it and check whether it
flags the `author__not_in` SQL injection and the batch route-confusion.

`main` is the **patched 7.0.2** state. See the open PR for the vulnerable
(pre-patch 6.9) state.
