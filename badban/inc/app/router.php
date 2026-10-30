<?php
/**
 * Front-end app: the tool lives at {site}/seo/brand/ as a standalone,
 * full-screen dashboard (own login screen, no theme), like Sokan at /seo/plan/,
 * Ghotbnama at /seo/cluster/ and Fanus at /seo/report/.
 *
 * Routes (relative to the base):
 *   /                    dashboard
 *   /studio/             write a new post;  /studio/{id}/  edit a post
 *   /queue/              all posts (tabs by status)
 *   /calendar/           publishing calendar + automatic plan
 *   /ideas/              idea bank
 *   /analytics/          growth, posts, insights
 *   /report/             monthly LinkedIn report (print → PDF)
 *   /brand/              brand strategy, pillars, voice
 *   /settings/           LinkedIn, AI, network, schedule
 *   /guide/              guide
 *   /linkedin-connect/   → LinkedIn authorization;  /linkedin-callback/  ← back
 *
 * Without pretty permalinks the same routes work as /?bdb_app=1&bdb_route=…
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

const BDB_REWRITE_VERSION = '1';

/**
 * Base path of the app. Filterable.
 */
function bdb_app_base() {
	return trim( (string) apply_filters( 'bdb_app_base', 'seo/brand' ), '/' );
}

/**
 * App URL of a route.
 */
function bdb_url( $route = '', $args = array() ) {
	$route = trim( (string) $route, '/' );
	if ( get_option( 'permalink_structure' ) ) {
		$url = home_url( '/' . bdb_app_base() . '/' . ( '' !== $route ? $route . '/' : '' ) );
	} else {
		$url = add_query_arg( array( 'bdb_app' => 1, 'bdb_route' => $route ), home_url( '/' ) );
	}
	return $args ? add_query_arg( $args, $url ) : $url;
}

add_action( 'init', function () {
	add_rewrite_rule( '^' . preg_quote( bdb_app_base(), '#' ) . '/?(.*)$', 'index.php?bdb_app=1&bdb_route=$matches[1]', 'top' );
	if ( get_option( 'bdb_rewrite_version' ) !== BDB_REWRITE_VERSION . '|' . bdb_app_base() ) {
		flush_rewrite_rules( false );
		update_option( 'bdb_rewrite_version', BDB_REWRITE_VERSION . '|' . bdb_app_base(), false );
	}
} );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'bdb_app';
	$vars[] = 'bdb_route';
	return $vars;
} );

/**
 * wp-admin keeps one menu item (and an admin-bar link) that opens the app.
 */
add_action( 'admin_menu', function () {
	add_menu_page( 'بادبان', 'بادبان (لینکدین)', BDB_CAP, 'bdb', '__return_null', 'dashicons-linkedin', 4 );
} );
add_action( 'load-toplevel_page_bdb', function () {
	wp_safe_redirect( bdb_url() );
	exit;
} );
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( bdb_can() ) {
		$bar->add_node( array( 'id' => 'bdb', 'title' => 'بادبان', 'href' => bdb_url() ) );
	}
}, 82 );

/**
 * Render the app before the theme.
 */
add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'bdb_app' ) ) {
		return;
	}
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow', true );
	status_header( 200 );
	show_admin_bar( false );
	$route = trim( (string) get_query_var( 'bdb_route' ), '/' );
	if ( ! is_user_logged_in() ) {
		bdb_render( 'login', array( 'redirect' => bdb_url( $route ) ) );
	}
	if ( ! bdb_can() ) {
		status_header( 403 );
		bdb_render( 'denied' );
	}
	wp_raise_memory_limit( 'admin' );
	bdb_handle_post( $route );
	bdb_handle_linkedin( $route );
	$r = bdb_resolve_route( $route );
	if ( 'report' === $r['view'] ) {
		bdb_render( 'report', $r );
	}
	bdb_render( 'shell', $r );
}, 1 );

/**
 * LinkedIn OAuth routes.
 */
function bdb_handle_linkedin( $route ) {
	if ( 'linkedin-connect' === $route ) {
		if ( '' === (string) bdb_setting( 'li_client_id' ) || '' === (string) bdb_setting( 'li_client_secret' ) ) {
			bdb_flash_set( 'اول Client ID و Client Secret برنامه لینکدین را در تنظیمات وارد کنید.', false );
			wp_safe_redirect( bdb_url( 'settings' ) );
			exit;
		}
		wp_redirect( bdb_li_auth_url() ); // phpcs:ignore WordPress.Security.SafeRedirect -- linkedin.com.
		exit;
	}
	if ( 'linkedin-callback' === $route ) {
		$err = sanitize_text_field( wp_unslash( $_GET['error_description'] ?? $_GET['error'] ?? '' ) ); // phpcs:ignore
		if ( '' !== $err ) {
			bdb_flash_set( 'لینکدین اتصال را رد کرد: ' . $err, false );
		} else {
			$r = bdb_li_handle_callback( sanitize_text_field( wp_unslash( $_GET['code'] ?? '' ) ), sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) ) ); // phpcs:ignore
			bdb_flash_set( is_wp_error( $r ) ? $r->get_error_message() : 'حساب لینکدین ' . bdb_setting( 'li_name' ) . ' وصل شد. انتشار خودکار فعال است.', ! is_wp_error( $r ) );
		}
		wp_safe_redirect( bdb_url( 'settings' ) );
		exit;
	}
}

