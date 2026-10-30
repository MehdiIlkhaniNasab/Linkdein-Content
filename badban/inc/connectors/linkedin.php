<?php
/**
 * LinkedIn: member connection (OAuth 2.0) and publishing on the personal profile.
 *
 * App setup (developer.linkedin.com › My apps): add the self-serve products
 * «Share on LinkedIn» (w_member_social) and «Sign In with LinkedIn using OpenID
 * Connect» (openid, profile, email), and the redirect URL shown in Badban's
 * settings. No partner approval is needed for publishing on a personal profile.
 *
 * Publishing uses the versioned REST API (/rest/posts, LinkedIn-Version header):
 *   text      commentary only
 *   carousel  a PDF uploaded through the Documents API (shown as a swipeable document)
 *   image     a PNG uploaded through the Images API
 *   poll      question + 2–4 options
 * After the post, the prepared first comment (links, booking URL…) is added
 * through the Comments API, because links inside the post reduce its reach.
 *
 * Access tokens live 60 days and these products issue no refresh token, so the
 * owner reconnects with one click; Badban warns 10 days before expiry.
 *
 * Every request goes through bdb_li_http() (proxy or relay; the server is in Iran).
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

const BDB_LI_AUTH  = 'https://www.linkedin.com/oauth/v2/authorization';
const BDB_LI_TOKEN = 'https://www.linkedin.com/oauth/v2/accessToken';
const BDB_LI_API   = 'https://api.linkedin.com';

/**
 * OAuth redirect URL (must be added to the LinkedIn app exactly like this).
 */
function bdb_li_redirect_uri() {
	return bdb_url( 'linkedin-callback' );
}

/**
 * Is a member connected with a token that has not expired?
 */
function bdb_li_connected() {
	return '' !== (string) bdb_setting( 'li_token' ) && '' !== (string) bdb_setting( 'li_person' ) && (int) bdb_setting( 'li_expires', 0 ) > time();
}

/**
 * Days left on the access token (negative when expired, null when never connected).
 */
function bdb_li_days_left() {
	$exp = (int) bdb_setting( 'li_expires', 0 );
	return $exp ? (int) floor( ( $exp - time() ) / DAY_IN_SECONDS ) : null;
}

/**
 * Authorization URL (the owner's browser goes there; it is not blocked for the owner with a VPN).
 */
function bdb_li_auth_url() {
	$state = wp_generate_password( 24, false );
	set_transient( 'bdb_li_state_' . get_current_user_id(), $state, 15 * MINUTE_IN_SECONDS );
	return add_query_arg( array(
		'response_type' => 'code',
		'client_id'     => rawurlencode( (string) bdb_setting( 'li_client_id' ) ),
		'redirect_uri'  => rawurlencode( bdb_li_redirect_uri() ),
		'state'         => $state,
		'scope'         => rawurlencode( 'openid profile email w_member_social' ),
	), BDB_LI_AUTH );
}

/**
 * OAuth callback: exchange the code, read the member, store the token.
 *
 * @return true|WP_Error
 */
