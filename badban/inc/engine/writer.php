<?php
/**
 * The writer: LinkedIn posts, carousels, image cards and polls with the AI.
 *
 * Every prompt is built from: the brand (positioning, audience, voice, samples),
 * the LinkedIn playbook below, what performed best so far (analytics), the last
 * posts (so ideas and hooks do not repeat) and the idea itself (with the
 * anonymised facts from Sokan / Ghotbnama / Fanus).
 *
 * English and Persian are written separately: the Persian version is a native
 * rewrite for the Persian-speaking audience, not a translation, and is
 * published on a different day.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

/**
 * The LinkedIn playbook the writer follows (English; the model writes in the post's language).
 */
function bdb_playbook( $lang = 'en' ) {
	$rules = array(
		'HOOK: the first 2 lines decide everything (about 200 characters show before “…see more”, ~140 on mobile). Line 1 ≤ 12 words, specific, no greeting, no “I am excited to share”. Line 2 creates curiosity or tension.',
		'ONE idea per post. Teach something specific; use real numbers and concrete examples; show the process and the why.',
		'FORMAT: short paragraphs of 1–2 lines with a blank line between them; lists with “→”, “•” or numbers; no walls of text; at most 2–3 emojis, only when they add meaning.',
		'LENGTH: text posts 900–1,600 characters (never above 2,800). Carousel/image/poll captions 500–1,000 characters: they tease the document, they do not repeat it.',
		'NO LINKS in the post body (LinkedIn reduces reach). Links, booking URL and resources go in the first comment.',
		'CTA: exactly one call to action at the end, matching the requested CTA type. Questions must be specific and easy to answer.',
		'HASHTAGS: 3–5 relevant hashtags, mixing broad and niche; they are returned separately, never inside the body.',
		'CREDIBILITY: never invent results, clients, numbers or quotes. Use only the facts given; if there are none, speak from process and experience without fake numbers.',
		'PRIVACY: never name a client, a client website, a brand or a competitor. Use neutral descriptions (“an e-commerce client in home appliances”). Placeholders like [client] or [page] must be rewritten into neutral words, never printed.',
		'NO engagement bait (“comment YES”, “like if you agree”), no clickbait that the post does not deliver, no hashtags or links in the hook.',
		'CAROUSEL: 6–10 slides. Slide 1 = cover with the promise (big title ≤ 9 words). Slide 2 = the problem or context. Then one point per slide (≤ 30 words each). Last slide = summary + the CTA. Every slide must make sense alone.',
		'IMAGE CARD: exactly 1 slide that carries one strong idea (a quote, a number, a short checklist).',
		'POLL: question ≤ 140 characters; 2–4 options, each ≤ 30 characters, mutually exclusive.',
	);
	if ( 'fa' === $lang ) {
		$rules[] = 'PERSIAN: write natural, fluent, professional Persian as an Iranian SEO manager would write on LinkedIn — not a translation. Use correct half-spaces (نیم‌فاصله) e.g. «می‌کنیم», «کلمه‌ها». Keep common SEO terms the way Iranian professionals use them (سئو، کیورد، لینک‌سازی، Core Web Vitals). Address Iranian business owners and SEO specialists. Use Persian digits.';
	} else {
		$rules[] = 'ENGLISH: plain international business English for a global audience (many readers are non-native speakers): short words, active voice, no idioms that do not travel.';
	}
	return "LINKEDIN PLAYBOOK:\n- " . implode( "\n- ", $rules );
}

/**
 * JSON schema of one slide (flat, so every gateway's strict mode accepts it).
 */
function bdb_slide_schema() {
	$s = array( 'type' => 'string' );
	$l = array( 'type' => 'array', 'items' => array( 'type' => 'string' ) );
	return array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( 'type', 'kicker', 'title', 'body', 'items', 'stat_value', 'stat_label', 'left_title', 'left_items', 'right_title', 'right_items' ),
		'properties'           => array(
			'type'        => array( 'type' => 'string', 'enum' => array( 'cover', 'point', 'list', 'steps', 'stat', 'compare', 'quote', 'cta' ) ),
			'kicker'      => $s,
			'title'       => $s,
			'body'        => $s,
			'items'       => $l,
			'stat_value'  => $s,
			'stat_label'  => $s,
			'left_title'  => $s,
			'left_items'  => $l,
			'right_title' => $s,
			'right_items' => $l,
		),
	);
}

/**
 * JSON schema of a written post.
 */
