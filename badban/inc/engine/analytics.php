<?php
/**
 * Analytics: the profile's growth and what works.
 *
 * LinkedIn gives personal-profile analytics through its API only to approved
 * partners, so Badban reads the file LinkedIn itself exports (profile › Analytics
 * › Content/Followers › Export, XLSX): daily impressions and engagements,
 * new followers and the follower total, top posts and demographics. Per-post
 * numbers can also be typed on the post (or come from the same file when the
 * post can be matched by its publish date).
 *
 * The insights (best format, pillar, hook, CTA, language, weekday) go back to
 * the writer, so every new post leans on what already worked.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

/**
 * Import a LinkedIn analytics export (XLSX, or the CSV of one sheet).
 *
 * @return array|WP_Error {days, followers, posts, demographics, msg}
 */
function bdb_import_analytics( $path, $name ) {
	$ext = strtolower( pathinfo( (string) $name, PATHINFO_EXTENSION ) );
	$sheets = array();
	if ( 'xlsx' === $ext ) {
		foreach ( bdb_xlsx_sheet_names( $path ) as $sn ) {
			$rows = bdb_xlsx_read( $path, $sn );
			if ( ! is_wp_error( $rows ) ) {
				$sheets[ strtoupper( trim( $sn ) ) ] = $rows;
			}
		}
	} else {
		$rows = bdb_read_table_file( $path, $name );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$sheets['CSV'] = $rows;
	}
	if ( ! $sheets ) {
		return new WP_Error( 'bdb_import', 'فایل خوانده نشد؛ فایل XLSX خروجی آنالیتیکس لینکدین را بارگذاری کنید.' );
	}
	$out = array( 'days' => 0, 'followers' => 0, 'posts' => 0, 'demographics' => 0 );
	foreach ( $sheets as $sn => $rows ) {
		$kind = bdb_sheet_kind( $sn, $rows );
		if ( 'engagement' === $kind ) {
			$out['days'] += bdb_import_engagement( $rows );
		} elseif ( 'followers' === $kind ) {
			$out['followers'] += bdb_import_followers( $rows );
		} elseif ( 'top' === $kind ) {
			$out['posts'] += bdb_import_top_posts( $rows );
		} elseif ( 'demographics' === $kind ) {
			$out['demographics'] += bdb_import_demographics( $rows );
		} elseif ( 'discovery' === $kind ) {
			bdb_import_discovery( $rows );
		}
	}
	if ( ! array_sum( $out ) ) {
		return new WP_Error( 'bdb_import', 'در فایل داده قابل استفاده‌ای پیدا نشد. از پروفایل لینکدین › Analytics › Content (یا Followers) › Export فایل XLSX بگیرید.' );
	}
	update_option( 'bdb_last_import', array( 'at' => bdb_now_utc(), 'result' => $out, 'file' => sanitize_file_name( $name ) ), false );
	$out['msg'] = sprintf( 'ورود انجام شد: %s روز آمار نمایش و تعامل، %s روز دنبال‌کننده، %s پست برتر، %s ردیف مخاطب‌شناسی.', bdb_num( $out['days'] ), bdb_num( $out['followers'] ), bdb_num( $out['posts'] ), bdb_num( $out['demographics'] ) );
	return $out;
}

/**
 * What a sheet holds (by its name, else by its headers).
 */
function bdb_sheet_kind( $name, $rows ) {
	$map = array( 'ENGAGEMENT' => 'engagement', 'FOLLOWERS' => 'followers', 'TOP POSTS' => 'top', 'DEMOGRAPHICS' => 'demographics', 'DISCOVERY' => 'discovery' );
	if ( isset( $map[ $name ] ) ) {
		return $map[ $name ];
	}
	$head = strtolower( implode( '|', array_map( 'strval', (array) ( $rows[0] ?? array() ) ) ) . '|' . implode( '|', array_map( 'strval', (array) ( $rows[1] ?? array() ) ) ) . '|' . implode( '|', array_map( 'strval', (array) ( $rows[2] ?? array() ) ) ) );
	if ( false !== strpos( $head, 'new followers' ) ) {
		return 'followers';
	}
	if ( false !== strpos( $head, 'post url' ) ) {
		return 'top';
	}
	if ( false !== strpos( $head, 'impressions' ) && false !== strpos( $head, 'date' ) ) {
		return 'engagement';
	}
	if ( false !== strpos( $head, 'demographic' ) ) {
		return 'demographics';
	}
	return '';
}