/**
 * Main navigation: key => [label, icon, route].
 */
function bdb_nav_items() {
	return array(
		'dashboard' => array( 'داشبورد', 'grid', '' ),
		'studio'    => array( 'استودیو محتوا', 'pen', 'studio' ),
		'queue'     => array( 'پست‌ها', 'list', 'queue' ),
		'calendar'  => array( 'تقویم انتشار', 'calendar', 'calendar' ),
		'ideas'     => array( 'بانک ایده', 'idea', 'ideas' ),
		'analytics' => array( 'آنالیتیکس', 'chart', 'analytics' ),
		'brand'     => array( 'استراتژی برند', 'diamond', 'brand' ),
	);
}

/**
 * Route → view + variables.
 */
function bdb_resolve_route( $route ) {
	$parts = array_values( array_filter( explode( '/', $route ), 'strlen' ) );
	$first = sanitize_key( $parts[0] ?? '' );
	$out   = array( 'route' => $route, 'nav' => $first ?: 'dashboard', 'vars' => array() );
	$views = array(
		'queue'     => 'پست‌ها',
		'calendar'  => 'تقویم انتشار',
		'ideas'     => 'بانک ایده',
		'analytics' => 'آنالیتیکس',
		'report'    => 'گزارش ماهانه لینکدین',
		'brand'     => 'استراتژی برند',
		'settings'  => 'تنظیمات و اتصال‌ها',
		'guide'     => 'راهنما',
	);
	if ( 'studio' === $first ) {
		$id   = absint( $parts[1] ?? 0 );
		$post = $id ? bdb_post( $id ) : null;
		if ( $id && ! $post ) {
			status_header( 404 );
			return array_merge( $out, array( 'view' => 'not-found', 'title' => 'یافت نشد' ) );
		}
		return array_merge( $out, array( 'view' => $post ? 'studio' : 'compose', 'title' => $post ? ( $post['title'] ?: 'ویرایش پست' ) : 'نوشتن پست تازه', 'vars' => array( 'post' => $post ) ) );
	}
	if ( isset( $views[ $first ] ) ) {
		return array_merge( $out, array( 'view' => $first, 'title' => $views[ $first ] ) );
	}
	if ( '' !== $first ) {
		status_header( 404 );
		return array_merge( $out, array( 'view' => 'not-found', 'title' => 'یافت نشد' ) );
	}
	return array_merge( $out, array( 'view' => 'dashboard', 'title' => 'داشبورد' ) );
}

/**
 * Output a full-page template and stop.
 */
function bdb_render( $bdb_template, $bdb_args = array() ) {
	extract( $bdb_args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
	include BDB_DIR . '/templates/app/' . $bdb_template . '.php';
	exit;
}

/**
 * Asset tags (no theme, no wp_head).
 */
function bdb_app_assets( $with_slides = false ) {
	printf( '<link rel="stylesheet" href="%s">' . "\n", esc_url( BDB_URL . 'assets/css/app.css?ver=' . BDB_VERSION ) );
	printf(
		'<script>window.BDB = %s;</script>' . "\n",
		wp_json_encode( array(
			'rest'   => esc_url_raw( rest_url( 'badban/v1/' ) ),
			'nonce'  => wp_create_nonce( 'wp_rest' ),
			'app'    => bdb_url(),
			'urls'   => array( 'ready' => bdb_url( 'queue', array( 'tab' => 'ready' ) ), 'queue' => bdb_url( 'queue' ) ),
			'render' => bdb_render_conf(),
		) )
	);
	if ( $with_slides ) {
		printf( '<script src="%s" defer></script>' . "\n", esc_url( BDB_URL . 'assets/js/slides.js?ver=' . BDB_VERSION ) );
	}
	printf( '<script src="%s" defer></script>' . "\n", esc_url( BDB_URL . 'assets/js/app.js?ver=' . BDB_VERSION ) );
}

/**
 * Login failures come back to the app's own login screen.
 */
add_action( 'wp_login_failed', function () {
	$to = isset( $_POST['bdb_login'], $_POST['redirect_to'] ) ? wp_unslash( $_POST['redirect_to'] ) : ''; // phpcs:ignore
	if ( $to ) {
		wp_safe_redirect( add_query_arg( 'bdb_login', 'failed', $to ) );
		exit;
	}
} );
