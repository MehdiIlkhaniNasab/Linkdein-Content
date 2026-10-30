<?php
/**
 * Publishing calendar and the automatic plan.
 *
 * Slots: weekday + time per language, in that language's audience timezone.
 * Default: English Tue/Wed/Thu 08:30 (international audience), Persian Sat/Mon
 * 09:00 Tehran — five posts a week, never both languages on the same day.
 *
 * «Plan the next weeks» fills every free slot: the best idea of the bank for
 * the pillar that is most behind its target share, a format mix (no two equal
 * formats in a row), the CTA rotation; a Persian slot takes the Persian
 * version of a recent English post when there is one, otherwise a new idea.
 * Each job is written by the AI one at a time (REST ticks from the browser,
 * which also renders the carousel PDFs), and lands in «ready» for approval.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

/**
 * Weekday names (PHP «w»: 0 = Sunday).
 */
function bdb_weekdays() {
	return array( 6 => 'شنبه', 0 => 'یکشنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه' );
}

/**
 * Default weekly slots.
 */
function bdb_schedule_defaults() {
	return array(
		'en'     => array( array( 'day' => 2, 'time' => '08:30' ), array( 'day' => 3, 'time' => '08:30' ), array( 'day' => 4, 'time' => '08:30' ) ),
		'fa'     => array( array( 'day' => 6, 'time' => '09:00' ), array( 'day' => 1, 'time' => '20:00' ) ),
		'weekly' => 5, // Goal: posts per week (both languages).
	);
}

/**
 * Weekly slots (stored over defaults).
 */
function bdb_schedule() {
	$s = get_option( 'bdb_schedule', array() );
	return wp_parse_args( is_array( $s ) ? $s : array(), bdb_schedule_defaults() );
}

/**
 * Upcoming slot times of a language (UTC MySQL), from now, for $days days.
 *
 * @return string[]
 */
function bdb_slot_times( $lang, $days = 14, $from = null ) {
	$tz    = bdb_tz( $lang );
	$now   = new DateTime( 'now', $tz );
	$start = $from ? new DateTime( $from, new DateTimeZone( 'UTC' ) ) : clone $now;
	$start->setTimezone( $tz );
	$out = array();
	foreach ( (array) ( bdb_schedule()[ $lang ] ?? array() ) as $slot ) {
		$d = clone $start;
		$d->setTime( 0, 0 );
		for ( $i = 0; $i <= $days; $i++ ) {
			if ( (int) $d->format( 'w' ) === (int) $slot['day'] ) {
				list( $h, $m ) = array_map( 'intval', explode( ':', (string) $slot['time'] . ':0' ) );
				$t = ( clone $d )->setTime( $h, $m );
				if ( $t > $now && $t >= $start ) {
					$out[] = ( clone $t )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
				}
			}
			$d->modify( '+1 day' );
		}
	}
	sort( $out );
	return $out;
}

/**
 * Is a slot already taken by a post of that language (within 6 hours)?
 */
function bdb_slot_taken( $utc, $lang ) {
	global $wpdb;
	$t = strtotime( $utc );
	return (bool) $wpdb->get_var( $wpdb->prepare(
		'SELECT id FROM ' . bdb_table( 'posts' ) . " WHERE lang = %s AND status IN ('draft','ready','scheduled','publishing','published') AND scheduled_at BETWEEN %s AND %s LIMIT 1", // phpcs:ignore
		$lang,
		gmdate( 'Y-m-d H:i:s', $t - 6 * HOUR_IN_SECONDS ),
		gmdate( 'Y-m-d H:i:s', $t + 6 * HOUR_IN_SECONDS )
	) );
}

/**
 * Free slots of a language in the next $days days.
 *
 * @return string[]
 */
function bdb_free_slots( $lang, $days = 14 ) {
	return array_values( array_filter( bdb_slot_times( $lang, $days ), function ( $t ) use ( $lang ) {
		return ! bdb_slot_taken( $t, $lang );
	} ) );
}

/**
 * First free slot of a language (searching up to 8 weeks), or null.
 */
function bdb_next_free_slot( $lang ) {
	$free = bdb_free_slots( $lang, 56 );
	return $free[0] ?? null;
}

/**
 * Pillar shares: target weight vs. what was published / planned in the last 30 days.
 *
 * @return array key => [target %, actual %, count]
 */
function bdb_pillar_balance() {
	$posts = bdb_posts( array( 'status' => array( 'draft', 'ready', 'scheduled', 'published' ), 'from' => gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ), 'limit' => 500 ) );
	$count = array();
	foreach ( $posts as $p ) {
		$count[ $p['pillar'] ] = ( $count[ $p['pillar'] ] ?? 0 ) + 1;
	}
	$total   = max( 1, array_sum( $count ) );
	$weights = array_sum( array_map( function ( $p ) {
		return (float) $p[2];
	}, bdb_pillars() ) ) ?: 1;
	$out = array();
	foreach ( bdb_pillars() as $k => $p ) {
		$out[ $k ] = array( round( 100 * $p[2] / $weights ), round( 100 * ( $count[ $k ] ?? 0 ) / $total ), $count[ $k ] ?? 0 );
	}
	return $out;
}