function bdb_li_handle_callback( $code, $state ) {
	$want = get_transient( 'bdb_li_state_' . get_current_user_id() );
	delete_transient( 'bdb_li_state_' . get_current_user_id() );
	if ( ! $want || ! hash_equals( (string) $want, (string) $state ) ) {
		return new WP_Error( 'bdb_li_state', 'نشست اتصال منقضی شد یا معتبر نبود؛ دوباره «اتصال به لینکدین» را بزنید.' );
	}
	$res = bdb_li_http( BDB_LI_TOKEN, array(
		'method'  => 'POST',
		'timeout' => 45,
		'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
		'body'    => array(
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'redirect_uri'  => bdb_li_redirect_uri(),
			'client_id'     => bdb_setting( 'li_client_id' ),
			'client_secret' => bdb_setting( 'li_client_secret' ),
		),
	) );
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'bdb_li_http', 'اتصال به لینکدین برقرار نشد (' . bdb_li_route_label() . '): ' . bdb_net_error( $res ) );
	}
	$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	if ( empty( $data['access_token'] ) ) {
		return new WP_Error( 'bdb_li_token', 'لینکدین توکن نداد: ' . ( $data['error_description'] ?? $data['error'] ?? 'HTTP ' . wp_remote_retrieve_response_code( $res ) ) . ' — Client ID، Client Secret و Redirect URL برنامه لینکدین را بررسی کنید.' );
	}
	$token = (string) $data['access_token'];
	$me    = bdb_li_request( 'GET', '/v2/userinfo', null, array( 'token' => $token, 'versioned' => false ) );
	if ( is_wp_error( $me ) ) {
		return $me;
	}
	if ( empty( $me['sub'] ) ) {
		return new WP_Error( 'bdb_li_me', 'شناسه عضو از لینکدین دریافت نشد؛ محصول «Sign In with LinkedIn using OpenID Connect» را به برنامه اضافه کنید.' );
	}
	bdb_update_settings( array(
		'li_token'        => $token,
		'li_expires'      => time() + (int) ( $data['expires_in'] ?? 60 * DAY_IN_SECONDS ),
		'li_person'       => 'urn:li:person:' . $me['sub'],
		'li_name'         => (string) ( $me['name'] ?? '' ),
		'li_picture'      => esc_url_raw( (string) ( $me['picture'] ?? '' ) ),
		'li_email'        => sanitize_email( (string) ( $me['email'] ?? '' ) ),
		'li_connected_at' => bdb_now_utc(),
	) );
	delete_option( 'bdb_li_expiry_mail' );
	return true;
}

/**
 * Forget the member connection.
 */
function bdb_li_disconnect() {
	bdb_update_settings( array( 'li_token' => '', 'li_expires' => 0, 'li_person' => '', 'li_name' => '', 'li_picture' => '', 'li_email' => '' ) );
}

/**
 * One API request.
 *
 * @param string     $method GET|POST|PUT|DELETE.
 * @param string     $path   Path under https://api.linkedin.com (e.g. /rest/posts) or a full URL.
 * @param array|null $body   JSON body.
 * @param array      $opt    token, versioned (default true), timeout, raw (return the response), headers.
 * @return array|WP_Error Decoded body (+ '_id' from x-restli-id), or the raw response with raw=true.
 */
function bdb_li_request( $method, $path, $body = null, $opt = array() ) {
	$opt     = wp_parse_args( $opt, array( 'token' => bdb_setting( 'li_token' ), 'versioned' => true, 'timeout' => 60, 'raw' => false, 'headers' => array() ) );
	$headers = array_merge( array(
		'Authorization' => 'Bearer ' . $opt['token'],
		'Accept'        => 'application/json',
	), $opt['headers'] );
	if ( $opt['versioned'] ) {
		$headers['LinkedIn-Version']          = preg_replace( '/\D/', '', (string) bdb_setting( 'li_version', '202608' ) );
		$headers['X-Restli-Protocol-Version'] = '2.0.0';
	}
	$args = array( 'method' => $method, 'timeout' => (int) $opt['timeout'], 'headers' => $headers );
	if ( null !== $body ) {
		$args['headers']['Content-Type'] = 'application/json';
		$args['body']                    = wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}
	$url = 0 === strpos( $path, 'http' ) ? $path : BDB_LI_API . $path;
	$res = bdb_li_http( $url, $args );
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'bdb_li_http', 'اتصال به لینکدین برقرار نشد (' . bdb_li_route_label() . '): ' . bdb_net_error( $res ) );
	}
	if ( $opt['raw'] ) {
		return $res;
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$raw  = (string) wp_remote_retrieve_body( $res );
	$data = json_decode( $raw, true );
	if ( $code >= 400 ) {
		return new WP_Error( 'bdb_li_' . $code, bdb_li_explain( $code, is_array( $data ) ? $data : array(), $raw ), array( 'code' => $code ) );
	}
	$data = is_array( $data ) ? $data : array();
	$id   = wp_remote_retrieve_header( $res, 'x-restli-id' ) ?: wp_remote_retrieve_header( $res, 'x-linkedin-id' );
	if ( $id ) {
		$data['_id'] = is_array( $id ) ? reset( $id ) : $id;
	}
	return $data;
}

/**
 * LinkedIn error → what to do, in Persian (with LinkedIn's own message).
 */
