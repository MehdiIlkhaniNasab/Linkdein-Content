<?php
/**
 * Idea harvest: the posts come out of the real work.
 *
 *   سکان (Sokan)       measured actions with a result (→ case studies), the lessons
 *                       its impact engine learned, Focus goals, the month's work
 *                       (→ behind the scenes) and the most frequent audit findings
 *                       across sites (→ «mistakes I keep seeing»).
 *   قطب‌نما (Ghotbnama) keyword research runs: raw keywords → clusters → pillars →
 *                       calendar (→ content strategy posts).
 *   فانوس (Fanus)       the latest client report's headline and highlights (→ results).
 *   Library            evergreen ideas per pillar, written for the brand's audience.
 *   Recycle            old posts that performed well, to re-publish in a new format.
 *
 * Client data is anonymised before it is stored or sent to the AI: project
 * names, domains, brand terms and competitors of every project in the three
 * tools are replaced with neutral words, and the quality check blocks a post
 * that still contains one of them.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

/**
 * Which sibling tools are installed on this WordPress?
 */
function bdb_sources_status() {
	return array(
		'sokan'     => function_exists( 'sap_projects' ),
		'ghotbnama' => function_exists( 'qtb_projects' ) && function_exists( 'qtb_data_get' ),
		'fanus'     => function_exists( 'sr_projects' ) && function_exists( 'sr_project_reports' ),
	);
}

/**
 * Private terms of all clients (names, domains, brands, competitors) + the brand's own list.
 *
 * @return string[] longest first
 */