function bdb_post_schema() {
	$s = array( 'type' => 'string' );
	$l = array( 'type' => 'array', 'items' => array( 'type' => 'string' ) );
	return array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( 'title', 'body', 'alt_hooks', 'first_comment', 'hashtags', 'doc_title', 'slides', 'poll_question', 'poll_options', 'notes' ),
		'properties'           => array(
			'title'         => $s,
			'body'          => $s,
			'alt_hooks'     => $l,
			'first_comment' => $s,
			'hashtags'      => $l,
			'doc_title'     => $s,
			'slides'        => array( 'type' => 'array', 'items' => bdb_slide_schema() ),
			'poll_question' => $s,
			'poll_options'  => $l,
			'notes'         => $s,
		),
	);
}

/**
 * The system prompt.
 */
function bdb_writer_system( $lang ) {
	return "You are the ghostwriter and LinkedIn strategist of the author below. You write posts that build a premium personal brand and start conversations with the target readers. You follow the playbook strictly.\n\n"
		. bdb_brand_prompt( $lang ) . "\n\n" . bdb_playbook( $lang );
}

/**
 * Context lines: what performed, what was published lately.
 */
function bdb_writer_context( $lang ) {
	$out      = array();
	$insights = bdb_insights_for_prompt();
	if ( $insights ) {
		$out[] = "WHAT PERFORMED BEST ON THIS PROFILE SO FAR (lean into it):\n- " . implode( "\n- ", $insights );
	}
	$recent = bdb_posts( array( 'status' => array( 'published', 'scheduled', 'ready' ), 'lang' => $lang, 'order' => 'time', 'limit' => 14 ) );
	if ( $recent ) {
		$out[] = "RECENT POSTS (do not repeat their idea or hook):\n- " . implode( "\n- ", array_map( function ( $p ) {
			return $p['title'] . ' — ' . mb_substr( strtok( $p['body'], "\n" ), 0, 90 );
		}, $recent ) );
	}
	return implode( "\n\n", $out );
}

/**
 * Write a post.
 *
 * @param array $a lang, format, pillar, framework, hook_type, cta_type, topic, angle, facts (array), slides (count), extra (instructions).
 * @return array|WP_Error post fields for bdb_post_save()
 */
