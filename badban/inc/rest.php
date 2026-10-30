<?php
/**
 * REST API of the app (namespace badban/v1). Cookie auth + wp_rest nonce;
 * every route needs the tool capability.
 *
 *   GET  post/{id}           a post + render settings (editor, slide renderer)
 *   POST write               write a new post {idea?, lang, format, pillar, framework, hook_type, cta_type, topic, angle, extra}
 *   POST save/{id}           save the editor {title, body, hashtags, first_comment, slides, poll, lang, format, pillar, hook_type, cta_type, when}
 *   POST rewrite/{id}        write the post again {extra}
 *   POST polish/{id}         quick AI edit {how: hook|shorter|story|clearer|punchy}
 *   POST counterpart/{id}    the other language's version
 *   POST asset/{id}          the rendered PDF / PNG (multipart «file», «hash»)
 *   POST approve/{id}        schedule {when?: local datetime|«next»}
 *   POST approve-all         schedule every ready post that has a time
 *   POST unschedule/{id}     back to ready
 *   POST publish/{id}        publish now
 *   POST duplicate/{id}      copy as a draft
 *   POST delete/{id}
 *   POST metrics/{id}        numbers typed by hand
 *   POST plan                build the plan {weeks}
 *   POST plan-tick           write the next planned post
 *   POST ideas/harvest       collect ideas from Sokan, Ghotbnama, Fanus and the library
 *   POST ideas/ai            AI brainstorm {pillar, count}
 *   POST idea                add an idea by hand {title, angle, pillar}
 *   POST idea/{id}           change status {status: new|used|dismissed|delete}
 *   POST import              LinkedIn analytics export (multipart «file»)
 *   POST test/{what}         li|ai
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	$ns     = 'badban/v1';
	$perm   = function () {
		return bdb_can();
	};
	$routes = array(
		array( '/post/(?P<id>\d+)', 'GET', 'bdb_rest_get_post' ),
		array( '/write', 'POST', 'bdb_rest_write' ),
		array( '/save/(?P<id>\d+)', 'POST', 'bdb_rest_save' ),
		array( '/rewrite/(?P<id>\d+)', 'POST', 'bdb_rest_rewrite' ),
		array( '/polish/(?P<id>\d+)', 'POST', 'bdb_rest_polish' ),
		array( '/counterpart/(?P<id>\d+)', 'POST', 'bdb_rest_counterpart' ),
		array( '/asset/(?P<id>\d+)', 'POST', 'bdb_rest_asset' ),
		array( '/approve/(?P<id>\d+)', 'POST', 'bdb_rest_approve' ),
		array( '/approve-all', 'POST', 'bdb_rest_approve_all' ),
		array( '/unschedule/(?P<id>\d+)', 'POST', 'bdb_rest_unschedule' ),
		array( '/publish/(?P<id>\d+)', 'POST', 'bdb_rest_publish' ),
		array( '/duplicate/(?P<id>\d+)', 'POST', 'bdb_rest_duplicate' ),
		array( '/delete/(?P<id>\d+)', 'POST', 'bdb_rest_delete' ),
		array( '/metrics/(?P<id>\d+)', 'POST', 'bdb_rest_metrics' ),
		array( '/plan', 'POST', 'bdb_rest_plan' ),
		array( '/plan-tick', 'POST', 'bdb_rest_plan_tick' ),
		array( '/ideas/harvest', 'POST', 'bdb_rest_harvest' ),
		array( '/ideas/ai', 'POST', 'bdb_rest_ideas_ai' ),
		array( '/idea', 'POST', 'bdb_rest_idea_add' ),
		array( '/idea/(?P<id>\d+)', 'POST', 'bdb_rest_idea_status' ),
		array( '/import', 'POST', 'bdb_rest_import' ),
		array( '/test/(?P<what>li|ai)', 'POST', 'bdb_rest_test' ),
	);
	foreach ( $routes as $r ) {
		register_rest_route( $ns, $r[0], array( 'methods' => $r[1], 'callback' => $r[2], 'permission_callback' => $perm ) );
	}
} );

/**
 * Post of a request (URL id) or error.
 */
function bdb_rest_post( $req ) {
	$p = bdb_post( (int) ( $req->get_url_params()['id'] ?? 0 ) );
	return $p ?: new WP_Error( 'bdb_404', 'پست پیدا نشد.', array( 'status' => 404 ) );
}

/**
 * Error with HTTP 400 (so the app shows the message).
 */