/**
 * Find the header row (contains all the needed words) and the column of each.
 *
 * @return array{row: int, cols: array}|null
 */
function bdb_find_header( $rows, $words ) {
	foreach ( array_slice( $rows, 0, 8, true ) as $ri => $r ) {
		$cols = array();
		foreach ( (array) $r as $ci => $cell ) {
			$c = strtolower( trim( (string) $cell ) );
			foreach ( $words as $key => $w ) {
				if ( ! isset( $cols[ $key ] ) && $c === $w ) {
					$cols[ $key ] = $ci;
				}
			}
		}
		if ( count( $cols ) === count( $words ) ) {
			return array( 'row' => $ri, 'cols' => $cols );
		}
	}
	return null;
}

/**
 * Date cell → Y-m-d (Excel serial, m/d/Y, Y-m-d).
 */
function bdb_cell_date( $v ) {
	if ( is_numeric( $v ) && $v > 20000 && $v < 80000 ) {
		return gmdate( 'Y-m-d', (int) round( ( $v - 25569 ) * DAY_IN_SECONDS ) );
	}
	$v = trim( (string) $v );
	if ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})#', $v, $m ) ) {
		return sprintf( '%04d-%02d-%02d', $m[3], $m[1], $m[2] );
	}
	$t = strtotime( $v );
	return $t ? gmdate( 'Y-m-d', $t ) : '';
}

/**
 * Number cell (1,234 / 1.2K).
 */
function bdb_cell_num( $v ) {
	if ( is_numeric( $v ) ) {
		return (int) round( $v );
	}
	$v = str_replace( array( ',', ' ' ), '', bdb_norm( (string) $v ) );
	if ( preg_match( '/^([\d.]+)k$/i', $v, $m ) ) {
		return (int) round( $m[1] * 1000 );
	}
	return (int) $v;
}

/**
 * ENGAGEMENT sheet: Date | Impressions | Engagements.
 */
function bdb_import_engagement( $rows ) {
	$h = bdb_find_header( $rows, array( 'date' => 'date', 'imp' => 'impressions', 'eng' => 'engagements' ) );
	if ( ! $h ) {
		return 0;
	}
	$n = 0;
	foreach ( array_slice( $rows, $h['row'] + 1 ) as $r ) {
		$day = bdb_cell_date( $r[ $h['cols']['date'] ] ?? '' );
		if ( '' === $day ) {
			continue;
		}
		bdb_stat_put( $day, array( 'impressions' => bdb_cell_num( $r[ $h['cols']['imp'] ] ?? 0 ), 'engagements' => bdb_cell_num( $r[ $h['cols']['eng'] ] ?? 0 ) ) );
		++$n;
	}
	return $n;
}

/**
 * FOLLOWERS sheet: «Total followers on {date}: N», then Date | New followers.
 * The daily total is rebuilt backwards from the final total.
 */
