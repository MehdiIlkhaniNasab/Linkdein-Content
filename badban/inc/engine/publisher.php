<?php
/**
 * Publisher: WP-Cron (every 5 minutes) sends the scheduled posts that are due,
 * retries documents LinkedIn is still processing, and once a day warns about
 * the token and refreshes the idea bank from Sokan / Ghotbnama / Fanus.
 *
 * On an Iranian host WP-Cron only runs when someone visits the site; a real
 * cron job hitting wp-cron.php every 5 minutes makes the times exact (guide).
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

add_action( 'bdb_cron', 'bdb_cron_run' );

/**
 * One cron run.
 */
function bdb_cron_run() {
	update_option( 'bdb_cron_last', time(), false );
	if ( (int) bdb_setting( 'auto_publish', 1 ) ) {
		bdb_publish_due();
	}
	if ( (int) get_option( 'bdb_daily_last', 0 ) < time() - DAY_IN_SECONDS ) {
		update_option( 'bdb_daily_last', time(), false );
		bdb_daily();
	}
}

/**
 * Publish what is due (at most 3 per run; LinkedIn dislikes bursts).
 */
function bdb_publish_due() {
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare(
		'SELECT id FROM ' . bdb_table( 'posts' ) . " WHERE status IN ('scheduled','publishing') AND scheduled_at <= %s ORDER BY scheduled_at ASC LIMIT 3", // phpcs:ignore
		bdb_now_utc()
	) );
	foreach ( $ids as $id ) {
		bdb_publish( (int) $id, false );
	}
}

/**
 * Publish one post on LinkedIn now.
 *
 * @param bool $manual Started by the owner (errors are shown, not mailed).
 * @return true|WP_Error
 */
function bdb_publish( $id, $manual = true ) {
	global $wpdb;
	$p = bdb_post( $id );
	if ( ! $p ) {
		return new WP_Error( 'bdb_404', 'پست پیدا نشد.' );
	}
	if ( 'published' === $p['status'] ) {
		return new WP_Error( 'bdb_done', 'این پست قبلاً منتشر شده است.' );
	}
	// One publisher at a time per post (cron and a click can overlap).
	$lock = 'bdb_pub_lock_' . $id;
	if ( get_transient( $lock ) ) {
		return new WP_Error( 'bdb_busy', 'این پست در حال انتشار است.' );
	}
	set_transient( $lock, 1, 3 * MINUTE_IN_SECONDS );
	$wpdb->update( bdb_table( 'posts' ), array( 'status' => 'publishing', 'updated_at' => bdb_now_utc() ), array( 'id' => $id ) );
	$res = bdb_publish_do( $p );
	delete_transient( $lock );
	if ( 'pending' === $res ) {
		return true; // Document still processing: the next cron run finishes it.
	}
	if ( is_wp_error( $res ) ) {
		bdb_post_save( array( 'status' => 'failed', 'error' => $res->get_error_message() ), $id );
		bdb_log( 'error', sprintf( 'انتشار «%s» ناموفق بود: %s', $p['title'], $res->get_error_message() ) );
		if ( ! $manual ) {
			bdb_notify( 'انتشار پست لینکدین ناموفق بود', sprintf( "پست «%s» منتشر نشد.\n\nعلت: %s\n\n%s", $p['title'], $res->get_error_message(), bdb_url( 'studio/' . $id ) ) );
		}
		return $res;
	}
	return true;
}

/**
 * The publishing steps. Returns true, 'pending' (document processing) or WP_Error.
 */