function bdb_rest_err( $e ) {
	return new WP_Error( $e->get_error_code(), $e->get_error_message(), array( 'status' => 400 ) );
}

/**
 * Settings the slide renderer needs.
 */
function bdb_render_conf() {
	$b = bdb_brand();
	return array(
		'name'      => $b['name'],
		'name_fa'   => $b['name_fa'],
		'headline'  => $b['headline'],
		'headline_fa' => $b['headline_fa'],
		'handle'    => $b['handle'],
		'photo'     => $b['photo_url'],
		'color'     => $b['brand_color'],
		'color2'    => $b['brand_color2'],
		'dark'      => $b['brand_dark'],
		'font'      => BDB_URL . 'assets/fonts/Vazirmatn-Variable.woff2',
	);
}

/**
 * Post for the app: fields + quality + local schedule time.
 */
function bdb_post_for_app( $p ) {
	$q     = bdb_quality( $p );
	$local = bdb_local( $p['scheduled_at'], $p['lang'] );
	return array(
		'post'    => array_merge( $p, array( 'when_local' => $local ? $local->format( 'Y-m-d\TH:i' ) : '', 'when_label' => bdb_when( $p['scheduled_at'], $p['lang'] ), 'url' => bdb_post_url( $p ), 'asset_url' => $p['asset'] ? bdb_asset_url( $p['asset'] ) : '', 'hash' => bdb_slides_hash( $p ) ) ),
		'quality' => $q,
		'render'  => bdb_render_conf(),
	);
}

/**
 * GET post.
 */
function bdb_rest_get_post( $req ) {
	$p = bdb_rest_post( $req );
	return is_wp_error( $p ) ? $p : bdb_post_for_app( $p );
}

/**
 * Write a new post.
 */
function bdb_rest_write( $req ) {
	bdb_raise_limits( 300 );
	$args = array(
		'lang'      => 'fa' === $req['lang'] ? 'fa' : 'en',
		'format'    => isset( bdb_formats()[ (string) $req['format'] ] ) ? $req['format'] : '',
		'pillar'    => isset( bdb_pillars()[ (string) $req['pillar'] ] ) ? $req['pillar'] : '',
		'framework' => isset( bdb_frameworks()[ (string) $req['framework'] ] ) ? $req['framework'] : '',
		'hook_type' => isset( bdb_hook_types()[ (string) $req['hook_type'] ] ) ? $req['hook_type'] : '',
		'cta_type'  => isset( bdb_cta_types()[ (string) $req['cta_type'] ] ) ? $req['cta_type'] : '',
		'topic'     => sanitize_textarea_field( (string) $req['topic'] ),
		'angle'     => sanitize_textarea_field( (string) $req['angle'] ),
		'extra'     => sanitize_textarea_field( (string) $req['extra'] ),
		'slides'    => max( 6, min( 10, (int) ( $req['slides'] ?: 8 ) ) ),
	);
	if ( (int) $req['idea'] ) {
		$id = bdb_post_from_idea( (int) $req['idea'], array_filter( $args ) );
	} else {
		$args['framework'] = $args['framework'] ?: 'lesson';
		$args['format']    = $args['format'] ?: bdb_suggest_format( $args['framework'] );
		$args['hook_type'] = $args['hook_type'] ?: bdb_suggest_hook( $args['framework'] );
		$args['cta_type']  = $args['cta_type'] ?: bdb_next_cta();
		$fields            = bdb_write_post( $args );
		$id                = is_wp_error( $fields ) ? $fields : bdb_post_save( array_merge( $fields, array( 'status' => 'draft' ) ) );
	}
	if ( is_wp_error( $id ) ) {
		return bdb_rest_err( $id );
	}
	if ( 'next' === $req['when'] ) {
		$p = bdb_post( $id );
		bdb_post_save( array( 'scheduled_at' => bdb_next_free_slot( $p['lang'] ) ), $id );
	}
	bdb_post_mark_ready( $id );
	return array( 'id' => $id, 'url' => bdb_url( 'studio/' . $id ) );
}

/**
 * Save the editor.
 */
