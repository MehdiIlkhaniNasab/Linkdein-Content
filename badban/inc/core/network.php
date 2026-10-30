<?php
/**
 * Network routing (same rules as Sokan and Ghotbnama).
 *
 * LinkedIn is blocked in Iran, so a server there reaches it one of two ways:
 *
 *   1. The WordPress proxy (WP_PROXY_HOST in wp-config.php, e.g. a local v2ray
 *      or a proxy on a foreign VPS). «smart» mode (default): only LinkedIn and
 *      foreign AI APIs go through it; GapGPT and everything else connect directly.
 *   2. A relay: a tiny Cloudflare Worker (relay/cloudflare-worker.js) on the
 *      agency's own domain. Every LinkedIn request is sent to
 *      {relay}/{host}/{path} with a shared secret and forwarded from abroad.
 *      Useful on hosts where a proxy cannot be installed.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hosts that need the proxy in smart mode.
 */
function bdb_proxy_hosts() {
	return array( 'linkedin.com', 'licdn.com', 'api.anthropic.com', 'api.openai.com' );
}

/**
 * Is a proxy configured in wp-config.php?
 */
function bdb_proxy_defined() {
	return defined( 'WP_PROXY_HOST' ) && '' !== (string) WP_PROXY_HOST;
}

/**
 * Proxy label for the settings screen.
 */
function bdb_proxy_label() {
	return bdb_proxy_defined() ? WP_PROXY_HOST . ( defined( 'WP_PROXY_PORT' ) ? ':' . WP_PROXY_PORT : '' ) : '';
}

/**
 * Is a relay configured?
 */
function bdb_relay_on() {
	return '' !== (string) bdb_setting( 'relay_url' ) && '' !== (string) bdb_setting( 'relay_secret' );
}

/**
 * Does a host match one of the patterns (suffix match)?
 */
function bdb_host_in( $host, $patterns ) {
	$host = strtolower( (string) $host );
	foreach ( $patterns as $p ) {
		$p = strtolower( trim( (string) $p ) );
		if ( '' !== $p && ( $host === $p || substr( $host, -strlen( '.' . $p ) ) === '.' . $p ) ) {
			return true;
		}
	}
	return false;
}

add_filter( 'pre_http_send_through_proxy', function ( $override, $uri, $check ) {
	if ( ! bdb_proxy_defined() ) {
		return $override;
	}
	if ( ! empty( $GLOBALS['bdb_force_direct'] ) ) {
		return false;
	}
	$host = (string) ( $check['host'] ?? '' );
	$ours = bdb_host_in( $host, bdb_proxy_hosts() );
	// With Sokan / Ghotbnama active their filters route everything else; only decide for our hosts.
	if ( ( defined( 'SAP_VERSION' ) || defined( 'QTB_VERSION' ) ) && ! $ours ) {
		return $override;
	}
	$mode = bdb_setting( 'proxy_mode', 'smart' );
	if ( 'all' === $mode ) {
		return true;
	}
	if ( 'none' === $mode ) {
		return false;
	}
	return $ours;
}, 12, 3 );

/**
 * Is an HTTP error a dead proxy?
 */
function bdb_is_proxy_down( $error ) {
	$msg = is_wp_error( $error ) ? $error->get_error_message() : (string) $error;
	return bdb_proxy_defined() && ( false !== strpos( $msg, 'cURL error 7' ) || false !== strpos( $msg, 'cURL error 97' ) ) && false !== strpos( $msg, (string) WP_PROXY_HOST );
}

/**
 * Persian explanation of a network error.
 */
function bdb_net_error( $error ) {
	$msg = is_wp_error( $error ) ? $error->get_error_message() : (string) $error;
	if ( bdb_is_proxy_down( $msg ) ) {
		return 'پروکسی وردپرس خاموش است. نرم‌افزار پروکسی (مثلاً v2rayN) را روشن کنید و دوباره امتحان کنید.';
	}
	if ( false !== strpos( $msg, 'cURL error 28' ) ) {
		return 'مهلت اتصال تمام شد (شبکه کند یا مسدود). اگر سرور در ایران است، لینکدین فقط از پروکسی یا رله در دسترس است (تنظیمات › شبکه).';
	}
	if ( false !== strpos( $msg, 'cURL error 6' ) ) {
		return 'نام دامنه پیدا نشد؛ اینترنت سرور، پروکسی یا آدرس رله را بررسی کنید.';
	}
	if ( false !== strpos( $msg, 'cURL error 35' ) || false !== strpos( $msg, 'cURL error 56' ) || false !== strpos( $msg, 'cURL error 52' ) ) {
		return 'اتصال وسط راه قطع شد (فیلترینگ یا پروکسی ناپایدار). مسیر اتصال لینکدین را در تنظیمات › شبکه بررسی کنید.';
	}
	return $msg;
}

/**
 * HTTP request that retries once without the proxy when the proxy is down.
 */
function bdb_http( $url, $args ) {
	$res = wp_remote_request( $url, $args );
	if ( is_wp_error( $res ) && bdb_is_proxy_down( $res ) ) {
		$GLOBALS['bdb_force_direct'] = true;
		$res                         = wp_remote_request( $url, $args );
		unset( $GLOBALS['bdb_force_direct'] );
	}
	return $res;
}

/**
 * HTTP request to LinkedIn: through the relay when one is set, else the proxy.
 */
function bdb_li_http( $url, $args ) {
	if ( bdb_relay_on() ) {
		$p    = wp_parse_url( $url );
		$url  = untrailingslashit( bdb_setting( 'relay_url' ) ) . '/' . $p['host'] . ( $p['path'] ?? '/' ) . ( isset( $p['query'] ) ? '?' . $p['query'] : '' );
		$args['headers'] = array_merge( (array) ( $args['headers'] ?? array() ), array( 'X-Badban-Relay' => bdb_setting( 'relay_secret' ) ) );
		$GLOBALS['bdb_force_direct'] = true; // The relay itself is reachable from Iran (Cloudflare on the agency's domain).
		$res = wp_remote_request( $url, $args );
		unset( $GLOBALS['bdb_force_direct'] );
		return $res;
	}
	return bdb_http( $url, $args );
}

/**
 * How LinkedIn is reached now (label for the settings and the error messages).
 */
function bdb_li_route_label() {
	if ( bdb_relay_on() ) {
		return 'رله ' . wp_parse_url( bdb_setting( 'relay_url' ), PHP_URL_HOST );
	}
	if ( bdb_proxy_defined() && 'none' !== bdb_setting( 'proxy_mode', 'smart' ) ) {
		return 'پروکسی وردپرس ' . bdb_proxy_label();
	}
	return 'مستقیم (بدون پروکسی)';
}
