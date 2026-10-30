<?php
/**
 * Database tables, capability and cron.
 *
 *   bdb_posts  every post: draft → ready → scheduled → published (or failed), with
 *              its slides, first comment, LinkedIn URN and measured metrics.
 *   bdb_ideas  the idea bank: what to write about, and where the idea came from
 *              (Sokan, Ghotbnama, Fanus, the library, the AI or by hand).
 *   bdb_stats  daily account series from LinkedIn's analytics export
 *              (impressions, engagements, followers).
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

/**
 * Full table name.
 */
function bdb_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'bdb_' . $name;
}

/**
 * Create / upgrade tables.
 */
function bdb_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();
	dbDelta( 'CREATE TABLE ' . bdb_table( 'posts' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		lang varchar(5) NOT NULL DEFAULT 'en',
		format varchar(20) NOT NULL DEFAULT 'text',
		pillar varchar(40) NOT NULL DEFAULT '',
		status varchar(20) NOT NULL DEFAULT 'draft',
		title varchar(255) NOT NULL DEFAULT '',
		body longtext NULL,
		first_comment text NULL,
		hashtags varchar(255) NOT NULL DEFAULT '',
		hook_type varchar(30) NOT NULL DEFAULT '',
		cta_type varchar(30) NOT NULL DEFAULT '',
		slides longtext NULL,
		meta longtext NULL,
		asset varchar(255) NOT NULL DEFAULT '',
		scheduled_at datetime NULL,
		published_at datetime NULL,
		li_urn varchar(120) NOT NULL DEFAULT '',
		error text NULL,
		pair_id bigint(20) unsigned NOT NULL DEFAULT 0,
		idea_id bigint(20) unsigned NOT NULL DEFAULT 0,
		metrics longtext NULL,
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		KEY status_time (status,scheduled_at),
		KEY lang (lang)
	) $c;" );
	dbDelta( 'CREATE TABLE ' . bdb_table( 'ideas' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		title varchar(255) NOT NULL DEFAULT '',
		angle text NULL,
		pillar varchar(40) NOT NULL DEFAULT '',
		source varchar(20) NOT NULL DEFAULT 'manual',
		source_ref varchar(120) NOT NULL DEFAULT '',
		payload longtext NULL,
		score smallint(6) NOT NULL DEFAULT 50,
		status varchar(20) NOT NULL DEFAULT 'new',
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		UNIQUE KEY source_ref (source,source_ref),
		KEY status (status)
	) $c;" );
	dbDelta( 'CREATE TABLE ' . bdb_table( 'stats' ) . " (
		day date NOT NULL,
		impressions int(11) NOT NULL DEFAULT 0,
		engagements int(11) NOT NULL DEFAULT 0,
		new_followers int(11) NOT NULL DEFAULT 0,
		followers int(11) NOT NULL DEFAULT 0,
		PRIMARY KEY  (day)
	) $c;" );
	update_option( 'bdb_db_version', BDB_DB_VERSION, false );
	delete_option( 'bdb_rewrite_version' );
	bdb_schedule_cron();
}

/**
 * Deactivation: stop the publisher.
 */
function bdb_deactivate() {
	wp_clear_scheduled_hook( 'bdb_cron' );
	delete_option( 'bdb_rewrite_version' );
}

/**
 * The publisher runs every five minutes.
 */
function bdb_schedule_cron() {
	if ( ! wp_next_scheduled( 'bdb_cron' ) ) {
		wp_schedule_event( time() + 60, 'bdb_5min', 'bdb_cron' );
	}
}

add_filter( 'cron_schedules', function ( $s ) {
	$s['bdb_5min'] = array( 'interval' => 5 * MINUTE_IN_SECONDS, 'display' => 'Badban — every 5 minutes' );
	return $s;
} );

add_action( 'plugins_loaded', function () {
	if ( get_option( 'bdb_db_version' ) !== BDB_DB_VERSION ) {
		bdb_install();
	}
} );

add_action( 'init', 'bdb_schedule_cron' );

/**
 * Administrators always have the tool capability.
 */
add_filter( 'user_has_cap', function ( $all ) {
	if ( ! empty( $all['manage_options'] ) ) {
		$all[ BDB_CAP ] = true;
	}
	return $all;
} );