function bdb_write_post( $a ) {
	$a      = wp_parse_args( $a, array( 'lang' => 'en', 'format' => 'text', 'pillar' => '', 'framework' => 'lesson', 'hook_type' => 'number', 'cta_type' => 'question', 'topic' => '', 'angle' => '', 'facts' => array(), 'slides' => 8, 'extra' => '' ) );
	$b      = bdb_brand();
	$lang   = 'fa' === $a['lang'] ? 'fa' : 'en';
	$fw     = bdb_frameworks()[ $a['framework'] ] ?? bdb_frameworks()['lesson'];
	$hook   = bdb_hook_types()[ $a['hook_type'] ] ?? bdb_hook_types()['number'];
	$cta    = bdb_cta_types()[ $a['cta_type'] ] ?? bdb_cta_types()['question'];
	$pillar = bdb_pillars()[ $a['pillar'] ] ?? null;
	$cta_i  = strtr( $cta[1], array( '{word}' => $b['magnet_word'], '{magnet}' => $b['magnet'] ) );
	$task   = array();
	$task[] = 'TASK: write one LinkedIn post in ' . ( 'fa' === $lang ? 'PERSIAN (فارسی)' : 'ENGLISH' ) . '.';
	$task[] = 'FORMAT: ' . array(
		'text'     => 'text post (slides: empty array).',
		'carousel' => 'carousel document: write the caption in “body” and ' . max( 6, min( 10, (int) $a['slides'] ) ) . ' slides in “slides”; doc_title = the document title shown on LinkedIn (≤ 60 chars).',
		'image'    => 'single image card: caption in “body” and exactly 1 slide (type quote, stat or list).',
		'poll'     => 'poll: caption in “body”, the question in poll_question and 2–4 options in poll_options (slides: empty array).',
	)[ $a['format'] ];
	if ( $pillar ) {
		$task[] = 'CONTENT PILLAR: ' . $pillar[1];
	}
	$task[] = 'TOPIC: ' . ( '' !== trim( $a['topic'] ) ? $a['topic'] : 'choose the strongest topic for this pillar' );
	if ( '' !== trim( $a['angle'] ) ) {
		$task[] = 'ANGLE: ' . $a['angle'];
	}
	$task[] = 'STRUCTURE: ' . $fw[1];
	$task[] = 'HOOK STYLE: ' . $hook[1] . ' Also give 3 alternative hooks (first line + second line) in alt_hooks, each in a different style.';
	$task[] = 'CALL TO ACTION: ' . $cta_i;
	if ( 'booking' === $a['cta_type'] && '' !== $b['booking_url'] ) {
		$task[] = 'The first comment must contain the booking link: ' . $b['booking_url'];
	}
	$task[] = 'FIRST COMMENT: a short, useful first comment by the author (a resource, the link if any, or one extra tip) — never “thanks for reading”.';
	$task[] = 'SIGNATURE: you may end the body with a variation of: ' . ( 'fa' === $lang ? $b['signature_fa'] : $b['signature'] );
	$task[] = 'HASHTAGS: choose 3–5 from or similar to: ' . ( 'fa' === $lang ? $b['hashtags_fa'] : $b['hashtags_en'] );
	$task[] = 'title = a short internal title (Persian, ≤ 70 chars) so the author recognises the post in the dashboard. notes = 1–2 sentences in Persian for the author: why this angle and when to post it.';
	if ( $a['facts'] ) {
		$task[] = "FACTS (anonymised, from the author's own tools — use them, do not invent more):\n" . wp_json_encode( $a['facts'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	}
	if ( '' !== trim( $a['extra'] ) ) {
		$task[] = 'EXTRA INSTRUCTIONS FROM THE AUTHOR: ' . $a['extra'];
	}
	$ctx  = bdb_writer_context( $lang );
	$user = implode( "\n\n", array_filter( array( $ctx, implode( "\n", $task ) ) ) );
	$res  = bdb_ai_request( bdb_writer_system( $lang ), $user, bdb_post_schema(), array( 'purpose' => 'analysis', 'max_tokens' => 7000, 'effort' => 'medium' ) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	return bdb_written_to_post( $res, $a );
}

/**
 * AI answer → post fields.
 */
function bdb_written_to_post( $res, $a ) {
	$tags = array_slice( array_values( array_filter( array_map( function ( $t ) {
		$t = trim( preg_replace( '/\s+/u', '_', trim( (string) $t ) ), '#_' );
		return '' === $t ? '' : '#' . $t;
	}, (array) ( $res['hashtags'] ?? array() ) ) ) ), 0, 5 );
	$slides = array();
	if ( in_array( $a['format'], array( 'carousel', 'image' ), true ) ) {
		foreach ( (array) ( $res['slides'] ?? array() ) as $s ) {
			$slides[] = bdb_slide_clean( $s );
		}
		if ( 'image' === $a['format'] ) {
			$slides = array_slice( $slides, 0, 1 );
		}
	}
	$meta = array(
		'alt_hooks' => array_slice( array_values( array_filter( array_map( 'strval', (array) ( $res['alt_hooks'] ?? array() ) ) ) ), 0, 4 ),
		'doc_title' => mb_substr( (string) ( $res['doc_title'] ?? '' ), 0, 80 ),
		'notes'     => (string) ( $res['notes'] ?? '' ),
		'framework' => $a['framework'],
		'facts'     => $a['facts'],
		'topic'     => $a['topic'],
	);
	if ( 'poll' === $a['format'] ) {
		$meta['poll'] = array(
			'question' => mb_substr( (string) ( $res['poll_question'] ?? '' ), 0, 140 ),
			'options'  => array_slice( array_map( function ( $o ) {
				return mb_substr( trim( (string) $o ), 0, 30 );
			}, (array) ( $res['poll_options'] ?? array() ) ), 0, 4 ),
			'duration' => 'THREE_DAYS',
		);
	}
	return array(
		'lang'          => 'fa' === $a['lang'] ? 'fa' : 'en',
		'format'        => $a['format'],
		'pillar'        => $a['pillar'],
		'title'         => (string) ( $res['title'] ?? '' ),
		'body'          => bdb_clean_body( (string) ( $res['body'] ?? '' ) ),
		'first_comment' => trim( (string) ( $res['first_comment'] ?? '' ) ),
		'hashtags'      => implode( ' ', $tags ),
		'hook_type'     => $a['hook_type'],
		'cta_type'      => $a['cta_type'],
		'slides'        => $slides,
		'meta'          => $meta,
	);
}

/**
 * Normalise a slide (from the AI or the editor).
 */
function bdb_slide_clean( $s ) {
	$s     = (array) $s;
	$str   = function ( $k, $max = 400 ) use ( $s ) {
		return mb_substr( trim( wp_strip_all_tags( (string) ( $s[ $k ] ?? '' ) ) ), 0, $max );
	};
	$list  = function ( $k ) use ( $s ) {
		return array_slice( array_values( array_filter( array_map( function ( $x ) {
			return mb_substr( trim( wp_strip_all_tags( (string) $x ) ), 0, 160 );
		}, (array) ( $s[ $k ] ?? array() ) ), 'strlen' ) ), 0, 7 );
	};
	$types = array( 'cover', 'point', 'list', 'steps', 'stat', 'compare', 'quote', 'cta' );
	return array(
		'type'        => in_array( $s['type'] ?? '', $types, true ) ? $s['type'] : 'point',
		'kicker'      => $str( 'kicker', 60 ),
		'title'       => $str( 'title', 140 ),
		'body'        => $str( 'body', 420 ),
		'items'       => $list( 'items' ),
		'stat_value'  => $str( 'stat_value', 16 ),
		'stat_label'  => $str( 'stat_label', 120 ),
		'left_title'  => $str( 'left_title', 40 ),
		'left_items'  => $list( 'left_items' ),
		'right_title' => $str( 'right_title', 40 ),
		'right_items' => $list( 'right_items' ),
	);
}

/**
 * Clean a body: no trailing hashtag line, no Markdown bold, max 3 blank lines.
 */
function bdb_clean_body( $body ) {
	$body = str_replace( "\r\n", "\n", trim( $body ) );
	$body = preg_replace( '/\*\*(.+?)\*\*/u', '$1', $body );
	$body = preg_replace( '/(\n\s*)?(#[\p{L}\p{N}_]+\s*){2,}$/u', '', $body ); // Hashtags are stored apart.
	return trim( preg_replace( "/\n{3,}/", "\n\n", $body ) );
}

/**
 * The text LinkedIn receives: body + hashtags.
 */
function bdb_commentary( $p ) {
	$tags = trim( (string) $p['hashtags'] );
	return trim( $p['body'] ) . ( '' !== $tags ? "\n\n" . $tags : '' );
}

/**
 * Write the post of an existing row again (same settings), keeping its schedule.
 *
 * @return true|WP_Error
 */
function bdb_rewrite_post( $id, $extra = '' ) {
	$p = bdb_post( $id );
	if ( ! $p ) {
		return new WP_Error( 'bdb_404', 'پست پیدا نشد.' );
	}
	$fields = bdb_write_post( array(
		'lang'      => $p['lang'],
		'format'    => $p['format'],
		'pillar'    => $p['pillar'],
		'framework' => $p['meta']['framework'] ?? 'lesson',
		'hook_type' => $p['hook_type'] ?: 'number',
		'cta_type'  => $p['cta_type'] ?: 'question',
		'topic'     => $p['meta']['topic'] ?? $p['title'],
		'facts'     => $p['meta']['facts'] ?? array(),
		'slides'    => max( 6, count( $p['slides'] ) ),
		'extra'     => $extra,
	) );
	if ( is_wp_error( $fields ) ) {
		return $fields;
	}
	$fields['meta']   = array_merge( $p['meta'], $fields['meta'] );
	$fields['asset']  = '';
	$fields['status'] = in_array( $p['status'], array( 'scheduled', 'ready' ), true ) ? 'draft' : $p['status'];
	bdb_post_save( $fields, $id );
	return true;
}

/**
 * Quick edits of one post with the light model: a stronger hook, shorter, more
 * story, more data… Returns the new body (the editor decides to keep it).
 *
 * @return array|WP_Error {body, hooks}
 */
function bdb_polish( $id, $how ) {
	$p = bdb_post( $id );
	if ( ! $p ) {
		return new WP_Error( 'bdb_404', 'پست پیدا نشد.' );
	}
	$asks = array(
		'hook'    => 'Rewrite only the first two lines into a much stronger hook; keep the rest identical.',
		'shorter' => 'Make it 30% shorter without losing the key insight or the CTA.',
		'story'   => 'Open with a short, specific personal moment (time, place, tension) and keep the teaching.',
		'clearer' => 'Simplify the language for non-native readers; shorter sentences; keep the meaning.',
		'punchy'  => 'Make it punchier: cut filler, stronger verbs, more white space.',
	);
	$schema = array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( 'body', 'hooks' ),
		'properties'           => array( 'body' => array( 'type' => 'string' ), 'hooks' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ) ),
	);
	$user = 'TASK: ' . ( $asks[ $how ] ?? $asks['punchy'] ) . " Return the full new post in “body” (no hashtags) and 3 alternative hooks in “hooks”.\n\nPOST:\n" . $p['body'];
	$res  = bdb_ai_request( bdb_writer_system( $p['lang'] ), $user, $schema, array( 'purpose' => 'light', 'max_tokens' => 3000 ) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	return array( 'body' => bdb_clean_body( (string) ( $res['body'] ?? '' ) ), 'hooks' => array_values( array_filter( (array) ( $res['hooks'] ?? array() ) ) ) );
}

/**
 * The Persian version of an English post (or the reverse): a native rewrite for
 * the other audience, created as a new post linked to the original.
 *
 * @return int|WP_Error new post id
 */
function bdb_write_counterpart( $id ) {
	$p = bdb_post( $id );
	if ( ! $p ) {
		return new WP_Error( 'bdb_404', 'پست پیدا نشد.' );
	}
	if ( $p['pair_id'] && bdb_post( $p['pair_id'] ) ) {
		return new WP_Error( 'bdb_pair', 'نسخه زبان دیگر این پست قبلاً ساخته شده است.' );
	}
	$to     = 'en' === $p['lang'] ? 'fa' : 'en';
	$source = "ORIGINAL POST (" . strtoupper( $p['lang'] ) . "):\n" . $p['body'];
	if ( $p['slides'] ) {
		$source .= "\n\nORIGINAL SLIDES:\n" . wp_json_encode( $p['slides'], JSON_UNESCAPED_UNICODE );
	}
	if ( ! empty( $p['meta']['poll'] ) ) {
		$source .= "\n\nORIGINAL POLL:\n" . wp_json_encode( $p['meta']['poll'], JSON_UNESCAPED_UNICODE );
	}
	$fields = bdb_write_post( array(
		'lang'      => $to,
		'format'    => $p['format'],
		'pillar'    => $p['pillar'],
		'framework' => $p['meta']['framework'] ?? 'lesson',
		'hook_type' => $p['hook_type'] ?: 'number',
		'cta_type'  => $p['cta_type'] ?: 'question',
		'topic'     => $p['meta']['topic'] ?? $p['title'],
		'facts'     => $p['meta']['facts'] ?? array(),
		'slides'    => max( 6, count( $p['slides'] ) ),
		'extra'     => ( 'fa' === $to
			? 'Write the Persian version of the post below for the Persian-speaking audience: same core idea and structure, but natively written and adapted (examples, tone, references) — never a literal translation.'
			: 'Write the English version of the post below for the international audience: same core idea, natively written and adapted — never a literal translation.' ) . "\n\n" . $source,
	) );
	if ( is_wp_error( $fields ) ) {
		return $fields;
	}
	$fields['status']  = 'draft';
	$fields['pair_id'] = $p['id'];
	$fields['idea_id'] = $p['idea_id'];
	$new               = bdb_post_save( $fields );
	bdb_post_save( array( 'pair_id' => $new ), $p['id'] );
	return $new;
}

/**
 * Brainstorm ideas for a pillar with the AI (into the idea bank).
 *
 * @return int|WP_Error number added
 */
function bdb_ideas_ai( $pillar = '', $count = 8 ) {
	$pillars = bdb_pillars();
	$schema  = array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( 'ideas' ),
		'properties'           => array(
			'ideas' => array(
				'type'  => 'array',
				'items' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'title', 'angle', 'pillar', 'framework', 'score' ),
					'properties'           => array(
						'title'     => array( 'type' => 'string' ),
						'angle'     => array( 'type' => 'string' ),
						'pillar'    => array( 'type' => 'string', 'enum' => array_keys( $pillars ) ),
						'framework' => array( 'type' => 'string', 'enum' => array_keys( bdb_frameworks() ) ),
						'score'     => array( 'type' => 'integer' ),
					),
				),
			),
		),
	);
	$have = array_map( function ( $i ) {
		return $i['title'];
	}, bdb_ideas( array( 'status' => '', 'limit' => 80 ) ) );
	$p_txt = array();
	foreach ( $pillars as $k => $p ) {
		$p_txt[] = $k . ': ' . $p[1];
	}
	$user  = 'TASK: propose ' . (int) $count . ' LinkedIn post ideas' . ( $pillar && isset( $pillars[ $pillar ] ) ? ' for the pillar “' . $pillar . '”' : ' spread across the pillars' ) . ' that the target readers would save and comment on. Each idea: a specific English working title, the angle (one sentence: the insight and why it matters to the reader), the pillar key, the best framework and a score 0–100 (expected resonance with the target readers).';
	$user .= "\n\nPILLARS:\n" . implode( "\n", $p_txt );
	if ( $have ) {
		$user .= "\n\nALREADY IN THE IDEA BANK (do not repeat):\n- " . implode( "\n- ", array_slice( $have, 0, 60 ) );
	}
	$insights = bdb_insights_for_prompt();
	if ( $insights ) {
		$user .= "\n\nWHAT PERFORMED BEST:\n- " . implode( "\n- ", $insights );
	}
	$res = bdb_ai_request( "You are the LinkedIn content strategist of the author below.\n\n" . bdb_brand_prompt( 'en' ), $user, $schema, array( 'purpose' => 'light', 'max_tokens' => 3500 ) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$n = 0;
	foreach ( (array) ( $res['ideas'] ?? array() ) as $i ) {
		$n += (int) (bool) bdb_idea_add( array(
			'title'   => (string) $i['title'],
			'angle'   => (string) $i['angle'],
			'pillar'  => isset( $pillars[ $i['pillar'] ] ) ? $i['pillar'] : $pillar,
			'source'  => 'ai',
			'score'   => (int) $i['score'],
			'payload' => array( 'framework' => (string) $i['framework'] ),
		) );
	}
	return $n;
}

