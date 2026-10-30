<?php
/**
 * Quality check before publishing: LinkedIn rules, the brand's «never use»
 * list and the privacy guard. Errors block publishing; warnings only advise.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

/**
 * Check a post.
 *
 * @return array{score: int, blocking: bool, checks: array[]}
 */
function bdb_quality( $p ) {
	$checks = array();
	$add    = function ( $level, $label, $msg = '' ) use ( &$checks ) {
		$checks[] = array( 'level' => $level, 'label' => $label, 'msg' => $msg );
	};
	$body  = trim( (string) $p['body'] );
	$full  = bdb_commentary( $p );
	$len   = mb_strlen( $full );
	$lines = preg_split( '/\n/', $body );
	$first = trim( (string) ( $lines[0] ?? '' ) );
	$two   = trim( $first . ' ' . trim( (string) ( $lines[1] ?? '' ) ) );
	$fa    = 'fa' === $p['lang'];

	if ( '' === $body ) {
		$add( 'error', 'متن پست خالی است' );
		return array( 'score' => 0, 'blocking' => true, 'checks' => $checks );
	}
	// Hook.
	$words = count( preg_split( '/\s+/u', $first, -1, PREG_SPLIT_NO_EMPTY ) );
	if ( $words > 14 || mb_strlen( $first ) > 110 ) {
		$add( 'warn', 'خط اول طولانی است', sprintf( '%s کلمه؛ قلاب قوی معمولاً زیر ۱۲ کلمه است.', bdb_num( $words ) ) );
	} else {
		$add( 'ok', 'قلاب کوتاه و قابل دیدن', sprintf( '%s کلمه', bdb_num( $words ) ) );
	}
	if ( mb_strlen( $two ) > 210 ) {
		$add( 'warn', 'دو خط اول از «…بیشتر» عبور می‌کند', 'حدود ۲۰۰ کاراکتر اول قبل از «…see more» دیده می‌شود.' );
	}
	if ( preg_match( '/^(hi|hello|i\'?m (so )?(excited|thrilled|happy)|سلام|دوستان عزیز)/iu', $first ) ) {
		$add( 'warn', 'شروع با سلام یا «خوشحالم»', 'مستقیم با ایده یا عدد شروع کنید.' );
	}
	// Length.
	$range = 'text' === $p['format'] ? array( 700, 2000 ) : array( 250, 1300 );
	if ( $len > 3000 ) {
		$add( 'error', 'بیش از ۳۰۰۰ کاراکتر', 'لینکدین پست بلندتر را نمی‌پذیرد.' );
	} elseif ( $len < $range[0] || $len > $range[1] ) {
		$add( 'warn', 'طول متن', sprintf( '%s کاراکتر؛ بازه پیشنهادی این قالب %s تا %s است.', bdb_num( $len ), bdb_num( $range[0] ), bdb_num( $range[1] ) ) );
	} else {
		$add( 'ok', 'طول مناسب', sprintf( '%s کاراکتر', bdb_num( $len ) ) );
	}
	// Links.
	if ( preg_match( '#https?://|www\.#i', $body ) ) {
		$add( 'warn', 'لینک داخل متن', 'لینک داخل پست دیده‌شدن را کم می‌کند؛ آن را به کامنت اول منتقل کنید.' );
	}
	// Hashtags.
	$tags = preg_match_all( '/#[\p{L}\p{N}_]+/u', (string) $p['hashtags'] );
	if ( $tags < 3 || $tags > 5 ) {
		$add( 'warn', 'تعداد هشتگ', sprintf( '%s هشتگ؛ ۳ تا ۵ هشتگ مرتبط پیشنهاد می‌شود.', bdb_num( $tags ) ) );
	}
	// Readability.
	$walls = 0;
	foreach ( preg_split( '/\n\s*\n/', $body ) as $para ) {
		if ( mb_strlen( trim( $para ) ) > 320 ) {
			++$walls;
		}
	}
	if ( $walls ) {
		$add( 'warn', 'پاراگراف بلند', sprintf( '%s پاراگراف بیش از ۳ خط دارد؛ در موبایل دیوار متن می‌شود.', bdb_num( $walls ) ) );
	}
	$emojis = preg_match_all( '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $body );
	if ( $emojis > 6 ) {
		$add( 'warn', 'ایموجی زیاد', sprintf( '%s ایموجی', bdb_num( $emojis ) ) );
	}
	// CTA.
	if ( 'question' === $p['cta_type'] && ! preg_match( '/[?؟]/u', implode( "\n", array_slice( $lines, -4 ) ) ) ) {
		$add( 'warn', 'سؤال پایانی پیدا نشد', 'CTA این پست «سؤال برای کامنت» است.' );
	}
	if ( preg_match( '/comment\s+["“]?yes|like if you|type\s+["“]?yes|کامنت\s+بذارید\s+بله/iu', $body ) ) {
		$add( 'warn', 'Engagement bait', 'لینکدین این جمله‌ها را کم‌ارزش می‌کند.' );
	}
	// Brand voice.
	$hit = array();
	foreach ( bdb_lines( bdb_brand()['avoid'] ) as $phrase ) {
		if ( mb_strlen( $phrase ) > 3 && false !== mb_stripos( $body, $phrase ) ) {
			$hit[] = $phrase;
		}
	}
	if ( $hit ) {
		$add( 'warn', 'عبارت ممنوع برند', implode( '، ', $hit ) );
	}
	// Persian spacing.
	if ( $fa && preg_match_all( '/(^|\s)(می|نمی)\s(?=[\x{0600}-\x{06FF}])/u', $body ) > 1 ) {
		$add( 'warn', 'نیم‌فاصله', '«می کنیم» را «می‌کنیم» بنویسید.' );
	}
	// Privacy.
	$scan = $full . "\n" . $p['first_comment'] . "\n" . wp_json_encode( $p['slides'], JSON_UNESCAPED_UNICODE ) . "\n" . wp_json_encode( $p['meta']['poll'] ?? array(), JSON_UNESCAPED_UNICODE );
	$priv = bdb_privacy_hits( $scan );
	if ( $priv ) {
		$add( 'error', 'نام کارفرما یا سایت در پست', 'این موارد باید ناشناس شوند: ' . implode( '، ', array_slice( $priv, 0, 6 ) ) );
	} else {
		$add( 'ok', 'ناشناس‌سازی', 'نام و دامنه هیچ کارفرمایی در پست نیست.' );
	}
	if ( preg_match( '/\[(client|page)\]/i', $scan ) ) {
		$add( 'error', 'جای‌نگهدار باقی مانده', '[client] یا [page] را با توصیف خنثی جایگزین کنید.' );
	}
	// Format specifics.
	if ( 'carousel' === $p['format'] ) {
		$n = count( $p['slides'] );
		if ( $n < 4 || $n > 12 ) {
			$add( $n < 2 ? 'error' : 'warn', 'تعداد اسلاید', sprintf( '%s اسلاید؛ ۶ تا ۱۰ پیشنهاد می‌شود.', bdb_num( $n ) ) );
		}
		$heavy = 0;
		foreach ( $p['slides'] as $s ) {
			$txt = $s['title'] . ' ' . $s['body'] . ' ' . implode( ' ', $s['items'] ) . ' ' . implode( ' ', $s['left_items'] ) . ' ' . implode( ' ', $s['right_items'] );
			if ( count( preg_split( '/\s+/u', trim( $txt ), -1, PREG_SPLIT_NO_EMPTY ) ) > 55 ) {
				++$heavy;
			}
		}
		if ( $heavy ) {
			$add( 'warn', 'اسلاید شلوغ', sprintf( '%s اسلاید بیش از ۵۵ کلمه دارد.', bdb_num( $heavy ) ) );
		}
	}
	if ( in_array( $p['format'], array( 'carousel', 'image' ), true ) ) {
		$file = $p['asset'] ? bdb_asset_path( $p['asset'] ) : '';
		if ( ! $file || ! file_exists( $file ) || ( $p['meta']['asset_hash'] ?? '' ) !== bdb_slides_hash( $p ) ) {
			$add( 'error', 'فایل ' . ( 'carousel' === $p['format'] ? 'PDF' : 'تصویر' ) . ' ساخته نشده یا قدیمی است', 'در استودیو «ساخت فایل» را بزنید (بعد از هر تغییر اسلایدها).' );
		} else {
			$add( 'ok', 'فایل آماده', 'carousel' === $p['format'] ? 'PDF اسلایدها ساخته شده است.' : 'تصویر ساخته شده است.' );
		}
	}
	if ( 'poll' === $p['format'] ) {
		$poll = (array) ( $p['meta']['poll'] ?? array() );
		$opts = array_filter( (array) ( $poll['options'] ?? array() ), 'strlen' );
		if ( '' === trim( (string) ( $poll['question'] ?? '' ) ) || count( $opts ) < 2 || count( $opts ) > 4 ) {
			$add( 'error', 'نظرسنجی ناقص', 'یک سؤال و ۲ تا ۴ گزینه لازم است.' );
		}
		foreach ( $opts as $o ) {
			if ( mb_strlen( $o ) > 30 ) {
				$add( 'error', 'گزینه نظرسنجی بلند', '«' . $o . '» بیش از ۳۰ کاراکتر است.' );
			}
		}
	}
	$err   = count( array_filter( $checks, function ( $c ) {
		return 'error' === $c['level'];
	} ) );
	$warn  = count( array_filter( $checks, function ( $c ) {
		return 'warn' === $c['level'];
	} ) );
	return array( 'score' => max( 0, 100 - 25 * $err - 7 * $warn ), 'blocking' => $err > 0, 'checks' => $checks );
}

/**
 * Hash of what the rendered file depends on (slides + language + brand colours).
 */
function bdb_slides_hash( $p ) {
	$b = bdb_brand();
	return md5( wp_json_encode( array( $p['slides'], $p['lang'], $p['format'], $b['brand_color'], $b['brand_color2'], $b['brand_dark'], $b['name'], $b['name_fa'], $b['headline'], $b['headline_fa'], $b['photo_url'], $b['handle'] ) ) );
}