function bdb_import_followers( $rows ) {
	$total = 0;
	foreach ( array_slice( $rows, 0, 4 ) as $r ) {
		foreach ( (array) $r as $ci => $cell ) {
			if ( false !== stripos( (string) $cell, 'total followers' ) ) {
				$total = bdb_cell_num( $r[ $ci + 1 ] ?? preg_replace( '/\D/', '', (string) $cell ) );
			}
		}
	}
	$h = bdb_find_header( $rows, array( 'date' => 'date', 'new' => 'new followers' ) );
	if ( ! $h ) {
		return 0;
	}
	$days = array();
	foreach ( array_slice( $rows, $h['row'] + 1 ) as $r ) {
		$day = bdb_cell_date( $r[ $h['cols']['date'] ] ?? '' );
		if ( '' !== $day ) {
			$days[ $day ] = bdb_cell_num( $r[ $h['cols']['new'] ] ?? 0 );
		}
	}
	krsort( $days );
	$running = $total;
	foreach ( $days as $day => $new ) {
		bdb_stat_put( $day, array_filter( array( 'new_followers' => $new, 'followers' => $total ? $running : 0 ), function ( $v, $k ) {
			return 'new_followers' === $k || $v > 0;
		}, ARRAY_FILTER_USE_BOTH ) );
		$running -= $new;
	}
	return count( $days );
}

/**
 * TOP POSTS sheet: two side-by-side tables (by engagements, by impressions).
 * Posts are matched to Badban's posts by their LinkedIn id or publish date.
 */
function bdb_import_top_posts( $rows ) {
	$found = array();
	foreach ( array_slice( $rows, 0, 6, true ) as $ri => $r ) {
		foreach ( (array) $r as $ci => $cell ) {
			if ( 'post url' === strtolower( trim( (string) $cell ) ) ) {
				$metric = strtolower( trim( (string) ( $r[ $ci + 2 ] ?? '' ) ) );
				$found[] = array( 'row' => $ri, 'url' => $ci, 'date' => $ci + 1, 'val' => $ci + 2, 'metric' => false !== strpos( $metric, 'impression' ) ? 'impressions' : 'engagements' );
			}
		}
	}
	$posts = array();
	foreach ( $found as $t ) {
		foreach ( array_slice( $rows, $t['row'] + 1 ) as $r ) {
			$url = trim( (string) ( $r[ $t['url'] ] ?? '' ) );
			if ( '' === $url ) {
				continue;
			}
			$posts[ $url ]                 = $posts[ $url ] ?? array( 'url' => $url, 'date' => bdb_cell_date( $r[ $t['date'] ] ?? '' ) );
			$posts[ $url ][ $t['metric'] ] = bdb_cell_num( $r[ $t['val'] ] ?? 0 );
		}
	}
	update_option( 'bdb_top_posts', array_values( $posts ), false );
	$mine = bdb_posts( array( 'status' => 'published', 'limit' => 500 ) );
	foreach ( $posts as $tp ) {
		$match = null;
		foreach ( $mine as $p ) {
			$id = preg_replace( '/\D/', '', (string) $p['li_urn'] );
			if ( '' !== $id && false !== strpos( $tp['url'], $id ) ) {
				$match = $p;
				break;
			}
		}
		if ( ! $match && $tp['date'] ) {
			$same = array_values( array_filter( $mine, function ( $p ) use ( $tp ) {
				return substr( (string) $p['published_at'], 0, 10 ) === $tp['date'];
			} ) );
			$match = 1 === count( $same ) ? $same[0] : null;
		}
		if ( $match ) {
			$m = $match['metrics'];
			foreach ( array( 'impressions', 'engagements' ) as $k ) {
				if ( isset( $tp[ $k ] ) && ( 'impressions' === $k || empty( $m['reactions'] ) ) ) {
					$m[ $k ] = $tp[ $k ];
				}
			}
			$m['li_url']     = $tp['url'];
			$m['updated_at'] = bdb_now_utc();
			$m['source']     = $m['source'] ?? 'import';
			bdb_post_save( array( 'metrics' => $m ), $match['id'] );
		}
	}
	return count( $posts );
}

/**
 * DEMOGRAPHICS sheet: Top Demographics | Value | Percentage.
 */