/**
 * Write a post from an idea of the bank (marks the idea used).
 *
 * @return int|WP_Error post id
 */
function bdb_post_from_idea( $idea_id, $over = array() ) {
	$i = bdb_idea( $idea_id );
	if ( ! $i ) {
		return new WP_Error( 'bdb_404', 'ایده پیدا نشد.' );
	}
	$facts = $i['payload'];
	$fw    = (string) ( $facts['framework'] ?? 'lesson' );
	unset( $facts['framework'] );
	$args   = wp_parse_args( $over, array(
		'lang'      => 'en',
		'format'    => bdb_suggest_format( $fw ),
		'pillar'    => $i['pillar'],
		'framework' => $fw,
		'hook_type' => bdb_suggest_hook( $fw ),
		'cta_type'  => bdb_next_cta(),
		'topic'     => $i['title'],
		'angle'     => (string) $i['angle'],
		'facts'     => $facts,
	) );
	$fields = bdb_write_post( $args );
	if ( is_wp_error( $fields ) ) {
		return $fields;
	}
	$fields['status']  = 'draft';
	$fields['idea_id'] = $i['id'];
	$fields['meta']['source'] = array( 'source' => $i['source'], 'idea' => $i['title'] );
	$id = bdb_post_save( $fields );
	bdb_idea_status( $i['id'], 'used' );
	return $id;
}

