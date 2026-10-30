<?php
/**
 * Brand strategy: who writes, for whom, about what, in which voice — and the
 * libraries the writer draws from (formats, frameworks, hooks, CTAs).
 *
 * The brand is one option (bdb_brand), edited on the «استراتژی برند» screen.
 * Every generation prompt starts from it, so changing the positioning or a
 * pillar changes every post written after it.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default brand profile (the owner's starting point; everything is editable).
 */
function bdb_brand_defaults() {
	return array(
		'name'         => 'Mehdi Ilkhani Nasab',
		'name_fa'      => 'مهدی ایلخانی نسب',
		'headline'     => 'SEO Manager & Strategist · I turn Google into a sales channel',
		'headline_fa'  => 'مدیر و استراتژیست سئو · گوگل را به کانال فروش تبدیل می‌کنم',
		'handle'       => '',
		'positioning'  => 'I help businesses turn organic search into predictable revenue: data-driven SEO strategy, technical SEO, content systems and a team that executes every week. I build my own tools (keyword clustering, action planning, client reporting) so strategy never stays on paper.',
		'audience'     => "Companies abroad hiring a senior SEO (in-house or remote)\nWell-known brands that want measurable organic growth\nFounders and marketing leads who need SEO tied to sales, not vanity traffic",
		'audience_fa'  => "صاحبان کسب‌وکار و مدیران بازاریابی ایرانی\nسئوکارها و تیم‌های سئو که می‌خواهند حرفه‌ای‌تر رشد کنند",
		'goals'        => "Be seen as a strategic SEO leader, not a task executor\nInbound job offers and client conversations from abroad\nTrust through proof: real (anonymised) results and transparent process",
		'proof'        => "Built Compass (keyword clustering), Helm (SEO action plan) and Lantern (client reporting) for my agency\nManages SEO for multiple e-commerce and service businesses\nLeads an SEO team end-to-end: strategy, technical, content, off-page",
		'voice'        => "Clear, practical and confident — never arrogant\nNumbers over adjectives; one idea per post\nShort lines, white space, plain words; explain the why, not only the what\nShare the process and the lesson, including mistakes",
		'avoid'        => "In today's fast-paced world\nGame-changer\nUnlock\nDelve\nLeverage synergies\nGuru\nEmoji walls and hashtag walls",
		'samples'      => '',
		'pillars'      => bdb_pillars_defaults(),
		'hashtags_en'  => '#SEO #SEOStrategy #TechnicalSEO #ContentStrategy #DigitalMarketing #Ecommerce #GrowthMarketing',
		'hashtags_fa'  => '#سئو #استراتژی_سئو #دیجیتال_مارکتینگ #سئو_تکنیکال #تولید_محتوا',
		'booking_url'  => '',
		'magnet'       => 'my SEO audit checklist',
		'magnet_word'  => 'SEO',
		'signature'    => 'Follow me for practical SEO strategy you can actually execute.',
		'signature_fa' => 'برای سئوی کاربردی و قابل اجرا، دنبالم کنید.',
		'brand_color'  => '#7C3AED',
		'brand_color2' => '#C4B5FD',
		'brand_dark'   => '#160B2E',
		'photo_url'    => '',
		'private_terms' => '',
	);
}

/**
 * Default content pillars: key => [label (fa), English description for the writer, weight %].
 */
function bdb_pillars_defaults() {
	return array(
		'strategy'  => array( 'استراتژی سئو و رشد', 'SEO strategy tied to revenue: priorities, roadmaps, focus, measuring what matters', 22 ),
		'technical' => array( 'سئو تکنیکال', 'Technical SEO: crawling, indexing, site architecture, schema, speed, migrations, redirects', 16 ),
		'content'   => array( 'محتوا و کیورد ریسرچ', 'Keyword research, clustering, pillar pages, content calendars, briefs and refreshes', 16 ),
		'offpage'   => array( 'آف‌پیج و اعتبار', 'Off-page SEO: digital PR, link earning, brand mentions, authority building', 8 ),
		'team'      => array( 'کار تیمی و مدیریت', 'Leading SEO teams: process, delegation, weekly execution, client communication', 14 ),
		'results'   => array( 'نتایج و کیس استادی', 'Anonymised case studies: what we did, why, the measured result and the lesson', 16 ),
		'career'    => array( 'رشد حرفه‌ای و فروش تخصص', 'Career and business skills for specialists: selling your expertise, communication, AI in SEO, lessons learned', 8 ),
	);
}

