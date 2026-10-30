<?php
/**
 * Daily account series (LinkedIn analytics export, see inc/engine/analytics.php).
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

/**
 * Upsert one day. Only the fields given are written.
 */
function bdb_stat_put( $day, $fields ) {
	global $wpdb;
	$day = gmdate( 'Y-m-d', strtotime( $day ) );
	$row = array_intersect_key( $fields, array_flip( array( 'impressions', 'engagements', 'new_followers', 'followers' ) ) );
	if ( ! $row ) {
		return;
	}
	$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT day FROM ' . bdb_table( 'stats' ) . ' WHERE day = %s', $day ) ); // phpcs:ignore
	if ( $exists ) {
		$wpdb->update( bdb_table( 'stats' ), $row, array( 'day' => $day ) );
	} else {
		$wpdb->insert( bdb_table( 'stats' ), array_merge( array( 'day' => $day ), $row ) );
	}
}

/**
 * Days between two dates (inclusive), oldest first.
 */
function bdb_stats( $from, $to ) {
	global $wpdb;
	return array_map( function ( $r ) {
		$r = (array) $r;
		foreach ( array( 'impressions', 'engagements', 'new_followers', 'followers' ) as $k ) {
			$r[ $k ] = (int) $r[ $k ];
		}
		return $r;
	}, (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . bdb_table( 'stats' ) . ' WHERE day BETWEEN %s AND %s ORDER BY day ASC', $from, $to ) ) ); // phpcs:ignore
}

/**
 * Totals of a period: impressions, engagements, new followers, ER, last follower count.
 */
function bdb_stats_totals( $from, $to ) {
	$rows = bdb_stats( $from, $to );
	$t    = array( 'impressions' => 0, 'engagements' => 0, 'new_followers' => 0, 'followers' => 0, 'days' => count( $rows ), 'er' => null );
	foreach ( $rows as $r ) {
		$t['impressions']   += $r['impressions'];
		$t['engagements']   += $r['engagements'];
		$t['new_followers'] += $r['new_followers'];
		if ( $r['followers'] ) {
			$t['followers'] = $r['followers'];
		}
	}
	$t['er'] = $t['impressions'] ? $t['engagements'] / $t['impressions'] : null;
	return $t;
}

/**
 * Last day with data.
 */
function bdb_stats_last_day() {
	global $wpdb;
	return (string) $wpdb->get_var( 'SELECT MAX(day) FROM ' . bdb_table( 'stats' ) ); // phpcs:ignore
}