function bdb_publish_do( $p ) {
	if ( ! bdb_li_connected() ) {
		return new WP_Error( 'bdb_li', 'لینکدین وصل نیست یا توکن منقضی شده؛ در تنظیمات دوباره متصل شوید.' );
	}
	$q = bdb_quality( $p );
	if ( $q['blocking'] ) {
		$errs = array_filter( $q['checks'], function ( $c ) {
			return 'error' === $c['level'];
		} );
		return new WP_Error( 'bdb_quality', 'بررسی کیفیت اجازه انتشار نداد: ' . implode( '؛ ', array_map( function ( $c ) {
			return $c['label'] . ( $c['msg'] ? ' (' . $c['msg'] . ')' : '' );
		}, $errs ) ) );
	}
	$meta    = $p['meta'];
	$content = null;
	if ( in_array( $p['format'], array( 'carousel', 'image' ), true ) ) {
		$kind = 'carousel' === $p['format'] ? 'documents' : 'images';
		$urn  = (string) ( $meta['li_media'] ?? '' );
		if ( '' === $urn || ( $meta['li_media_hash'] ?? '' ) !== ( $meta['asset_hash'] ?? '' ) ) {
			$urn = bdb_li_upload( $kind, bdb_asset_path( $p['asset'] ) );
			if ( is_wp_error( $urn ) ) {
				return $urn;
			}
			bdb_post_meta_merge( $p['id'], array( 'li_media' => $urn, 'li_media_hash' => $meta['asset_hash'] ?? '', 'li_media_at' => time() ) );
			$meta['li_media_at'] = time();
		}
		$status = '';
		for ( $i = 0; $i < 4; $i++ ) {
			$status = bdb_li_media_status( $urn );
			if ( 'AVAILABLE' === $status || 'PROCESSING_FAILED' === $status ) {
				break;
			}
			sleep( 3 );
		}
		if ( 'PROCESSING_FAILED' === $status ) {
			bdb_post_meta_merge( $p['id'], array( 'li_media' => '' ) );
			return new WP_Error( 'bdb_li_media', 'لینکدین پردازش فایل را رد کرد؛ فایل را دوباره بسازید و منتشر کنید.' );
		}
		if ( 'AVAILABLE' !== $status && time() - (int) ( $meta['li_media_at'] ?? time() ) < 20 * MINUTE_IN_SECONDS ) {
			bdb_post_save( array( 'status' => 'publishing', 'error' => 'لینکدین هنوز فایل را پردازش می‌کند؛ اجرای بعدی (حداکثر ۵ دقیقه) منتشر می‌کند.' ), $p['id'] );
			return 'pending';
		}
		$content = array( 'media' => 'carousel' === $p['format']
			? array( 'title' => $meta['doc_title'] ?: $p['title'], 'id' => $urn )
			: array( 'id' => $urn, 'altText' => mb_substr( (string) ( $p['slides'][0]['title'] ?? $p['title'] ), 0, 300 ) ) );
	} elseif ( 'poll' === $p['format'] ) {
		$poll    = (array) $meta['poll'];
		$content = array( 'poll' => array(
			'question' => (string) $poll['question'],
			'options'  => array_map( function ( $o ) {
				return array( 'text' => (string) $o );
			}, array_values( array_filter( (array) $poll['options'], 'strlen' ) ) ),
			'settings' => array( 'duration' => in_array( $poll['duration'] ?? '', array( 'ONE_DAY', 'THREE_DAYS', 'SEVEN_DAYS', 'FOURTEEN_DAYS' ), true ) ? $poll['duration'] : 'THREE_DAYS' ),
		) );
	}
	$urn = bdb_li_create_post( bdb_commentary( $p ), $content );
	if ( is_wp_error( $urn ) ) {
		return $urn;
	}
	$changes = array(
		'status'       => 'published',
		'published_at' => bdb_now_utc(),
		'li_urn'       => $urn,
		'error'        => '',
		'meta'         => array_merge( bdb_post( $p['id'] )['meta'], array( 'url' => 'https://www.linkedin.com/feed/update/' . $urn . '/' ) ),
	);
	if ( ! $p['scheduled_at'] ) {
		$changes['scheduled_at'] = $changes['published_at'];
	}
	bdb_post_save( $changes, $p['id'] );
	bdb_log( 'ok', sprintf( 'منتشر شد: «%s»', $p['title'] ) );
	if ( (int) bdb_setting( 'first_comment', 1 ) && '' !== trim( $p['first_comment'] ) ) {
		$c = bdb_li_comment( $urn, $p['first_comment'] );
		bdb_post_meta_merge( $p['id'], array( 'first_comment_error' => is_wp_error( $c ) ? $c->get_error_message() : '' ) );
	}
	return true;
}

/**
 * Once a day: token warning, idea refresh.
 */
function bdb_daily() {
	$left = bdb_li_days_left();
	if ( null !== $left && $left <= 10 && (int) get_option( 'bdb_li_expiry_mail', 0 ) < time() - 3 * DAY_IN_SECONDS ) {
		update_option( 'bdb_li_expiry_mail', time(), false );
		bdb_notify( 'اتصال لینکدین بادبان رو به اتمام است', sprintf( "توکن لینکدین %s روز دیگر منقضی می‌شود. برای اینکه انتشار خودکار متوقف نشود، یک بار دوباره متصل شوید:\n\n%s", max( 0, $left ), bdb_url( 'settings' ) ) );
	}
	bdb_harvest_all();
}

/**
 * Email the owner.
 */
function bdb_notify( $subject, $text ) {
	$to = bdb_setting( 'notify_email' ) ?: get_option( 'admin_email' );
	if ( $to ) {
		wp_mail( $to, '[بادبان] ' . $subject, $text );
	}
}

/**
 * Activity log (last 120 events).
 */
function bdb_log( $level, $msg ) {
	$log   = get_option( 'bdb_log', array() );
	$log   = is_array( $log ) ? $log : array();
	$log[] = array( 't' => bdb_now_utc(), 'l' => $level, 'm' => $msg );
	update_option( 'bdb_log', array_slice( $log, -120 ), false );
}
