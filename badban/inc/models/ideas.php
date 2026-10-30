<?php
/**
 * Idea bank. One row per idea; (source, source_ref) is unique, so re-importing
 * from Sokan or Ghotbnama never duplicates an idea that was already used or
 * dismissed.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

/**
 * Idea sources: key => [label, icon].
 */
function bdb_idea_sources() {
	return array(
		'sokan'     => array( 'سکان', 'target' ),
		'ghotbnama' => array( 'قطب‌نما', 'compass' ),
		'fanus'     => array( 'فانوس', 'idea' ),
		'library'   => array( 'کتابخانه بادبان', 'layers' ),
		'ai'        => array( 'هوش مصنوعی', 'spark' ),
		'recycle'   => array( 'بازیافت پست موفق', 'refresh' ),
		'manual'    => array( 'دستی', 'pen' ),
	);
}

/**
 * Hydrate a row.
 */
function bdb_idea_row( $row ) {
	if ( ! $row ) {
		return null;
	}
	$i            = (array) $row;
	$i['id']      = (int) $i['id'];
	$i['score']   = (int) $i['score'];
	$i['payload'] = json_decode( (string) $i['payload'], true ) ?: array();
	return $i;
}

/**
 * One idea.
 */
function bdb_idea( $id ) {
	global $wpdb;
	return bdb_idea_row( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . bdb_table( 'ideas' ) . ' WHERE id = %d', (int) $id ) ) ); // phpcs:ignore
}

/**
 * Ideas by filter.
 */
function bdb_ideas( $args = array() ) {
	global $wpdb;
	$args  = wp_parse_args( $args, array( 'status' => 'new', 'source' => '', 'pillar' => '', 'limit' => 300 ) );
	$where = array( '1=1' );
	if ( $args['status'] ) {
		$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
	}
	if ( $args['source'] ) {
		$where[] = $wpdb->prepare( 'source = %s', $args['source'] );
	}
	if ( $args['pillar'] ) {
		$where[] = $wpdb->prepare( 'pillar = %s', $args['pillar'] );
	}
	$sql = 'SELECT * FROM ' . bdb_table( 'ideas' ) . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY score DESC, id DESC' . $wpdb->prepare( ' LIMIT %d', (int) $args['limit'] );
	return array_map( 'bdb_idea_row', (array) $wpdb->get_results( $sql ) ); // phpcs:ignore
}

/**
 * Count of new ideas by source.
 */
function bdb_idea_counts() {
	global $wpdb;
	$out = array();
	foreach ( (array) $wpdb->get_results( "SELECT source, COUNT(*) n FROM " . bdb_table( 'ideas' ) . " WHERE status = 'new' GROUP BY source" ) as $r ) { // phpcs:ignore
		$out[ $r->source ] = (int) $r->n;
	}
	return $out;
}

/**
 * Add an idea unless the same (source, ref) exists. Returns the id, or 0 when it existed.
 */
function bdb_idea_add( $data ) {
	global $wpdb;
	$source = sanitize_key( $data['source'] ?? 'manual' );
	$ref    = mb_substr( (string) ( $data['source_ref'] ?? '' ), 0, 120 );
	if ( '' === $ref ) {
		$ref = 'h:' . substr( md5( bdb_key( (string) ( $data['title'] ?? '' ) ) ), 0, 16 );
	}
	$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . bdb_table( 'ideas' ) . ' WHERE source = %s AND source_ref = %s', $source, $ref ) ); // phpcs:ignore
	if ( $exists ) {
		return 0;
	}
	$wpdb->insert( bdb_table( 'ideas' ), array(
		'title'      => mb_substr( wp_strip_all_tags( (string) ( $data['title'] ?? '' ) ), 0, 250 ),
		'angle'      => (string) ( $data['angle'] ?? '' ),
		'pillar'     => sanitize_key( $data['pillar'] ?? '' ),
		'source'     => $source,
		'source_ref' => $ref,
		'payload'    => wp_json_encode( $data['payload'] ?? array(), JSON_UNESCAPED_UNICODE ),
		'score'      => max( 0, min( 100, (int) ( $data['score'] ?? 50 ) ) ),
		'status'     => 'new',
		'created_at' => bdb_now_utc(),
	) );
	return (int) $wpdb->insert_id;
}

/**
 * Change an idea's status (new|used|dismissed).
 */
function bdb_idea_status( $id, $status ) {
	global $wpdb;
	$wpdb->update( bdb_table( 'ideas' ), array( 'status' => sanitize_key( $status ) ), array( 'id' => (int) $id ) );
}

/**
 * Delete an idea.
 */
function bdb_idea_delete( $id ) {
	global $wpdb;
	$wpdb->delete( bdb_table( 'ideas' ), array( 'id' => (int) $id ) );
}