/**
 * Build the plan of the next $weeks weeks: one job per free slot.
 *
 * @return array{jobs: array, msg: string}
 */
function bdb_plan_build( $weeks = 2 ) {
	$days    = max( 7, min( 28, 7 * (int) $weeks ) );
	$jobs    = array();
	$balance = bdb_pillar_balance();
	$deficit = array();
	foreach ( $balance as $k => $b ) {
		$deficit[ $k ] = max( 0, $b[0] - $b[1] );
	}
	$ideas = bdb_ideas( array( 'status' => 'new', 'limit' => 200 ) );
	if ( count( $ideas ) < 6 ) {
		bdb_harvest_all();
		bdb_harvest_library();
		$ideas = bdb_ideas( array( 'status' => 'new', 'limit' => 200 ) );
	}
	$used      = array();
	$last_fmt  = array();
	$recent_en = bdb_posts( array( 'lang' => 'en', 'status' => array( 'ready', 'scheduled', 'published' ), 'order' => 'time', 'limit' => 20 ) );
	$en_slots  = bdb_free_slots( 'en', $days );
	$fa_slots  = bdb_free_slots( 'fa', $days );
	foreach ( $en_slots as $slot ) {
		$best = null;
		$bs   = -1;
		foreach ( $ideas as $i ) {
			if ( isset( $used[ $i['id'] ] ) ) {
				continue;
			}
			$s = $i['score'] * ( 1 + ( $deficit[ $i['pillar'] ] ?? 0 ) / 25 );
			if ( $s > $bs ) {
				$bs   = $s;
				$best = $i;
			}
		}
		if ( ! $best ) {
			$jobs[] = array( 'kind' => 'ai_idea', 'lang' => 'en', 'slot' => $slot );
			continue;
		}
		$used[ $best['id'] ] = true;
		if ( isset( $deficit[ $best['pillar'] ] ) ) {
			$deficit[ $best['pillar'] ] = max( 0, $deficit[ $best['pillar'] ] - 12 );
		}
		$fmt = bdb_suggest_format( (string) ( $best['payload']['framework'] ?? 'lesson' ) );
		if ( count( $last_fmt ) >= 1 && end( $last_fmt ) === $fmt ) {
			$fmt = array( 'carousel' => 'text', 'text' => 'carousel', 'image' => 'text', 'poll' => 'text' )[ $fmt ];
		}
		if ( count( $jobs ) && 0 === count( $jobs ) % 6 ) {
			$fmt = 'poll'; // One poll every ~6 posts: cheap reach and audience research.
		}
		$last_fmt[] = $fmt;
		$jobs[]     = array( 'key' => 'j' . count( $jobs ), 'kind' => 'idea', 'lang' => 'en', 'slot' => $slot, 'idea' => $best['id'], 'format' => $fmt, 'title' => $best['title'] );
	}
	// Persian: the Persian version of an English post published / planned at least 2 days earlier
	// (an existing one first, else one of the English jobs of this plan).
	$pairable = array_values( array_filter( $recent_en, function ( $p ) {
		return ! $p['pair_id'];
	} ) );
	$en_jobs  = $jobs;
	foreach ( $fa_slots as $slot ) {
		$pick = null;
		foreach ( $pairable as $k => $p ) {
			$t = strtotime( (string) ( $p['scheduled_at'] ?: $p['published_at'] ) );
			if ( $t && $t <= strtotime( $slot ) - 2 * DAY_IN_SECONDS ) {
				$pick = $p;
				unset( $pairable[ $k ] );
				break;
			}
		}
		if ( $pick ) {
			$jobs[] = array( 'kind' => 'pair', 'lang' => 'fa', 'slot' => $slot, 'post' => $pick['id'], 'title' => 'نسخه فارسی: ' . $pick['title'] );
			continue;
		}
		foreach ( $en_jobs as $k => $j ) {
			if ( 'idea' === $j['kind'] && strtotime( $j['slot'] ) <= strtotime( $slot ) - 2 * DAY_IN_SECONDS ) {
				$pick = $j;
				unset( $en_jobs[ $k ] );
				break;
			}
		}
		if ( $pick ) {
			$jobs[] = array( 'kind' => 'pair', 'lang' => 'fa', 'slot' => $slot, 'from_job' => $pick['key'], 'title' => 'نسخه فارسی: ' . $pick['title'] );
			continue;
		}
		$best = null;
		foreach ( $ideas as $i ) {
			if ( ! isset( $used[ $i['id'] ] ) ) {
				$best = $i;
				break;
			}
		}
		if ( $best ) {
			$used[ $best['id'] ] = true;
			$jobs[]              = array( 'kind' => 'idea', 'lang' => 'fa', 'slot' => $slot, 'idea' => $best['id'], 'format' => bdb_suggest_format( (string) ( $best['payload']['framework'] ?? 'lesson' ) ), 'title' => $best['title'] );
		} else {
			$jobs[] = array( 'kind' => 'ai_idea', 'lang' => 'fa', 'slot' => $slot );
		}
	}
	usort( $jobs, function ( $a, $b ) {
		// English first (Persian pairs need their English original), then by time.
		return array( 'fa' === $a['lang'], $a['slot'] ) <=> array( 'fa' === $b['lang'], $b['slot'] );
	} );
	update_option( 'bdb_plan_job', array( 'jobs' => $jobs, 'done' => array(), 'started' => bdb_now_utc() ), false );
	return array(
		'jobs' => $jobs,
		'msg'  => $jobs ? sprintf( '%s پست برای %s هفته آینده برنامه‌ریزی شد؛ نوشتن شروع می‌شود…', bdb_num( count( $jobs ) ), bdb_num( $days / 7 ) ) : 'همه جایگاه‌های این بازه پر هستند.',
	);
}