function bdb_li_explain( $code, $data, $raw ) {
	$msg = (string) ( $data['message'] ?? $data['error_description'] ?? '' );
	if ( '' === $msg && false !== stripos( $raw, '<html' ) ) {
		$msg = 'پاسخ HTML (احتمالاً صفحه فیلترینگ یا خطای رله/پروکسی)';
	}
	$why = array(
		401 => 'توکن لینکدین منقضی یا باطل شده؛ در تنظیمات دوباره «اتصال به لینکدین» را بزنید.',
		403 => 'لینکدین اجازه نداد. محصول «Share on LinkedIn» باید روی برنامه فعال باشد و اتصال بعد از افزودن آن تکرار شود.',
		422 => 'لینکدین محتوای پست را نپذیرفت (مثلاً پست تکراری، متن بیش از ۳۰۰۰ کاراکتر یا گزینه نظرسنجی بیش از ۳۰ کاراکتر).',
		426 => 'نسخه API لینکدین (LinkedIn-Version) دیگر پشتیبانی نمی‌شود؛ در تنظیمات نسخه جدیدتر (مثلاً ماه جاری به شکل YYYYMM) وارد کنید.',
		429 => 'سقف درخواست لینکدین پر شده؛ چند ساعت بعد دوباره امتحان کنید.',
	)[ $code ] ?? 'خطای لینکدین.';
	if ( 400 === $code && false !== stripos( $msg, 'version' ) ) {
		$why = bdb_li_explain( 426, array(), '' );
	}
	return $why . ' (HTTP ' . $code . ( '' !== $msg ? ' — ' . mb_substr( wp_strip_all_tags( $msg ), 0, 220 ) : '' ) . ')';
}

/**
 * Text in LinkedIn's «little text» format: reserved characters escaped,
 * hashtags turned into hashtag templates.
 */
function bdb_li_little( $text ) {
	$text = str_replace( "\r\n", "\n", (string) $text );
	$tags = array();
	$text = preg_replace_callback( '/(^|[\s(])#([\p{L}\p{N}_]+)/u', function ( $m ) use ( &$tags ) {
		$tags[] = $m[2];
		return $m[1] . "\x01" . ( count( $tags ) - 1 ) . "\x02";
	}, $text );
	$esc  = function ( $s ) {
		return preg_replace( '/([\\\\|{}@\[\]()<>#*_~])/u', '\\\\$1', $s );
	};
	$text = $esc( $text );
	return preg_replace_callback( "/\x01(\d+)\x02/", function ( $m ) use ( $tags, $esc ) {
		return '{hashtag|\#|' . $esc( $tags[ (int) $m[1] ] ) . '}';
	}, $text );
}

/**
 * Upload a file (PDF document or PNG image) and return its URN.
 *
 * @param string $kind documents|images.
 * @return string|WP_Error
 */
function bdb_li_upload( $kind, $file ) {
	if ( ! file_exists( $file ) ) {
		return new WP_Error( 'bdb_li_file', 'فایل رندرشده پست پیدا نشد؛ در استودیو یک بار «ساخت فایل» را بزنید.' );
	}
	$init = bdb_li_request( 'POST', '/rest/' . $kind . '?action=initializeUpload', array( 'initializeUploadRequest' => array( 'owner' => bdb_setting( 'li_person' ) ) ) );
	if ( is_wp_error( $init ) ) {
		return $init;
	}
	$url = (string) ( $init['value']['uploadUrl'] ?? '' );
	$urn = (string) ( $init['value'][ 'documents' === $kind ? 'document' : 'image' ] ?? '' );
	if ( '' === $url || '' === $urn ) {
		return new WP_Error( 'bdb_li_upload', 'لینکدین آدرس بارگذاری فایل را نداد.' );
	}
	$res = bdb_li_http( $url, array(
		'method'  => 'PUT',
		'timeout' => 120,
		'headers' => array( 'Authorization' => 'Bearer ' . bdb_setting( 'li_token' ), 'Content-Type' => 'application/octet-stream' ),
		'body'    => (string) file_get_contents( $file ), // phpcs:ignore WordPress.WP.AlternativeFunctions
	) );
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'bdb_li_upload', 'بارگذاری فایل در لینکدین ناموفق بود: ' . bdb_net_error( $res ) );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	if ( $code >= 300 ) {
		return new WP_Error( 'bdb_li_upload', 'بارگذاری فایل در لینکدین ناموفق بود (HTTP ' . $code . ').' );
	}
	return $urn;
}