function bdb_private_terms() {
	$cached = get_transient( 'bdb_private_terms' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$terms = bdb_lines( (string) ( bdb_brand()['private_terms'] ?? '' ) );
	$add   = function ( $t ) use ( &$terms ) {
		$t = trim( wp_strip_all_tags( (string) $t ) );
		if ( mb_strlen( $t ) >= 3 ) {
			$terms[] = $t;
		}
	};
	$add_domain = function ( $url ) use ( $add ) {
		$h = bdb_host( $url );
		if ( '' !== $h ) {
			$add( $h );
			$add( preg_replace( '/\.[a-z.]+$/', '', $h ) ); // «digikala» from digikala.com.
		}
	};
	$src = bdb_sources_status();
	if ( $src['sokan'] ) {
		foreach ( sap_projects( '' ) as $p ) {
			$add( $p['name'] );
			$add_domain( $p['site_url'] );
			foreach ( (array) $p['brand_terms'] as $t ) {
				$add( $t );
			}
			foreach ( (array) $p['competitors'] as $c ) {
				$add_domain( is_array( $c ) ? ( $c[0] ?? '' ) : $c );
				if ( is_array( $c ) && isset( $c[1] ) ) {
					$add( $c[1] );
				}
			}
		}
	}
	if ( $src['ghotbnama'] ) {
		foreach ( qtb_projects() as $q ) {
			$add( $q['name'] );
			$add_domain( $q['site_url'] );
			foreach ( array_merge( (array) ( $q['cfg']['brands'] ?? array() ), (array) ( $q['cfg']['competitors'] ?? array() ) ) as $t ) {
				false !== strpos( (string) $t, '.' ) ? $add_domain( $t ) : $add( $t );
			}
		}
	}
	if ( $src['fanus'] ) {
		foreach ( sr_projects() as $f ) {
			$add( $f['title'] ?? '' );
			$add_domain( $f['domain'] ?? '' );
		}
	}
	$terms = array_values( array_unique( array_filter( $terms ) ) );
	usort( $terms, function ( $a, $b ) {
		return mb_strlen( $b ) <=> mb_strlen( $a );
	} );
	set_transient( 'bdb_private_terms', $terms, HOUR_IN_SECONDS );
	return $terms;
}

/**
 * Replace client-identifying text with neutral words.
 */
function bdb_anonymize( $text ) {
	$text = preg_replace( '#https?://[^\s<>"\')]+#iu', '[page]', (string) $text );
	foreach ( bdb_private_terms() as $t ) {
		$text = preg_replace( '/' . preg_quote( $t, '/' ) . '/iu', '[client]', $text );
	}
	return $text;
}

/**
 * Private terms found in a text (quality check before publishing).
 *
 * @return string[]
 */
function bdb_privacy_hits( $text ) {
	$hits = array();
	$norm = bdb_norm( $text );
	foreach ( bdb_private_terms() as $t ) {
		$k = bdb_norm( $t );
		if ( mb_strlen( $k ) >= 3 && false !== mb_strpos( $norm, $k ) ) {
			$hits[] = $t;
		}
	}
	return array_values( array_unique( $hits ) );
}

/**
 * Run every harvester. Returns [source => added count].
 */
function bdb_harvest_all() {
	delete_transient( 'bdb_private_terms' );
	$out = array();
	$src = bdb_sources_status();
	if ( $src['sokan'] ) {
		$out['sokan'] = bdb_harvest_sokan();
	}
	if ( $src['ghotbnama'] ) {
		$out['ghotbnama'] = bdb_harvest_ghotbnama();
	}
	if ( $src['fanus'] ) {
		$out['fanus'] = bdb_harvest_fanus();
	}
	$out['recycle'] = bdb_harvest_recycle();
	update_option( 'bdb_last_harvest', array( 'at' => bdb_now_utc(), 'added' => $out ), false );
	return $out;
}

/**
 * Sokan: case studies, lessons, focuses, the month's work, common findings.
 */
function bdb_harvest_sokan() {
	$added    = 0;
	$projects = sap_projects();
	$n        = 0;
	$findings = array();
	$month    = array( 'projects' => 0, 'done' => 0, 'hours' => 0, 'new' => 0, 'updated' => 0, 'contexts' => array() );
	foreach ( $projects as $p ) {
		++$n;
		$pid   = $p['id'];
		$niche = bdb_anonymize( (string) ( $p['options']['business_goal'] ?? '' ) );
		$who   = '' !== trim( $niche ) ? mb_substr( $niche, 0, 140 ) : 'a client website';
		// Measured actions that grew.
		if ( function_exists( 'sap_impact_summary' ) ) {
			$s = sap_impact_summary( $pid );
			foreach ( $s['best'] as $i ) {
				$net = (int) ( $i['net_change'] ?? 0 );
				if ( $net < 10 ) {
					continue;
				}
				$story = function_exists( 'sap_impact_story' ) ? sap_impact_story( $pid, $i ) : '';
				$label = function_exists( 'sap_context_label' ) ? sap_context_label( $i['context'] ?? '' ) : (string) ( $i['context'] ?? '' );
				$added += (int) (bool) bdb_idea_add( array(
					'title'      => sprintf( 'کیس استادی: %s ← %s٪ رشد خالص کلیک', $label, bdb_num( $net ) ),
					'angle'      => 'What we changed, why, and the measured result (net of site-wide change). Turn it into a lesson others can apply.',
					'pillar'     => 'results',
					'source'     => 'sokan',
					'source_ref' => 'impact:' . $pid . ':' . md5( (string) ( $i['ref'] ?? $i['url'] . $i['done'] ) ),
					'score'      => min( 98, 70 + (int) round( $net / 4 ) ),
					'payload'    => array(
						'framework' => 'case_study',
						'client'    => $who,
						'work_type' => $label,
						'story'     => bdb_anonymize( wp_strip_all_tags( $story ) ),
						'net'       => $net,
						'clicks'    => array( 'before_per_day' => round( ( $i['before']['clicks'] ?? 0 ) / max( 1, $i['before']['days'] ?? 1 ), 1 ), 'after_per_day' => round( ( $i['after']['clicks'] ?? 0 ) / max( 1, $i['after']['days'] ?? 1 ), 1 ) ),
						'position'  => array( 'before' => $i['kw_before'] ?? null, 'after' => $i['kw_after'] ?? null ),
						'days'      => (int) ( $i['after_days'] ?? 0 ),
					),
				) );
			}
			foreach ( $s['lessons'] as $lesson ) {
				$added += (int) (bool) bdb_idea_add( array(
					'title'      => 'درس از داده: ' . mb_substr( bdb_anonymize( $lesson ), 0, 120 ),
					'angle'      => 'One data-backed lesson about which type of SEO work moves the needle and which does not.',
					'pillar'     => 'strategy',
					'source'     => 'sokan',
					'source_ref' => 'lesson:' . $pid . ':' . md5( $lesson ),
					'score'      => 66,
					'payload'    => array( 'framework' => 'lesson', 'client' => $who, 'lesson' => bdb_anonymize( $lesson ) ),
				) );
			}
		}
		// Focus goals.
		if ( function_exists( 'sap_focuses' ) ) {
			foreach ( sap_focuses( $pid, true ) as $f ) {
				if ( $f['kpi_target'] <= 0 ) {
					continue;
				}
				$added += (int) (bool) bdb_idea_add( array(
					'title'      => 'چطور برای یک بخش سایت Focus و هدف قابل اندازه‌گیری می‌گذاریم',
					'angle'      => 'Why we never “do SEO on the whole site”: picking one focus, a baseline, a target KPI and a deadline, and running the week around it.',
					'pillar'     => 'strategy',
					'source'     => 'sokan',
					'source_ref' => 'focus:' . $f['id'],
					'score'      => 62,
					'payload'    => array(
						'framework' => 'framework',
						'client'    => $who,
						'focus'     => bdb_anonymize( $f['name'] ),
						'metric'    => (string) ( $f['kpi_metric'] ?? '' ),
						'baseline'  => $f['kpi_baseline'],
						'target'    => $f['kpi_target'],
						'deadline'  => (string) ( $f['deadline'] ?? '' ),
					),
				) );
			}
		}
		// Last month's work (aggregated across projects below).
		if ( function_exists( 'sap_month_report' ) && function_exists( 'sap_period_current' ) && function_exists( 'sap_period_shift' ) ) {
			$prev = sap_period_shift( sap_period_current(), -1 );
			$m    = sap_month_report( $p, $prev );
			if ( ! empty( $m['plan']['tasks_done'] ) ) {
				++$month['projects'];
				$month['done']    += (int) $m['plan']['tasks_done'];
				$month['hours']   += (float) $m['plan']['hours_done'];
				$month['new']     += count( $m['content']['new'] ?? array() );
				$month['updated'] += count( $m['content']['updated'] ?? array() );
				foreach ( (array) $m['plan']['done'] as $t ) {
					$c = function_exists( 'sap_context_label' ) ? sap_context_label( $t['category'] ?? '' ) : (string) ( $t['category'] ?? '' );
					$month['contexts'][ $c ] = ( $month['contexts'][ $c ] ?? 0 ) + 1;
				}
				$month['period'] = $m['period']['label'] ?? $prev;
				$month['key']    = $prev;
			}
		}
		// Audit findings (counted across sites).
		if ( function_exists( 'sap_findings' ) ) {
			foreach ( sap_findings( $pid, array( 'limit' => 400 ) ) as $fi ) {
				$k = (string) ( $fi['detector'] ?? '' );
				if ( '' === $k ) {
					continue;
				}
				$findings[ $k ] = $findings[ $k ] ?? array( 'sites' => array(), 'count' => 0, 'title' => bdb_anonymize( (string) $fi['title'] ), 'context' => (string) ( $fi['context'] ?? '' ) );
				$findings[ $k ]['sites'][ $pid ] = true;
				++$findings[ $k ]['count'];
			}
		}
	}
	if ( $month['projects'] ) {
		arsort( $month['contexts'] );
		$added += (int) (bool) bdb_idea_add( array(
			'title'      => sprintf( 'پشت صحنه %s: %s کار انجام‌شده در %s پروژه', $month['period'], bdb_num( $month['done'] ), bdb_num( $month['projects'] ) ),
			'angle'      => 'How an SEO team actually spends a month: what we prioritised, the split of work types, what we would do differently.',
			'pillar'     => 'team',
			'source'     => 'sokan',
			'source_ref' => 'month:' . $month['key'],
			'score'      => 64,
			'payload'    => array( 'framework' => 'behind', 'month' => array_merge( $month, array( 'contexts' => array_slice( $month['contexts'], 0, 6, true ) ) ) ),
		) );
	}
	if ( $findings ) {
		uasort( $findings, function ( $a, $b ) {
			return count( $b['sites'] ) <=> count( $a['sites'] ) ?: $b['count'] <=> $a['count'];
		} );
		$top  = array_slice( $findings, 0, 7, true );
		$list = array();
		foreach ( $top as $f ) {
			$list[] = array( 'issue' => $f['title'], 'sites' => count( $f['sites'] ), 'occurrences' => $f['count'], 'area' => $f['context'] );
		}
		$added += (int) (bool) bdb_idea_add( array(
			'title'      => sprintf( '%s ایرادی که تقریباً در همه سایت‌ها پیدا می‌کنیم', bdb_num( count( $list ) ) ),
			'angle'      => 'The SEO problems I keep finding across the sites we audit, why they cost revenue, and the fix for each.',
			'pillar'     => 'technical',
			'source'     => 'sokan',
			'source_ref' => 'findings:' . gmdate( 'Y-m' ),
			'score'      => 78,
			'payload'    => array( 'framework' => 'mistakes', 'sites_audited' => $n, 'issues' => $list ),
		) );
	}
	return $added;
}

/**
 * Ghotbnama: keyword research runs.
 */
function bdb_harvest_ghotbnama() {
	$added = 0;
	foreach ( qtb_projects() as $q ) {
		$clusters = (array) qtb_data_get( $q['id'], 'clusters', array() );
		$pillars  = (array) qtb_data_get( $q['id'], 'pillars', array() );
		if ( ! $clusters || ! $pillars ) {
			continue;
		}
		$kws     = (array) qtb_data_get( $q['id'], 'keywords', array() );
		$removed = (array) qtb_data_get( $q['id'], 'removed', array() );
		$cal     = (array) qtb_data_get( $q['id'], 'calendar', array() );
		$input   = (array) qtb_data_get( $q['id'], 'input', array() );
		$pages   = array_count_values( array_map( function ( $c ) {
			return (string) ( $c['page'] ?? 'blog' );
		}, $clusters ) );
		$p1      = count( array_filter( $clusters, function ( $c ) {
			return 'P1' === ( $c['priority'] ?? '' );
		} ) );
		$quick   = count( array_filter( $clusters, function ( $c ) {
			return ! empty( $c['quick_win'] );
		} ) );
		$facts   = array(
			'raw_keywords'     => count( $input ) ?: count( $kws ) + count( $removed ),
			'kept_keywords'    => count( $kws ),
			'removed_keywords' => count( $removed ),
			'clusters'         => count( $clusters ),
			'pillars'          => count( $pillars ),
			'page_types'       => $pages,
			'p1_clusters'      => $p1,
			'quick_wins'       => $quick,
			'calendar_articles' => count( (array) ( $cal['articles'] ?? array() ) ),
			'niche'            => bdb_anonymize( (string) ( $q['cfg']['niche'] ?? '' ) ),
		);
		$built = (string) qtb_data_time( $q['id'], 'clusters' );
		$added += (int) (bool) bdb_idea_add( array(
			'title'      => sprintf( 'از %s کلمه خام تا %s پیلار و %s کلاستر: کیورد ریسرچ فروش‌محور', bdb_num( $facts['raw_keywords'] ), bdb_num( $facts['pillars'] ), bdb_num( $facts['clusters'] ) ),
			'angle'      => 'How we turn a raw keyword list into a sales-focused site structure: cleaning, SERP-based clustering, intent, page type, priority by revenue.',
			'pillar'     => 'content',
			'source'     => 'ghotbnama',
			'source_ref' => 'run:' . $q['id'] . ':' . substr( md5( $built ), 0, 8 ),
			'score'      => 76,
			'payload'    => array( 'framework' => 'case_study', 'facts' => $facts ),
		) );
		if ( $facts['removed_keywords'] > 0 && $facts['raw_keywords'] > 0 ) {
			$added += (int) (bool) bdb_idea_add( array(
				'title'      => sprintf( 'چرا %s٪ کلمات کلیدی را دور می‌ریزیم', bdb_num( round( 100 * $facts['removed_keywords'] / max( 1, $facts['raw_keywords'] ) ) ) ),
				'angle'      => 'Contrarian: most keyword lists are full of competitor brands, marketplaces and zero-intent terms. Cutting them is the strategy.',
				'pillar'     => 'content',
				'source'     => 'ghotbnama',
				'source_ref' => 'removed:' . $q['id'] . ':' . substr( md5( $built ), 0, 8 ),
				'score'      => 72,
				'payload'    => array( 'framework' => 'contrarian', 'facts' => $facts ),
			) );
		}
	}
	return $added;
}

/**
 * Fanus: the latest client report of each project.
 */
function bdb_harvest_fanus() {
	$added = 0;
	foreach ( sr_projects() as $f ) {
		$posts = sr_project_reports( $f['id'], 1 );
		if ( ! $posts ) {
			continue;
		}
		$r = sr_report( $posts[0]->ID );
		$e = $r['editorial'] ?? array();
		$text = trim( implode( "\n", array_filter( array( $e['headline'] ?? '', $e['highlights'] ?? '', $e['next_focus'] ?? '' ) ) ) );
		if ( '' === $text ) {
			continue;
		}
		$added += (int) (bool) bdb_idea_add( array(
			'title'      => 'نتیجه ماهانه یک پروژه: ' . mb_substr( bdb_anonymize( (string) ( $e['headline'] ?: $r['title'] ) ), 0, 110 ),
			'angle'      => 'A month of SEO in one story: strategy → action → result → next focus. Share the process, not only the numbers.',
			'pillar'     => 'results',
			'source'     => 'fanus',
			'source_ref' => 'report:' . $r['id'],
			'score'      => 70,
			'payload'    => array(
				'framework' => 'before_after',
				'report'    => bdb_anonymize( mb_substr( $text, 0, 1500 ) ),
				'summary'   => bdb_anonymize( mb_substr( wp_strip_all_tags( (string) ( $e['summary'] ?? '' ) ), 0, 1200 ) ),
				'period'    => $r['period'],
			),
		) );
	}
	return $added;
}

/**
 * Recycle: posts older than 60 days that beat the median engagement rate.
 */
function bdb_harvest_recycle() {
	$posts = array_filter( bdb_posts( array( 'status' => 'published', 'limit' => 300 ) ), function ( $p ) {
		return null !== bdb_post_er( $p ) && strtotime( (string) $p['published_at'] ) < time() - 60 * DAY_IN_SECONDS;
	} );
	if ( count( $posts ) < 4 ) {
		return 0;
	}
	$ers = array_map( 'bdb_post_er', $posts );
	sort( $ers );
	$cut   = $ers[ (int) floor( count( $ers ) * 0.7 ) ];
	$added = 0;
	foreach ( $posts as $p ) {
		if ( bdb_post_er( $p ) < $cut ) {
			continue;
		}
		$added += (int) (bool) bdb_idea_add( array(
			'title'      => 'بازنشر در قالب تازه: ' . mb_substr( $p['title'], 0, 120 ),
			'angle'      => 'This post performed in the top 30%. Rewrite it with a new hook and a different format (e.g. text → carousel), updated with anything new.',
			'pillar'     => $p['pillar'],
			'source'     => 'recycle',
			'source_ref' => 'post:' . $p['id'],
			'score'      => 74,
			'payload'    => array( 'framework' => 'lesson', 'original' => mb_substr( $p['body'], 0, 2500 ), 'format' => $p['format'], 'er' => round( bdb_post_er( $p ) * 100, 2 ) ),
		) );
	}
	return $added;
}

/**
 * Evergreen library: strong ideas per pillar for the brand's audience.
 *
 * @return array[] title, angle, pillar, framework
 */
function bdb_library() {
	return array(
		array( 'The SEO strategy I would build for a new e-commerce site in its first 90 days', 'Phase by phase: research and structure, technical base, money pages, content engine, authority.', 'strategy', 'how_i' ),
		array( 'Why most SEO strategies fail before the first article is written', 'They optimise for traffic, not revenue: no priority by money pages, no focus, no measurement.', 'strategy', 'contrarian' ),
		array( 'How I prioritise 200 SEO tasks when a team has 40 hours a month', 'Impact × confidence ÷ effort, one focus at a time, weekly due dates, measuring every action.', 'strategy', 'framework' ),
		array( 'Compass → Helm → Lantern: the 3-tool system behind every SEO project I run', 'Strategy (clustering), execution (action plan) and reporting (client report) — why each step needs its own tool.', 'strategy', 'framework' ),
		array( 'SEO KPIs a CEO actually cares about', 'From rankings to revenue: organic sales, money-page visibility, share of first-page volume.', 'strategy', 'checklist' ),
		array( '7 technical SEO issues I find on almost every website', 'Redirect chains, orphan pages, wrong canonicals, faceted crawl traps, missing schema, slow LCP, broken internal links.', 'technical', 'mistakes' ),
		array( 'A safe website migration checklist (from someone who has seen them go wrong)', 'Before, during and after: URL mapping, redirects, crawl comparison, monitoring.', 'technical', 'checklist' ),
		array( 'Crawl budget is not your problem — your internal links are', 'Contrarian take on crawl budget for small and mid-size sites.', 'technical', 'contrarian' ),
		array( 'Schema markup that actually changes how you look in Google', 'Product, FAQ, Breadcrumb, Organization — what matters and what is noise.', 'technical', 'checklist' ),
		array( 'Keyword research is not a list — it is a site structure', 'From a keyword list to pillars, landing pages and articles, using the top-10 results.', 'content', 'framework' ),
		array( 'Two keywords, one page: how SERP overlap decides your content plan', 'If 4 of the top 10 URLs are shared, it is one page. Examples and edge cases.', 'content', 'lesson' ),
		array( 'Content refresh beats new content (most of the time)', 'Which pages to update first, what to change, how to measure the uplift.', 'content', 'myth_fact' ),
		array( 'A content brief template my writers love', 'Intent, winning angle, must-cover topics, headings, FAQ, internal links, CTA.', 'content', 'checklist' ),
		array( 'Links are not the strategy; reasons to link are', 'Digital PR, data assets and partnerships instead of buying links.', 'offpage', 'contrarian' ),
		array( 'How we pick off-page targets with a fixed budget', 'Which pages need authority, which sites, what anchor mix, how we measure.', 'offpage', 'framework' ),
		array( 'How I run a weekly SEO team meeting in 30 minutes', 'Agenda, the one dashboard we look at, decisions, owners, due dates.', 'team', 'behind' ),
		array( 'Delegating SEO without losing quality', 'Checklists, examples, review loops and why every task needs a “why”.', 'team', 'lesson' ),
		array( 'What I learned managing SEO for multiple clients at once', 'Focus, templates, automation and saying no.', 'team', 'story' ),
		array( 'How I explain SEO results to a client who only cares about sales', 'Strategy → action → result → next focus, in one page.', 'team', 'framework' ),
		array( 'Before / after: what one month of focused SEO changed for a client', 'An anonymised story with numbers.', 'results', 'before_after' ),
		array( 'Why every specialist needs to learn to sell', 'Programmers, SEOs and designers: explaining value is part of the job.', 'career', 'contrarian' ),
		array( 'How AI changed my SEO workflow (and what it did not change)', 'Where AI saves hours and where human judgement still decides.', 'career', 'lesson' ),
		array( 'From SEO specialist to SEO manager: what I had to unlearn', 'Doing vs. deciding; tasks vs. outcomes; tools vs. systems.', 'career', 'story' ),
		array( 'GEO: how to show up in AI answers, not only in Google', 'Entities, citations, clear answers and brand mentions.', 'strategy', 'checklist' ),
	);
}

/**
 * Add the library to the idea bank (once per item).
 */
function bdb_harvest_library() {
	$added = 0;
	foreach ( bdb_library() as $i => $l ) {
		$added += (int) (bool) bdb_idea_add( array(
			'title'      => $l[0],
			'angle'      => $l[1],
			'pillar'     => $l[2],
			'source'     => 'library',
			'source_ref' => 'lib:' . md5( $l[0] ),
			'score'      => 55,
			'payload'    => array( 'framework' => $l[3] ),
		) );
	}
	return $added;
}