/**
 * Work one job of the plan.
 *
 * @return array{done: bool, left: int, post: int, msg: string, error?: string}
 */
function bdb_plan_tick() {
	$state = get_option( 'bdb_plan_job', array() );
	$jobs  = (array) ( $state['jobs'] ?? array() );
	if ( ! $jobs ) {
		return array( 'done' => true, 'left' => 0, 'post' => 0, 'msg' => 'برنامه کامل شد.' );
	}
	$job = array_shift( $jobs );
	bdb_raise_limits( 300 );
	$id = 0;
	if ( 'pair' === $job['kind'] ) {
		$src = isset( $job['from_job'] ) ? (int) ( $state['map'][ $job['from_job'] ] ?? 0 ) : (int) $job['post'];
		$id  = $src ? bdb_write_counterpart( $src ) : new WP_Error( 'bdb_plan', 'پست انگلیسی این نسخه فارسی نوشته نشد؛ جایگاه خالی ماند.' );
	} else {
		$idea = (int) ( $job['idea'] ?? 0 );
		if ( 'ai_idea' === $job['kind'] ) {
			$n = bdb_ideas_ai( '', 6 );
			if ( is_wp_error( $n ) ) {
				$id = $n;
			} else {
				$fresh = bdb_ideas( array( 'status' => 'new', 'source' => 'ai', 'limit' => 1 ) );
				$idea  = $fresh ? $fresh[0]['id'] : 0;
			}
		}
		if ( ! is_wp_error( $id ) ) {
			$id = $idea ? bdb_post_from_idea( $idea, array_filter( array( 'lang' => $job['lang'], 'format' => $job['format'] ?? '' ) ) ) : new WP_Error( 'bdb_plan', 'ایده‌ای برای این جایگاه پیدا نشد.' );
		}
	}
	$state['jobs'] = $jobs;
	if ( is_wp_error( $id ) ) {
		update_option( 'bdb_plan_job', $state, false );
		return array( 'done' => ! $jobs, 'left' => count( $jobs ), 'post' => 0, 'msg' => $id->get_error_message(), 'error' => $id->get_error_message() );
	}
	bdb_post_save( array( 'scheduled_at' => $job['slot'] ), $id );
	bdb_post_mark_ready( $id );
	$state['done'][] = $id;
	if ( isset( $job['key'] ) ) {
		$state['map'][ $job['key'] ] = $id;
	}
	update_option( 'bdb_plan_job', $state, false );
	$p = bdb_post( $id );
	return array(
		'done'         => ! $jobs,
		'left'         => count( $jobs ),
		'post'         => $id,
		'needs_render' => in_array( $p['format'], array( 'carousel', 'image' ), true ),
		'msg'          => sprintf( 'نوشته شد: %s (%s)', $p['title'], bdb_when( $job['slot'], $p['lang'], false ) ),
	);
}

/**
 * After writing / editing: a post without blocking errors becomes «ready»
 * (needs approval) or «scheduled» (approval off) when it has a time.
 */
function bdb_post_mark_ready( $id ) {
	$p = bdb_post( $id );
	if ( ! $p || in_array( $p['status'], array( 'published', 'publishing' ), true ) ) {
		return;
	}
	$q      = bdb_quality( $p );
	$status = $q['blocking'] ? 'draft' : 'ready';
	if ( 'ready' === $status && $p['scheduled_at'] && ! (int) bdb_setting( 'require_approval', 1 ) ) {
		$status = 'scheduled';
	}
	bdb_post_save( array( 'status' => $status, 'meta' => array_merge( $p['meta'], array( 'quality' => $q['score'] ) ) ), $id );
}