/**
 * Best format for a framework.
 */
function bdb_suggest_format( $framework ) {
	return array(
		'checklist'    => 'carousel',
		'framework'    => 'carousel',
		'mistakes'     => 'carousel',
		'how_i'        => 'carousel',
		'before_after' => 'image',
		'myth_fact'    => 'image',
		'contrarian'   => 'text',
		'story'        => 'text',
		'case_study'   => 'text',
		'behind'       => 'text',
		'lesson'       => 'text',
	)[ $framework ] ?? 'text';
}

/**
 * Best hook for a framework.
 */
function bdb_suggest_hook( $framework ) {
	return array(
		'case_study'   => 'number',
		'mistakes'     => 'mistake',
		'checklist'    => 'promise',
		'framework'    => 'promise',
		'myth_fact'    => 'contrarian',
		'before_after' => 'number',
		'story'        => 'story',
		'contrarian'   => 'contrarian',
		'how_i'        => 'promise',
		'behind'       => 'story',
		'lesson'       => 'question',
	)[ $framework ] ?? 'number';
}

/**
 * Next CTA in the rotation: conversation most of the time, a lead magnet or a
 * booking now and then (never two lead-magnet posts in a row).
 */
function bdb_next_cta() {
	$cycle = array( 'question', 'save', 'question', 'keyword', 'follow', 'question', 'repost', 'booking', 'question', 'dm' );
	$i     = (int) get_option( 'bdb_cta_cursor', 0 );
	update_option( 'bdb_cta_cursor', ( $i + 1 ) % count( $cycle ), false );
	$cta = $cycle[ $i % count( $cycle ) ];
	if ( 'booking' === $cta && '' === (string) bdb_brand()['booking_url'] ) {
		$cta = 'dm';
	}
	return $cta;
}