function bdb_rest_save( $req ) {
	$p = bdb_rest_post( $req );
	if ( is_wp_error( $p ) ) {
		return $p;
	}
	if ( in_array( $p['status'], array( 'published', 'publishing' ), true ) ) {
		return new WP_Error( 'bdb_locked', 'پست منتشرشده قابل ویرایش نیست؛ از آن کپی بگیرید.', array( 'status' => 400 ) );
	}
	$d       = $req->get_json_params();
	$changes = array();
	foreach ( array( 'title', 'hashtags' ) as $k ) {
		if ( isset( $d[ $k ] ) ) {
			$changes[ $k ] = sanitize_text_field( (string) $d[ $k ] );
		}
	}
	foreach ( array( 'body', 'first_comment' ) as $k ) {
		if ( isset( $d[ $k ] ) ) {
			$changes[ $k ] = 'body' === $k ? bdb_clean_body( sanitize_textarea_field( (string) $d[ $k ] ) ) : trim( sanitize_textarea_field( (string) $d[ $k ] ) );
		}
	}
	if ( isset( $d['lang'] ) ) {
		$changes['lang'] = 'fa' === $d['lang'] ? 'fa' : 'en';
	}
	if ( isset( $d['format'] ) && isset( bdb_formats()[ $d['format'] ] ) ) {
		$changes['format'] = $d['format'];
	}
	foreach ( array( 'pillar' => bdb_pillars(), 'hook_type' => bdb_hook_types(), 'cta_type' => bdb_cta_types() ) as $k => $allowed ) {
		if ( isset( $d[ $k ] ) && ( '' === $d[ $k ] || isset( $allowed[ $d[ $k ] ] ) ) ) {
			$changes[ $k ] = $d[ $k ];
		}
	}
	if ( isset( $d['slides'] ) && is_array( $d['slides'] ) ) {
		$changes['slides'] = array_map( 'bdb_slide_clean', array_slice( $d['slides'], 0, 14 ) );
	}
	$meta = $p['meta'];
	if ( isset( $d['poll'] ) && is_array( $d['poll'] ) ) {
		$meta['poll'] = array(
			'question' => mb_substr( sanitize_text_field( (string) ( $d['poll']['question'] ?? '' ) ), 0, 140 ),
			'options'  => array_slice( array_values( array_filter( array_map( function ( $o ) {
				return mb_substr( sanitize_text_field( (string) $o ), 0, 30 );
			}, (array) ( $d['poll']['options'] ?? array() ) ), 'strlen' ) ), 0, 4 ),
			'duration' => in_array( $d['poll']['duration'] ?? '', array( 'ONE_DAY', 'THREE_DAYS', 'SEVEN_DAYS', 'FOURTEEN_DAYS' ), true ) ? $d['poll']['duration'] : 'THREE_DAYS',
		);
	}
	if ( isset( $d['doc_title'] ) ) {
		$meta['doc_title'] = mb_substr( sanitize_text_field( (string) $d['doc_title'] ), 0, 80 );
	}
	$changes['meta'] = $meta;
	if ( array_key_exists( 'when', (array) $d ) ) {
		$lang                    = $changes['lang'] ?? $p['lang'];
		$changes['scheduled_at'] = 'next' === $d['when'] ? bdb_next_free_slot( $lang ) : bdb_to_utc( (string) $d['when'], $lang );
	}
	bdb_post_save( $changes, $p['id'] );
	$p = bdb_post( $p['id'] );
	// An edit sends a scheduled post back to review; a clean draft becomes ready.
	if ( 'scheduled' === $p['status'] && bdb_quality( $p )['blocking'] ) {
		bdb_post_save( array( 'status' => 'draft' ), $p['id'] );
	} elseif ( in_array( $p['status'], array( 'draft', 'ready', 'failed' ), true ) ) {
		bdb_post_mark_ready( $p['id'] );
	}
	return bdb_post_for_app( bdb_post( $p['id'] ) );
}

/**
 * Write again.
 */
function bdb_rest_rewrite( $req ) {
	$p = bdb_rest_post( $req );
	if ( is_wp_error( $p ) ) {
		return $p;
	}
	bdb_raise_limits( 300 );
	$r = bdb_rewrite_post( $p['id'], sanitize_textarea_field( (string) $req['extra'] ) );
	if ( is_wp_error( $r ) ) {
		return bdb_rest_err( $r );
	}
	bdb_post_mark_ready( $p['id'] );
	return bdb_post_for_app( bdb_post( $p['id'] ) );
}

/**
 * Quick AI edit.
 */
function bdb_rest_polish( $req ) {
	$p = bdb_rest_post( $req );
	if ( is_wp_error( $p ) ) {
		return $p;
	}
	$r = bdb_polish( $p['id'], sanitize_key( (string) $req['how'] ) );
	return is_wp_error( $r ) ? bdb_rest_err( $r ) : $r;
}

/**
 * Other language's version.
 */