function bdb_import_demographics( $rows ) {
	$h = bdb_find_header( $rows, array( 'cat' => 'top demographics', 'val' => 'value', 'pct' => 'percentage' ) );
	if ( ! $h ) {
		return 0;
	}
	$out = array();
	foreach ( array_slice( $rows, $h['row'] + 1 ) as $r ) {
		$cat = trim( (string) ( $r[ $h['cols']['cat'] ] ?? '' ) );
		$val = trim( (string) ( $r[ $h['cols']['val'] ] ?? '' ) );
		if ( '' === $cat || '' === $val ) {
			continue;
		}
		$pct = $r[ $h['cols']['pct'] ] ?? 0;
		$pct = is_numeric( $pct ) ? (float) $pct : (float) str_replace( array( '%', '<' ), '', (string) $pct ) / 100;
		$out[ $cat ][] = array( $val, $pct > 1 ? $pct / 100 : $pct );
	}
	update_option( 'bdb_demographics', $out, false );
	return array_sum( array_map( 'count', $out ) );
}

/**
 * DISCOVERY sheet: overall impressions and members reached of the period.
 */
function bdb_import_discovery( $rows ) {
	$out = array();
	foreach ( $rows as $r ) {
		$k = strtolower( trim( (string) ( $r[0] ?? '' ) ) );
		if ( in_array( $k, array( 'impressions', 'members reached' ), true ) ) {
			$out[ $k ] = bdb_cell_num( $r[1] ?? 0 );
		} elseif ( 'overall performance' === $k ) {
			$out['period'] = (string) ( $r[1] ?? '' );
		}
	}
	if ( $out ) {
		update_option( 'bdb_discovery', $out, false );
	}
}

/**
 * Performance groups of published posts with numbers.
 *
 * @return array by => [value => {n, er, imp}]
 */
function bdb_performance() {
	$posts = array_filter( bdb_posts( array( 'status' => 'published', 'limit' => 500 ) ), function ( $p ) {
		return null !== bdb_post_er( $p );
	} );
	$out = array( 'format' => array(), 'pillar' => array(), 'hook_type' => array(), 'cta_type' => array(), 'lang' => array(), 'weekday' => array() );
	foreach ( $posts as $p ) {
		$p['weekday'] = bdb_local( $p['published_at'], $p['lang'] ) ? bdb_local( $p['published_at'], $p['lang'] )->format( 'w' ) : '';
		foreach ( array_keys( $out ) as $by ) {
			$v = (string) $p[ $by ];
			if ( '' === $v ) {
				continue;
			}
			$out[ $by ][ $v ] = $out[ $by ][ $v ] ?? array( 'n' => 0, 'er' => 0, 'imp' => 0 );
			++$out[ $by ][ $v ]['n'];
			$out[ $by ][ $v ]['er']  += bdb_post_er( $p );
			$out[ $by ][ $v ]['imp'] += (int) ( $p['metrics']['impressions'] ?? 0 );
		}
	}
	foreach ( $out as &$groups ) {
		foreach ( $groups as &$g ) {
			$g['er']  = $g['er'] / $g['n'];
			$g['imp'] = (int) round( $g['imp'] / $g['n'] );
		}
		unset( $g );
		uasort( $groups, function ( $a, $b ) {
			return $b['er'] <=> $a['er'];
		} );
	}
	unset( $groups );
	return $out;
}

/**
 * Human labels of a performance group value.
 */
function bdb_perf_label( $by, $v ) {
	switch ( $by ) {
		case 'format':
			return bdb_formats()[ $v ][0] ?? $v;
		case 'pillar':
			return bdb_pillar_label( $v );
		case 'hook_type':
			return bdb_hook_types()[ $v ][0] ?? $v;
		case 'cta_type':
			return bdb_cta_types()[ $v ][0] ?? $v;
		case 'lang':
			return 'fa' === $v ? 'فارسی' : 'انگلیسی';
		case 'weekday':
			return bdb_weekdays()[ (int) $v ] ?? $v;
	}
	return $v;
}

/**
 * Insight lines for the writer (English): the best group of each kind with at least 2 posts.
 *
 * @return string[]
 */