/**
 * Processing status of an uploaded document/image: AVAILABLE, PROCESSING, PROCESSING_FAILED…
 */
function bdb_li_media_status( $urn ) {
	$kind = false !== strpos( $urn, ':document:' ) ? 'documents' : 'images';
	$res  = bdb_li_request( 'GET', '/rest/' . $kind . '/' . rawurlencode( $urn ) );
	return is_wp_error( $res ) ? 'UNKNOWN' : (string) ( $res['status'] ?? 'UNKNOWN' );
}

/**
 * Create the post. $content is the Posts API «content» part (or null for text).
 *
 * @return string|WP_Error The post URN.
 */
function bdb_li_create_post( $commentary, $content = null ) {
	$body = array(
		'author'                    => bdb_setting( 'li_person' ),
		'commentary'                => bdb_li_little( $commentary ),
		'visibility'                => 'PUBLIC',
		'distribution'              => array( 'feedDistribution' => 'MAIN_FEED', 'targetEntities' => array(), 'thirdPartyDistributionChannels' => array() ),
		'lifecycleState'            => 'PUBLISHED',
		'isReshareDisabledByAuthor' => false,
	);
	if ( $content ) {
		$body['content'] = $content;
	}
	$res = bdb_li_request( 'POST', '/rest/posts', $body );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	return (string) ( $res['_id'] ?? $res['id'] ?? '' ) ?: new WP_Error( 'bdb_li_post', 'لینکدین پست را ساخت اما شناسه آن را برنگرداند؛ پروفایل را بررسی کنید.' );
}

/**
 * Add a comment as the member (the «first comment»).
 *
 * @return true|WP_Error
 */
function bdb_li_comment( $post_urn, $text ) {
	$res = bdb_li_request( 'POST', '/rest/socialActions/' . rawurlencode( $post_urn ) . '/comments', array(
		'actor'   => bdb_setting( 'li_person' ),
		'object'  => $post_urn,
		'message' => array( 'text' => (string) $text ),
	) );
	return is_wp_error( $res ) ? $res : true;
}

/**
 * Connection test for the settings screen: route + token + identity.
 *
 * @return array{ok: bool, steps: array}
 */
function bdb_li_test() {
	$steps = array();
	$res   = bdb_li_http( 'https://www.linkedin.com/robots.txt', array( 'method' => 'GET', 'timeout' => 25 ) );
	$steps[] = is_wp_error( $res )
		? array( 'ok' => false, 'name' => 'دسترسی سرور به لینکدین', 'msg' => bdb_net_error( $res ) . ' — مسیر فعلی: ' . bdb_li_route_label() )
		: array( 'ok' => true, 'name' => 'دسترسی سرور به لینکدین', 'msg' => 'برقرار است؛ مسیر: ' . bdb_li_route_label() );
	if ( '' === (string) bdb_setting( 'li_client_id' ) || '' === (string) bdb_setting( 'li_client_secret' ) ) {
		$steps[] = array( 'ok' => false, 'name' => 'برنامه لینکدین', 'msg' => 'Client ID و Client Secret وارد نشده‌اند.' );
	}
	if ( '' !== (string) bdb_setting( 'li_token' ) ) {
		$me      = bdb_li_request( 'GET', '/v2/userinfo', null, array( 'versioned' => false, 'timeout' => 30 ) );
		$steps[] = is_wp_error( $me )
			? array( 'ok' => false, 'name' => 'حساب متصل', 'msg' => $me->get_error_message() )
			: array( 'ok' => true, 'name' => 'حساب متصل', 'msg' => ( $me['name'] ?? '' ) . ' — توکن ' . bdb_num( max( 0, (int) bdb_li_days_left() ) ) . ' روز دیگر اعتبار دارد.' );
	} else {
		$steps[] = array( 'ok' => false, 'name' => 'حساب متصل', 'msg' => 'هنوز به حساب لینکدین وصل نشده‌اید.' );
	}
	return array( 'ok' => ! in_array( false, array_column( $steps, 'ok' ), true ), 'steps' => $steps );
}