function bdb_rest_counterpart( $req ) {
	$p = bdb_rest_post( $req );
	if ( is_wp_error( $p ) ) {
		return $p;
	}
	bdb_raise_limits( 300 );
	$id = bdb_write_counterpart( $p['id'] );
	if ( is_wp_error( $id ) ) {
		return bdb_rest_err( $id );
	}
	$new = bdb_post( $id );
	bdb_post_save( array( 'scheduled_at' => bdb_next_free_slot( $new['lang'] ) ), $id );
	bdb_post_mark_ready( $id );
	return array( 'id' => $id, 'url' => bdb_url( 'studio/' . $id ) );
}

/**
 * Upload the rendered file.
 */
function bdb_rest_asset( $req ) {
	$p = bdb_rest_post( $req );
	if ( is_wp_error( $p ) ) {
		return $p;
	}
	$f = $req->get_file_params()['file'] ?? null;
	if ( ! $f || ! empty( $f['error'] ) || empty( $f['tmp_name'] ) ) {
		return new WP_Error( 'bdb_upload', 'فایلی دریافت نشد.', array( 'status' => 400 ) );
	}
	$head = (string) file_get_contents( $f['tmp_name'], false, null, 0, 8 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$ext  = 0 === strpos( $head, '%PDF' ) ? 'pdf' : ( "\x89PNG" === substr( $head, 0, 4 ) ? 'png' : '' );
	if ( '' === $ext || ( 'carousel' === $p['format'] ) !== ( 'pdf' === $ext ) ) {
		return new WP_Error( 'bdb_upload', 'نوع فایل با قالب پست نمی‌خواند (کاروسل: PDF، کارت تصویری: PNG).', array( 'status' => 400 ) );
	}
	if ( (int) $f['size'] > 95 * MB_IN_BYTES ) {
		return new WP_Error( 'bdb_upload', 'حجم فایل بیش از حد مجاز لینکدین است.', array( 'status' => 400 ) );
	}
	$name = 'post-' . $p['id'] . '-' . wp_generate_password( 10, false ) . '.' . $ext;
	if ( ! @move_uploaded_file( $f['tmp_name'], bdb_asset_path( $name ) ) && ! @copy( $f['tmp_name'], bdb_asset_path( $name ) ) ) { // phpcs:ignore
		return new WP_Error( 'bdb_upload', 'ذخیره فایل در پوشه آپلود ممکن نشد (دسترسی نوشتن).', array( 'status' => 500 ) );
	}
	if ( $p['asset'] && file_exists( bdb_asset_path( $p['asset'] ) ) ) {
		wp_delete_file( bdb_asset_path( $p['asset'] ) );
	}
	$hash = preg_replace( '/[^a-f0-9]/', '', (string) $req['hash'] ) ?: bdb_slides_hash( $p );
	bdb_post_save( array( 'asset' => $name, 'meta' => array_merge( $p['meta'], array( 'asset_hash' => $hash, 'asset_pages' => (int) $req['pages'] ) ) ), $p['id'] );
	if ( in_array( $p['status'], array( 'draft', 'ready', 'failed' ), true ) ) {
		bdb_post_mark_ready( $p['id'] );
	}
	return bdb_post_for_app( bdb_post( $p['id'] ) );
}

/**
 * Approve: schedule.
 */
function bdb_rest_approve( $req ) {
	$p = bdb_rest_post( $req );
	if ( is_wp_error( $p ) ) {
		return $p;
	}
	if ( in_array( $p['status'], array( 'published', 'publishing' ), true ) ) {
		return new WP_Error( 'bdb_done', 'این پست منتشر شده است.', array( 'status' => 400 ) );
	}
	$when = (string) $req['when'];
	$at   = 'next' === $when ? bdb_next_free_slot( $p['lang'] ) : ( '' !== $when ? bdb_to_utc( $when, $p['lang'] ) : $p['scheduled_at'] );
	if ( ! $at ) {
		$at = bdb_next_free_slot( $p['lang'] );
	}
	if ( ! $at ) {
		return new WP_Error( 'bdb_slot', 'زمان انتشار مشخص نیست و جایگاه خالی هم پیدا نشد؛ زمان را دستی انتخاب کنید.', array( 'status' => 400 ) );
	}
	bdb_post_save( array( 'scheduled_at' => $at ), $p['id'] );
	$p = bdb_post( $p['id'] );
	$q = bdb_quality( $p );
	if ( $q['blocking'] ) {
		return new WP_Error( 'bdb_quality', 'پیش از زمان‌بندی، خطاهای بررسی کیفیت را رفع کنید.', array( 'status' => 400 ) );
	}
	bdb_post_save( array( 'status' => 'scheduled', 'error' => '' ), $p['id'] );
	return array_merge( bdb_post_for_app( bdb_post( $p['id'] ) ), array( 'msg' => 'زمان‌بندی شد: ' . bdb_when( $at, $p['lang'] ) ) );
}

/**
 * Approve all ready posts with a time in the future.
 */
function bdb_rest_approve_all() {
	$n = 0;
	foreach ( bdb_posts( array( 'status' => 'ready', 'limit' => 100 ) ) as $p ) {
		if ( $p['scheduled_at'] && strtotime( $p['scheduled_at'] ) > time() && ! bdb_quality( $p )['blocking'] ) {
			bdb_post_save( array( 'status' => 'scheduled' ), $p['id'] );
			++$n;
		}
	}
	return array( 'count' => $n, 'msg' => sprintf( '%s پست زمان‌بندی شد.', bdb_num( $n ) ) );
}

/**
 * Back to ready.
 */
function bdb_rest_unschedule( $req ) {
	$p = bdb_rest_post( $req );
	if ( is_wp_error( $p ) ) {
		return $p;
	}
	if ( 'scheduled' === $p['status'] || 'failed' === $p['status'] ) {
		bdb_post_save( array( 'status' => 'ready' ), $p['id'] );
	}
	return bdb_post_for_app( bdb_post( $p['id'] ) );
}

/**
 * Publish now.
 */
function bdb_rest_publish( $req ) {
	$p = bdb_rest_post( $req );
	if ( is_wp_error( $p ) ) {
		return $p;
	}
	bdb_raise_limits( 240 );
	$r = bdb_publish( $p['id'], true );
	if ( is_wp_error( $r ) ) {
		return bdb_rest_err( $r );
	}
	$p = bdb_post( $p['id'] );
	return array_merge( bdb_post_for_app( $p ), array( 'msg' => 'published' === $p['status'] ? 'در لینکدین منتشر شد.' : (string) $p['error'] ) );
}

/**
 * Copy as a draft.
 */
function bdb_rest_duplicate( $req ) {
	$p = bdb_rest_post( $req );
	if ( is_wp_error( $p ) ) {
		return $p;
	}
	$copy = array_intersect_key( $p, array_flip( array( 'lang', 'format', 'pillar', 'body', 'first_comment', 'hashtags', 'hook_type', 'cta_type', 'slides', 'idea_id' ) ) );
	$meta = $p['meta'];
	unset( $meta['url'], $meta['li_media'], $meta['li_media_hash'], $meta['asset_hash'], $meta['first_comment_error'] );
	$id = bdb_post_save( array_merge( $copy, array( 'title' => $p['title'] . ' (کپی)', 'status' => 'draft', 'meta' => $meta ) ) );
	return array( 'id' => $id, 'url' => bdb_url( 'studio/' . $id ) );
}

/**
 * Delete.
 */
function bdb_rest_delete( $req ) {
	$p = bdb_rest_post( $req );
	if ( is_wp_error( $p ) ) {
		return $p;
	}
	bdb_post_delete( $p['id'] );
	return array( 'ok' => true );
}

/**
 * Numbers typed by hand.
 */
function bdb_rest_metrics( $req ) {
	$p = bdb_rest_post( $req );
	if ( is_wp_error( $p ) ) {
		return $p;
	}
	$m = $p['metrics'];
	foreach ( array( 'impressions', 'reactions', 'comments', 'reposts', 'saves', 'clicks', 'followers', 'profile_views', 'leads' ) as $k ) {
		if ( null !== $req[ $k ] && '' !== (string) $req[ $k ] ) {
			$m[ $k ] = max( 0, (int) bdb_norm( (string) $req[ $k ] ) );
		}
	}
	$m['updated_at'] = bdb_now_utc();
	$m['source']     = 'manual';
	bdb_post_save( array( 'metrics' => $m ), $p['id'] );
	$p  = bdb_post( $p['id'] );
	$er = bdb_post_er( $p );
	return array( 'ok' => true, 'msg' => 'آمار ذخیره شد' . ( null !== $er ? '؛ نرخ تعامل ' . bdb_pct( $er, 1 ) : '' ) . '.' );
}

/**
 * Build the plan.
 */
function bdb_rest_plan( $req ) {
	bdb_raise_limits( 120 );
	return bdb_plan_build( max( 1, min( 4, (int) ( $req['weeks'] ?: 2 ) ) ) );
}

/**
 * Next planned post.
 */
function bdb_rest_plan_tick() {
	return bdb_plan_tick();
}

/**
 * Harvest ideas.
 */
function bdb_rest_harvest() {
	bdb_raise_limits( 180 );
	$r   = bdb_harvest_all();
	$lib = bdb_harvest_library();
	$sum = array_sum( $r ) + $lib;
	$parts = array();
	foreach ( $r as $k => $n ) {
		if ( $n ) {
			$parts[] = ( bdb_idea_sources()[ $k ][0] ?? $k ) . ': ' . bdb_num( $n );
		}
	}
	if ( $lib ) {
		$parts[] = 'کتابخانه: ' . bdb_num( $lib );
	}
	return array( 'count' => $sum, 'msg' => $sum ? sprintf( '%s ایده تازه اضافه شد (%s).', bdb_num( $sum ), implode( '، ', $parts ) ) : 'ایده تازه‌ای نبود؛ همه ایده‌های منابع قبلاً در بانک هستند.' );
}

/**
 * AI brainstorm.
 */
function bdb_rest_ideas_ai( $req ) {
	bdb_raise_limits( 240 );
	$n = bdb_ideas_ai( sanitize_key( (string) $req['pillar'] ), max( 3, min( 15, (int) ( $req['count'] ?: 8 ) ) ) );
	return is_wp_error( $n ) ? bdb_rest_err( $n ) : array( 'count' => $n, 'msg' => sprintf( '%s ایده تازه از هوش مصنوعی.', bdb_num( $n ) ) );
}

/**
 * Add an idea by hand.
 */
function bdb_rest_idea_add( $req ) {
	$title = sanitize_text_field( (string) $req['title'] );
	if ( '' === $title ) {
		return new WP_Error( 'bdb_idea', 'عنوان ایده را بنویسید.', array( 'status' => 400 ) );
	}
	$id = bdb_idea_add( array(
		'title'   => $title,
		'angle'   => sanitize_textarea_field( (string) $req['angle'] ),
		'pillar'  => isset( bdb_pillars()[ (string) $req['pillar'] ] ) ? $req['pillar'] : '',
		'source'  => 'manual',
		'score'   => 70,
		'payload' => array( 'framework' => isset( bdb_frameworks()[ (string) $req['framework'] ] ) ? $req['framework'] : 'lesson' ),
	) );
	return $id ? array( 'id' => $id, 'msg' => 'ایده اضافه شد.' ) : new WP_Error( 'bdb_idea', 'این ایده قبلاً در بانک هست.', array( 'status' => 400 ) );
}

/**
 * Idea status / delete.
 */
function bdb_rest_idea_status( $req ) {
	$id = (int) $req->get_url_params()['id'];
	$st = sanitize_key( (string) $req['status'] );
	if ( 'delete' === $st ) {
		bdb_idea_delete( $id );
	} elseif ( in_array( $st, array( 'new', 'used', 'dismissed' ), true ) ) {
		bdb_idea_status( $id, $st );
	}
	return array( 'ok' => true );
}

/**
 * Analytics import.
 */
function bdb_rest_import( $req ) {
	$f = $req->get_file_params()['file'] ?? null;
	if ( ! $f || ! empty( $f['error'] ) || empty( $f['tmp_name'] ) ) {
		return new WP_Error( 'bdb_upload', 'فایلی دریافت نشد.', array( 'status' => 400 ) );
	}
	$r = bdb_import_analytics( $f['tmp_name'], (string) $f['name'] );
	return is_wp_error( $r ) ? bdb_rest_err( $r ) : $r;
}

/**
 * Connection tests.
 */
function bdb_rest_test( $req ) {
	if ( 'li' === $req['what'] ) {
		return bdb_li_test();
	}
	if ( bdb_ai_via_sokan() ) {
		$r = bdb_ai_request( 'Reply with JSON.', 'Say ok.', array( 'type' => 'object', 'additionalProperties' => false, 'required' => array( 'ok' ), 'properties' => array( 'ok' => array( 'type' => 'string' ) ) ), array( 'purpose' => 'light', 'max_tokens' => 50 ) );
		return array( 'ok' => ! is_wp_error( $r ), 'steps' => array( array( 'ok' => ! is_wp_error( $r ), 'name' => bdb_ai_label(), 'msg' => is_wp_error( $r ) ? $r->get_error_message() : 'جواب داد؛ آماده نوشتن است.' ) ) );
	}
	return bdb_ai_test();
}
