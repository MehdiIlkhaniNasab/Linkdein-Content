<?php
/**
 * Posts: every LinkedIn post Badban writes, schedules and publishes.
 *
 * Times are stored in UTC (scheduled_at, published_at) and shown in the
 * timezone of the post's audience (English → audience_tz, Persian → Tehran).
 *
 * meta (JSON): hook alternatives, quality checks, poll, framework, source of the
 *              idea, AI notes, the LinkedIn URL…
 * metrics (JSON): impressions, reactions, comments, reposts, saves, clicks,
 *              followers, leads (+ updated_at), from the analytics import or by hand.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hydrate a row.
 */
function bdb_post_row( $row ) {
	if ( ! $row ) {
		return null;
	}
	$p                 = (array) $row;
	$p['id']           = (int) $p['id'];
	$p['pair_id']      = (int) $p['pair_id'];
	$p['idea_id']      = (int) $p['idea_id'];
	$p['slides']       = json_decode( (string) $p['slides'], true ) ?: array();
	$p['meta']         = json_decode( (string) $p['meta'], true ) ?: array();
	$p['metrics']      = json_decode( (string) $p['metrics'], true ) ?: array();
	$p['body']         = (string) $p['body'];
	$p['first_comment'] = (string) $p['first_comment'];
	return $p;
}

/**
 * One post.
 */
function bdb_post( $id ) {
	global $wpdb;
	return bdb_post_row( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . bdb_table( 'posts' ) . ' WHERE id = %d', (int) $id ) ) ); // phpcs:ignore
}

/**
 * Posts by filter.
 *
 * @param array $args status (string|array), lang, pillar, format, from, to (UTC, on scheduled_at or published_at), search, order, limit, offset.
 */
function bdb_posts( $args = array() ) {
	global $wpdb;
	$args  = wp_parse_args( $args, array( 'status' => '', 'lang' => '', 'pillar' => '', 'format' => '', 'from' => '', 'to' => '', 'search' => '', 'order' => 'recent', 'limit' => 200, 'offset' => 0 ) );
	$where = array( '1=1' );
	if ( $args['status'] ) {
		$st      = (array) $args['status'];
		$where[] = "status IN ('" . implode( "','", array_map( 'esc_sql', $st ) ) . "')";
	}
	foreach ( array( 'lang', 'pillar', 'format' ) as $k ) {
		if ( '' !== (string) $args[ $k ] ) {
			$where[] = $wpdb->prepare( "$k = %s", $args[ $k ] ); // phpcs:ignore
		}
	}
	$time = 'COALESCE(published_at, scheduled_at, updated_at)';
	if ( $args['from'] ) {
		$where[] = $wpdb->prepare( "$time >= %s", $args['from'] ); // phpcs:ignore
	}
	if ( $args['to'] ) {
		$where[] = $wpdb->prepare( "$time <= %s", $args['to'] ); // phpcs:ignore
	}
	if ( '' !== (string) $args['search'] ) {
		$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		$where[] = $wpdb->prepare( '(title LIKE %s OR body LIKE %s)', $like, $like );
	}
	$order = array(
		'recent'   => 'updated_at DESC',
		'schedule' => 'scheduled_at ASC, id ASC',
		'time'     => "$time DESC",
	)[ $args['order'] ] ?? 'updated_at DESC';
	$sql = 'SELECT * FROM ' . bdb_table( 'posts' ) . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY ' . $order . $wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $args['limit'], (int) $args['offset'] );
	return array_map( 'bdb_post_row', (array) $wpdb->get_results( $sql ) ); // phpcs:ignore
}

/**
 * Count of posts by status.
 */
function bdb_post_counts() {
	global $wpdb;
	$out = array();
	foreach ( (array) $wpdb->get_results( 'SELECT status, COUNT(*) n FROM ' . bdb_table( 'posts' ) . ' GROUP BY status' ) as $r ) { // phpcs:ignore
		$out[ $r->status ] = (int) $r->n;
	}
	return $out;
}

/**
 * Insert or update a post. Returns the id.
 */
function bdb_post_save( $data, $id = 0 ) {
	global $wpdb;
	$cols = array( 'lang', 'format', 'pillar', 'status', 'title', 'body', 'first_comment', 'hashtags', 'hook_type', 'cta_type', 'slides', 'meta', 'asset', 'scheduled_at', 'published_at', 'li_urn', 'error', 'pair_id', 'idea_id', 'metrics' );
	$row  = array();
	foreach ( $cols as $c ) {
		if ( array_key_exists( $c, $data ) ) {
			$v         = $data[ $c ];
			$row[ $c ] = in_array( $c, array( 'slides', 'meta', 'metrics' ), true ) ? wp_json_encode( $v ?: array(), JSON_UNESCAPED_UNICODE ) : $v;
		}
	}
	if ( isset( $row['title'] ) ) {
		$row['title'] = mb_substr( wp_strip_all_tags( (string) $row['title'] ), 0, 250 );
	}
	$row['updated_at'] = bdb_now_utc();
	if ( $id ) {
		$wpdb->update( bdb_table( 'posts' ), $row, array( 'id' => (int) $id ) );
		return (int) $id;
	}
	$row['created_at'] = $row['updated_at'];
	$wpdb->insert( bdb_table( 'posts' ), $row );
	return (int) $wpdb->insert_id;
}