/**
 * Brand profile (stored over defaults).
 */
function bdb_brand() {
	if ( isset( $GLOBALS['bdb_brand_cache'] ) ) {
		return $GLOBALS['bdb_brand_cache'];
	}
	$b = get_option( 'bdb_brand', array() );
	$b = wp_parse_args( is_array( $b ) ? $b : array(), bdb_brand_defaults() );
	if ( ! is_array( $b['pillars'] ) || ! $b['pillars'] ) {
		$b['pillars'] = bdb_pillars_defaults();
	}
	$GLOBALS['bdb_brand_cache'] = $b;
	return $b;
}

/**
 * Save the brand profile.
 */
function bdb_brand_save( $data ) {
	update_option( 'bdb_brand', $data, false );
	unset( $GLOBALS['bdb_brand_cache'] );
}

/**
 * Pillars: key => [label, description, weight].
 */
function bdb_pillars() {
	return bdb_brand()['pillars'];
}

/**
 * Label of a pillar.
 */
function bdb_pillar_label( $key ) {
	return bdb_pillars()[ $key ][0] ?? ( '' === (string) $key ? 'بدون ستون' : $key );
}

/**
 * Post formats: key => [label, icon, short help].
 */
function bdb_formats() {
	return array(
		'carousel' => array( 'کاروسل PDF', 'slides', 'اسلایدهای برند در یک سند PDF؛ بیشترین ماندگاری و ذخیره‌شدن' ),
		'text'     => array( 'متنی', 'pen', 'پست متنی با قلاب قوی و فاصله‌گذاری خوانا' ),
		'image'    => array( 'کارت تصویری', 'doc', 'یک کارت گرافیکی (نقل‌قول، عدد، چک‌لیست) بدون تولید تصویر هوش مصنوعی' ),
		'poll'     => array( 'نظرسنجی', 'chart', 'سؤال با ۲ تا ۴ گزینه برای تعامل و شناخت مخاطب' ),
	);
}

/**
 * Post frameworks (the structure of the post), with English instructions for the writer.
 */
function bdb_frameworks() {
	return array(
		'case_study'   => array( 'کیس استادی', 'Case study: context (anonymised) → the problem in one line → the 3 key moves and why → the measured result → the lesson others can apply.' ),
		'mistakes'     => array( 'اشتباهات رایج', 'N common mistakes: each mistake, why it hurts revenue, and the fix. End with the one mistake that matters most.' ),
		'checklist'    => array( 'چک‌لیست', 'Actionable checklist: a promise, then numbered steps someone can apply today, each with a one-line why.' ),
		'framework'    => array( 'فریم‌ورک اختصاصی', 'A named framework of the author (e.g. Compass → Helm → Lantern: strategy → execution → reporting): the problem it solves, the steps, an example.' ),
		'myth_fact'    => array( 'افسانه و واقعیت', 'Myth vs reality: a popular belief, why it is wrong or incomplete, what actually works, with evidence.' ),
		'before_after' => array( 'قبل و بعد', 'Before / after: the situation before, the change, the situation after, with numbers and the reason it worked.' ),
		'story'        => array( 'داستان و درس', 'Personal story: a specific moment (time, place, tension), what happened, the lesson, and how the reader can use it.' ),
		'contrarian'   => array( 'نظر خلاف جریان', 'Contrarian take: a strong, defensible opinion against common advice, the reasoning, and the nuance.' ),
		'how_i'        => array( 'اگر من بودم…', 'How I would do X (e.g. the first 90 days of SEO for a new e-commerce site): a concrete plan by phases.' ),
		'behind'       => array( 'پشت صحنه', 'Behind the scenes: how the team actually works (meetings, tools, process), what we learned, what we changed.' ),
		'lesson'       => array( 'یک درس کوتاه', 'One insight, short and sharp: the insight, one example, one action.' ),
	);
}

