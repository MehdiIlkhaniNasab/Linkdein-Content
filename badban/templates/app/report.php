<?php
/**
 * Monthly LinkedIn report: a printable page (browser «Save as PDF»), in the
 * same spirit as Fanus: strategy → action → result → next focus.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

$b      = bdb_brand();
$ym     = preg_match( '/^\d{4}-\d{2}$/', (string) ( $_GET['m'] ?? '' ) ) ? $_GET['m'] : gmdate( 'Y-m', strtotime( 'first day of last month' ) ); // phpcs:ignore
$from   = $ym . '-01';
$to     = gmdate( 'Y-m-t', strtotime( $from ) );
$pfrom  = gmdate( 'Y-m-01', strtotime( $from . ' -1 month' ) );
$pto    = gmdate( 'Y-m-t', strtotime( $pfrom ) );
$tot    = bdb_stats_totals( $from, $to );
$prev   = bdb_stats_totals( $pfrom, $pto );
$series = bdb_stats( $from, $to );
$posts  = bdb_posts( array( 'status' => 'published', 'from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59', 'order' => 'time', 'limit' => 200 ) );
$ppost  = bdb_posts( array( 'status' => 'published', 'from' => $pfrom . ' 00:00:00', 'to' => $pto . ' 23:59:59', 'limit' => 200 ) );
$ranked = $posts;
usort( $ranked, function ( $a, $c ) {
	return ( bdb_post_er( $c ) ?? -1 ) <=> ( bdb_post_er( $a ) ?? -1 );
} );
$by_p = array();
$by_f = array();
$by_l = array( 'en' => 0, 'fa' => 0 );
$leads = 0;
foreach ( $posts as $p ) {
	$by_p[ $p['pillar'] ] = ( $by_p[ $p['pillar'] ] ?? 0 ) + 1;
	$by_f[ $p['format'] ] = ( $by_f[ $p['format'] ] ?? 0 ) + 1;
	++$by_l[ $p['lang'] ];
	$leads += (int) ( $p['metrics']['leads'] ?? 0 );
}
arsort( $by_p );
$cons   = bdb_consistency();
$ins    = bdb_insights_fa();
$months = array();
for ( $i = 1; $i <= 12; $i++ ) {
	$months[] = gmdate( 'Y-m', strtotime( "first day of -$i month" ) );
}
$jm = bdb_jdate( $from, 'month' );
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( 'گزارش لینکدین ' . $jm . ' · ' . $b['name_fa'] ); ?></title>
	<?php bdb_app_assets(); ?>
</head>
<body class="bdb-app bdb-report-body">
<div class="bdb-report-bar">
	<a class="bdb-btn is-small" href="<?php echo esc_url( bdb_url( 'analytics' ) ); ?>">بازگشت</a>
	<form method="get" class="bdb-inline">
		<?php if ( ! get_option( 'permalink_structure' ) ) : ?><input type="hidden" name="bdb_app" value="1"><input type="hidden" name="bdb_route" value="report"><?php endif; ?>
		<select name="m" onchange="this.form.submit()"><?php foreach ( $months as $m ) : ?><option value="<?php echo esc_attr( $m ); ?>" <?php selected( $m, $ym ); ?>><?php echo esc_html( bdb_jdate( $m . '-15', 'month' ) . ' (' . $m . ')' ); ?></option><?php endforeach; ?></select>
	</form>
	<button class="bdb-btn is-small is-primary" type="button" onclick="window.print()"><?php echo bdb_icon( 'download', 14 ); // phpcs:ignore ?>ذخیره PDF</button>
</div>

<article class="bdb-report">
	<header class="bdb-report-cover">
		<span class="bdb-brand-mark is-lg"><?php echo bdb_icon( 'sail', 30 ); // phpcs:ignore ?></span>
		<p>گزارش ماهانه برند شخصی در لینکدین</p>
		<h1><?php echo esc_html( $jm ); ?></h1>
		<h2><?php echo esc_html( $b['name_fa'] ); ?> <small dir="ltr"><?php echo esc_html( $b['headline'] ); ?></small></h2>
		<small dir="ltr"><?php echo esc_html( gmdate( 'j M', strtotime( $from ) ) . ' – ' . gmdate( 'j M Y', strtotime( $to ) ) ); ?></small>
	</header>

	<section>
		<h3>خلاصه ماه</h3>
		<div class="bdb-stats is-6">
			<div class="bdb-stat"><span>پست منتشرشده</span><b><?php echo esc_html( bdb_num( count( $posts ) ) ); ?></b><small>ماه قبل: <?php echo esc_html( bdb_num( count( $ppost ) ) ); ?></small></div>
			<div class="bdb-stat"><span>نمایش</span><b><?php echo esc_html( bdb_compact( $tot['impressions'] ) ); ?></b><small><?php echo bdb_delta( $tot['impressions'], $prev['impressions'] ); // phpcs:ignore ?></small></div>
			<div class="bdb-stat"><span>تعامل</span><b><?php echo esc_html( bdb_compact( $tot['engagements'] ) ); ?></b><small><?php echo bdb_delta( $tot['engagements'], $prev['engagements'] ); // phpcs:ignore ?></small></div>
			<div class="bdb-stat"><span>نرخ تعامل</span><b><?php echo esc_html( null !== $tot['er'] ? bdb_pct( $tot['er'], 1 ) : '—' ); ?></b><small>قبل: <?php echo esc_html( null !== $prev['er'] ? bdb_pct( $prev['er'], 1 ) : '—' ); ?></small></div>
			<div class="bdb-stat"><span>دنبال‌کننده جدید</span><b><?php echo esc_html( '+' . bdb_num( $tot['new_followers'] ) ); ?></b><small><?php echo esc_html( $tot['followers'] ? 'کل: ' . bdb_num( $tot['followers'] ) : '' ); ?></small></div>
			<div class="bdb-stat"><span>لید و گفتگوی کاری</span><b><?php echo esc_html( bdb_num( $leads ) ); ?></b><small>ثبت‌شده روی پست‌ها</small></div>
		</div>
		<?php if ( count( $series ) >= 3 ) : ?>
			<?php echo bdb_svg_line( array( array( 'نمایش', array_column( $series, 'impressions' ), 's1', true ) ), array_map( function ( $r ) { return bdb_jdate( $r['day'], 'day' ); }, $series ), 180 ); // phpcs:ignore ?>
		<?php endif; ?>
	</section>

	<section class="bdb-report-cols">
		<div>
			<h3>ترکیب محتوا</h3>
			<?php
			echo bdb_bars( array_map( function ( $k, $n ) { // phpcs:ignore
				return array( bdb_pillar_label( $k ), $n, bdb_num( $n ) . ' پست' );
			}, array_keys( $by_p ), $by_p ) );
			?>
			<p class="bdb-small">قالب‌ها: <?php echo esc_html( implode( '، ', array_map( function ( $k, $n ) { return ( bdb_formats()[ $k ][0] ?? $k ) . ' ' . bdb_num( $n ); }, array_keys( $by_f ), $by_f ) ) ?: '—' ); ?> · انگلیسی <?php echo esc_html( bdb_num( $by_l['en'] ) ); ?> / فارسی <?php echo esc_html( bdb_num( $by_l['fa'] ) ); ?></p>
		</div>
		<div>
			<h3>ثبات انتشار</h3>
			<p>رکورد فعلی: <b><?php echo esc_html( bdb_num( $cons['streak'] ) ); ?> هفته</b> پشت سر هم با حداقل <?php echo esc_html( bdb_num( $cons['need'] ) ); ?> پست (هدف هفتگی <?php echo esc_html( bdb_num( $cons['goal'] ) ); ?>).</p>
			<?php if ( $ins ) : ?>
				<h3>آنچه جواب داد</h3>
				<ul class="bdb-insights"><?php foreach ( array_slice( $ins, 0, 5 ) as $line ) : ?><li><?php echo esc_html( $line ); ?></li><?php endforeach; ?></ul>
			<?php endif; ?>
		</div>
	</section>

	<section>
		<h3>پست‌های ماه <small>به ترتیب نرخ تعامل</small></h3>
		<?php if ( ! $posts ) : ?>
			<p class="bdb-muted">در این ماه پستی منتشر نشده است.</p>
		<?php else : ?>
			<table class="bdb-table">
				<thead><tr><th>پست</th><th>تاریخ</th><th class="num">نمایش</th><th class="num">تعامل</th><th class="num">نرخ</th></tr></thead>
				<tbody>
					<?php foreach ( $ranked as $p ) : ?>
						<tr>
							<td><b><?php echo esc_html( $p['title'] ); ?></b><br><small class="bdb-muted" dir="auto"><?php echo esc_html( bdb_hook_preview( $p['body'], 110 ) ); ?></small></td>
							<td class="bdb-nowrap"><?php echo esc_html( bdb_jdate( substr( (string) $p['published_at'], 0, 10 ), 'day' ) ); ?></td>
							<td class="num"><?php echo esc_html( isset( $p['metrics']['impressions'] ) ? bdb_num( $p['metrics']['impressions'] ) : '—' ); ?></td>
							<td class="num"><?php echo esc_html( bdb_num( bdb_post_engagement( $p ) ) ); ?></td>
							<td class="num"><b><?php echo esc_html( null !== bdb_post_er( $p ) ? bdb_pct( bdb_post_er( $p ), 1 ) : '—' ); ?></b></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</section>

	<section>
		<h3>فوکوس ماه آینده</h3>
		<?php
		$balance = bdb_pillar_balance();
		$behind  = array();
		foreach ( $balance as $k => $bal ) {
			if ( $bal[0] - $bal[1] >= 5 ) {
				$behind[] = bdb_pillar_label( $k );
			}
		}
		$best_f = array_key_first( bdb_performance()['format'] ?? array() );
		?>
		<ul class="bdb-insights">
			<li>هدف انتشار: <?php echo esc_html( bdb_num( $cons['goal'] ) ); ?> پست در هفته (<?php echo esc_html( bdb_num( count( (array) bdb_schedule()['en'] ) ) ); ?> انگلیسی، <?php echo esc_html( bdb_num( count( (array) bdb_schedule()['fa'] ) ) ); ?> فارسی) — بدون هفته خالی.</li>
			<?php if ( $behind ) : ?><li>ستون‌هایی که عقب مانده‌اند و باید جلو بیایند: <?php echo esc_html( implode( '، ', $behind ) ); ?>.</li><?php endif; ?>
			<?php if ( $best_f ) : ?><li>قالب برتر تا امروز «<?php echo esc_html( bdb_formats()[ $best_f ][0] ?? $best_f ); ?>» است؛ سهم آن را بیشتر کنید.</li><?php endif; ?>
			<li>هدف ماه بعد: نرخ تعامل بالای <?php echo esc_html( bdb_pct( max( 0.02, ( $tot['er'] ?? 0.02 ) * 1.1 ), 1 ) ); ?> و رشد نمایش ۱۰٪ نسبت به این ماه.</li>
		</ul>
	</section>
	<footer class="bdb-report-foot">بادبان · Badban by Ronagh — <?php echo esc_html( bdb_jdate( current_time( 'Y-m-d' ) ) ); ?></footer>
</article>
</body>
</html>