function bdb_insights_for_prompt() {
	if ( isset( $GLOBALS['bdb_insights_cache'] ) ) {
		return $GLOBALS['bdb_insights_cache'];
	}
	$out  = array();
	$keys = array( 'format' => 'format', 'pillar' => 'pillar', 'hook_type' => 'hook style', 'cta_type' => 'call to action' );
	foreach ( bdb_performance() as $by => $groups ) {
		if ( ! isset( $keys[ $by ] ) ) {
			continue;
		}
		$ok = array_filter( $groups, function ( $g ) {
			return $g['n'] >= 2;
		} );
		if ( count( $ok ) < 2 ) {
			continue;
		}
		$best  = array_key_first( $ok );
		$worst = array_key_last( $ok );
		$out[] = sprintf( 'Best %s: %s (avg engagement rate %.1f%% over %d posts) vs weakest: %s (%.1f%%).', $keys[ $by ], $best, 100 * $ok[ $best ]['er'], $ok[ $best ]['n'], $worst, 100 * $ok[ $worst ]['er'] );
	}
	$GLOBALS['bdb_insights_cache'] = $out;
	return $out;
}

/**
 * Persian insight cards for the analytics screen.
 *
 * @return string[]
 */
function bdb_insights_fa() {
	$out    = array();
	$titles = array( 'format' => 'قالب', 'pillar' => 'ستون محتوا', 'hook_type' => 'نوع قلاب', 'cta_type' => 'نوع CTA', 'lang' => 'زبان', 'weekday' => 'روز انتشار' );
	foreach ( bdb_performance() as $by => $groups ) {
		$ok = array_filter( $groups, function ( $g ) {
			return $g['n'] >= 2;
		} );
		if ( count( $ok ) < 2 ) {
			continue;
		}
		$best  = array_key_first( $ok );
		$worst = array_key_last( $ok );
		$ratio = $ok[ $worst ]['er'] > 0 ? $ok[ $best ]['er'] / $ok[ $worst ]['er'] : 0;
		$out[] = sprintf( 'بهترین %s: «%s» با نرخ تعامل %s (%s پست)%s.', $titles[ $by ], bdb_perf_label( $by, $best ), bdb_pct( $ok[ $best ]['er'], 1 ), bdb_num( $ok[ $best ]['n'] ), $ratio >= 1.3 ? sprintf( '؛ %s برابر «%s»', bdb_num( $ratio, 1 ), bdb_perf_label( $by, $worst ) ) : '' );
	}
	return $out;
}

/**
 * Consistency: published posts per week (last 12 weeks, Monday-based) and the current streak.
 *
 * @return array{weeks: array, streak: int, goal: int, this_week: int}
 */
function bdb_consistency() {
	$goal  = max( 1, (int) bdb_schedule()['weekly'] );
	$need  = max( 1, (int) ceil( $goal * 0.6 ) ); // A week «counts» at 60% of the goal (3 of 5).
	$weeks = array();
	$mon   = strtotime( 'monday this week', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
	for ( $i = 11; $i >= 0; $i-- ) {
		$from          = $mon - $i * WEEK_IN_SECONDS;
		$weeks[ $from ] = 0;
	}
	foreach ( bdb_posts( array( 'status' => 'published', 'from' => gmdate( 'Y-m-d H:i:s', $mon - 12 * WEEK_IN_SECONDS ), 'limit' => 500 ) ) as $p ) {
		$t = strtotime( (string) $p['published_at'] ) + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
		foreach ( array_reverse( array_keys( $weeks ) ) as $w ) {
			if ( $t >= $w ) {
				++$weeks[ $w ];
				break;
			}
		}
	}
	$streak = 0;
	$keys   = array_reverse( array_keys( $weeks ) );
	foreach ( $keys as $i => $w ) {
		if ( $weeks[ $w ] >= $need ) {
			++$streak;
		} elseif ( 0 !== $i ) { // The running week may still fill up.
			break;
		}
	}
	return array( 'weeks' => $weeks, 'streak' => $streak, 'goal' => $goal, 'need' => $need, 'this_week' => $weeks[ $mon ] ?? 0 );
}