/**
 * Hook types (first lines that stop the scroll).
 */
function bdb_hook_types() {
	return array(
		'number'     => array( 'عدد و نتیجه', 'Open with a specific number or result.' ),
		'contrarian' => array( 'خلاف انتظار', 'Open with a bold statement that contradicts common belief.' ),
		'story'      => array( 'شروع داستانی', 'Open in the middle of a moment (time, place, tension).' ),
		'question'   => array( 'سؤال', 'Open with a question the target reader asks themselves.' ),
		'mistake'    => array( 'اشتباه', 'Open with a costly mistake.' ),
		'promise'    => array( 'وعده', 'Open with a clear, specific promise of what the reader will get.' ),
		'list'       => array( 'فهرست', 'Open with “N things/steps…” and a reason to care.' ),
	);
}

/**
 * Call-to-action types: key => [label, English instruction].
 */
function bdb_cta_types() {
	return array(
		'question' => array( 'سؤال برای کامنت', 'End with one specific, easy-to-answer question that invites comments from the target audience.' ),
		'keyword'  => array( 'کلمه در کامنت (لید مگنت)', 'Offer the lead magnet: “Comment {word} and I will send you {magnet}.”' ),
		'save'     => array( 'ذخیره', 'Ask to save the post for later (works best for checklists and frameworks).' ),
		'repost'   => array( 'بازنشر', 'Ask to repost (♻️) to help someone in their network.' ),
		'follow'   => array( 'دنبال کردن', 'Invite to follow for more on this topic, with a specific promise.' ),
		'dm'       => array( 'پیام مستقیم', 'Invite to send a DM for a specific need (e.g. a quick review).' ),
		'booking'  => array( 'رزرو جلسه', 'Invite to book a call; the link goes in the first comment, never in the post.' ),
	);
}

/**
 * Status labels.
 */
function bdb_statuses() {
	return array(
		'draft'      => array( 'پیش‌نویس', 'is-draft' ),
		'ready'      => array( 'آماده انتشار', 'is-ready' ),
		'scheduled'  => array( 'زمان‌بندی‌شده', 'is-scheduled' ),
		'publishing' => array( 'در حال انتشار', 'is-scheduled' ),
		'published'  => array( 'منتشر شد', 'is-published' ),
		'failed'     => array( 'ناموفق', 'is-failed' ),
		'archived'   => array( 'بایگانی', 'is-draft' ),
	);
}

/**
 * Brand summary for the writer's prompt, in English.
 */
function bdb_brand_prompt( $lang = 'en' ) {
	$b     = bdb_brand();
	$lines = array();
	$lines[] = 'AUTHOR: ' . ( 'fa' === $lang ? $b['name_fa'] . ' (' . $b['name'] . ')' : $b['name'] ) . ' — ' . ( 'fa' === $lang ? $b['headline_fa'] : $b['headline'] );
	$lines[] = 'POSITIONING: ' . $b['positioning'];
	$lines[] = 'TARGET READERS:' . "\n- " . implode( "\n- ", bdb_lines( 'fa' === $lang ? $b['audience_fa'] : $b['audience'] ) );
	$lines[] = 'GOALS OF THE PERSONAL BRAND:' . "\n- " . implode( "\n- ", bdb_lines( $b['goals'] ) );
	if ( '' !== trim( $b['proof'] ) ) {
		$lines[] = 'PROOF POINTS (use when relevant, never exaggerate):' . "\n- " . implode( "\n- ", bdb_lines( $b['proof'] ) );
	}
	$lines[] = 'VOICE:' . "\n- " . implode( "\n- ", bdb_lines( $b['voice'] ) );
	$lines[] = 'NEVER USE: ' . implode( ' | ', bdb_lines( $b['avoid'] ) );
	if ( '' !== trim( $b['samples'] ) ) {
		$lines[] = "WRITING SAMPLES OF THE AUTHOR (match this voice and rhythm, do not copy):\n" . mb_substr( trim( $b['samples'] ), 0, 3500 );
	}
	return implode( "\n\n", $lines );
}