/**
 * Merge keys into a post's meta.
 */
function bdb_post_meta_merge( $id, $changes ) {
	$p = bdb_post( $id );
	if ( $p ) {
		bdb_post_save( array( 'meta' => array_merge( $p['meta'], $changes ) ), $id );
	}
}

/**
 * Delete a post (and its rendered file).
 */
function bdb_post_delete( $id ) {
	global $wpdb;
	$p = bdb_post( $id );
	if ( $p && $p['asset'] ) {
		$file = bdb_asset_path( $p['asset'] );
		if ( $file && file_exists( $file ) ) {
			wp_delete_file( $file );
		}
	}
	$wpdb->delete( bdb_table( 'posts' ), array( 'id' => (int) $id ) );
	$wpdb->update( bdb_table( 'posts' ), array( 'pair_id' => 0 ), array( 'pair_id' => (int) $id ) );
}

/**
 * Current UTC time in MySQL format.
 */
function bdb_now_utc() {
	return gmdate( 'Y-m-d H:i:s' );
}

/**
 * UTC MySQL datetime → DateTime in the audience timezone of a language.
 */
function bdb_local( $utc, $lang = 'en' ) {
	if ( ! $utc || 0 === strpos( (string) $utc, '0000' ) ) {
		return null;
	}
	$d = new DateTime( $utc, new DateTimeZone( 'UTC' ) );
	return $d->setTimezone( bdb_tz( $lang ) );
}

/**
 * Local datetime string (Y-m-d H:i in the language's timezone) → UTC MySQL.
 */
function bdb_to_utc( $local, $lang = 'en' ) {
	$local = trim( (string) $local );
	if ( '' === $local ) {
		return null;
	}
	try {
		$d = new DateTime( str_replace( 'T', ' ', $local ), bdb_tz( $lang ) );
	} catch ( Exception $e ) {
		return null;
	}
	return $d->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
}

/**
 * Human date of a post time: English posts in Gregorian + audience time, Persian in Jalali.
 */
function bdb_when( $utc, $lang = 'en', $with_tehran = true ) {
	$d = bdb_local( $utc, $lang );
	if ( ! $d ) {
		return '—';
	}
	if ( 'fa' === $lang ) {
		return bdb_jweekday( $d->format( 'Y-m-d' ) ) . ' ' . bdb_jdate( $d->format( 'Y-m-d' ), 'day' ) . ' · ' . bdb_fa( $d->format( 'H:i' ) );
	}
	$out = $d->format( 'D j M · H:i' ) . ' ' . $d->format( 'T' );
	if ( $with_tehran ) {
		$t    = ( clone $d )->setTimezone( new DateTimeZone( 'Asia/Tehran' ) );
		$out .= ' (تهران ' . bdb_fa( $t->format( 'H:i' ) ) . ')';
	}
	return $out;
}

/**
 * Engagement count of a post (reactions + comments + reposts + saves + clicks).
 */
function bdb_post_engagement( $p ) {
	$m = $p['metrics'];
	return (int) ( $m['reactions'] ?? 0 ) + (int) ( $m['comments'] ?? 0 ) + (int) ( $m['reposts'] ?? 0 ) + (int) ( $m['saves'] ?? 0 ) + (int) ( $m['clicks'] ?? 0 );
}

/**
 * Engagement rate (0–1) of a post, or null without impressions.
 */
function bdb_post_er( $p ) {
	$imp = (int) ( $p['metrics']['impressions'] ?? 0 );
	return $imp > 0 ? bdb_post_engagement( $p ) / $imp : null;
}

/**
 * Public URL of a published post.
 */
function bdb_post_url( $p ) {
	if ( ! empty( $p['meta']['url'] ) ) {
		return (string) $p['meta']['url'];
	}
	return '' !== (string) $p['li_urn'] ? 'https://www.linkedin.com/feed/update/' . $p['li_urn'] . '/' : '';
}

/**
 * Where rendered files (PDF / PNG) are kept: uploads/badban/ (not listed, random names).
 */
function bdb_asset_dir() {
	$u   = wp_upload_dir();
	$dir = trailingslashit( $u['basedir'] ) . 'badban';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	return $dir;
}

/**
 * Absolute path of an asset name.
 */
function bdb_asset_path( $name ) {
	$name = basename( (string) $name );
	return '' === $name ? '' : bdb_asset_dir() . '/' . $name;
}

/**
 * URL of an asset name.
 */
function bdb_asset_url( $name ) {
	$u = wp_upload_dir();
	return trailingslashit( $u['baseurl'] ) . 'badban/' . rawurlencode( basename( (string) $name ) );
}
