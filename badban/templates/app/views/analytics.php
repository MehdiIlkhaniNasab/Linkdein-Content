<?php
/**
 * Analytics: growth of the profile, what works, top posts, audience.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

$range  = in_array( (int) ( $_GET['days'] ?? 90 ), array( 30, 90, 365 ), true ) ? (int) $_GET['days'] : 90; // phpcs:ignore
$today  = current_time( 'Y-m-d' );
$from   = gmdate( 'Y-m-d', strtotime( $today . ' -' . ( $range - 1 ) . ' days' ) );
$pfrom  = gmdate( 'Y-m-d', strtotime( $from . ' -' . $range . ' days' ) );
$pto    = gmdate( 'Y-m-d', strtotime( $from . ' -1 day' ) );
$tot    = bdb_stats_totals( $from, $today );
$prev   = bdb_stats_totals( $pfrom, $pto );
$series = bdb_stats( $from, $today );
$labels = array_map( function ( $r ) {
	return bdb_jdate( $r['day'], 'day' );
}, $series );
$posts  = bdb_posts( array( 'status' => 'published', 'from' => $from . ' 00:00:00', 'limit' => 500 ) );
$withm  = array_filter( $posts, function ( $p ) {
	return ! empty( $p['metrics']['impressions'] );
} );
usort( $withm, function ( $a, $b ) {
	return ( bdb_post_er( $b ) ?? 0 ) <=> ( bdb_post_er( $a ) ?? 0 );
} );
$leads  = array_sum( array_map( function ( $p ) {
	return (int) ( $p['metrics']['leads'] ?? 0 );
}, $posts ) );
$perf   = bdb_performance();
$ins    = bdb_insights_fa();
$demo   = get_option( 'bdb_demographics', array() );
$import = get_option( 'bdb_last_import', array() );
$titles = array( 'format' => 'قالب', 'pillar' => 'ستون محتوا', 'hook_type' => 'نوع قلاب', 'cta_type' => 'کال تو اکشن', 'lang' => 'زبان', 'weekday' => 'روز انتشار' );
$foll   = array_values( array_filter( array_column( $series, 'followers' ) ) );
?>
<div class="bdb-head">
	<div>
		<p class="bdb-eyebrow"><?php echo bdb_icon( 'chart', 14 ); // phpcs:ignore ?>رشد قابل اندازه‌گیری</p>
		<h1>آنالیتیکس</h1>
		<p class="bdb-muted">روند پیج، عملکرد هر پست و آنچه جواب می‌دهد. یافته‌ها خودکار به نویسنده داده می‌شوند.</p>
	</div>
	<div class="bdb-actions">
		<div class="bdb-seg">
			<?php foreach ( array( 30 => '۳۰ روز', 90 => '۹۰ روز', 365 => '۱ سال' ) as $d => $l ) : ?>
				<a class="<?php echo $d === $range ? 'is-active' : ''; ?>" href="<?php echo esc_url( bdb_url( 'analytics', array( 'days' => $d ) ) ); ?>"><?php echo esc_html( $l ); ?></a>
			<?php endforeach; ?>
		</div>
		<a class="bdb-btn" href="<?php echo esc_url( bdb_url( 'report' ) ); ?>" target="_blank"><?php echo bdb_icon( 'doc', 16 ); // phpcs:ignore ?>گزارش ماهانه</a>
	</div>
</div>

<form class="bdb-panel is-import" data-bdb-import>
	<div class="bdb-import">
		<div>
			<h2><?php echo bdb_icon( 'upload', 18 ); // phpcs:ignore ?>ورود آمار از لینکدین</h2>
			<p class="bdb-small bdb-muted">لینکدین آمار پروفایل شخصی را فقط به شرکای تأییدشده از طریق API می‌دهد؛ پس فایل خروجی خود لینکدین را وارد کنید: پروفایل › <b>Analytics</b> › <b>Content</b> (و <b>Followers</b>) › بازه ۳۶۵ روز › <b>Export</b>. هر هفته یک بار کافی است.</p>
			<?php if ( ! empty( $import['at'] ) ) : ?><p class="bdb-small">آخرین ورود: <?php echo esc_html( bdb_when( $import['at'], 'fa' ) . ' — ' . ( $import['file'] ?? '' ) ); ?></p><?php endif; ?>
		</div>
		<div class="bdb-inline">
			<input type="file" name="file" accept=".xlsx,.csv" required>
			<button class="bdb-btn is-primary" type="submit">بارگذاری</button>
		</div>
	</div>
</form>

<div class="bdb-stats is-6">
	<div class="bdb-stat"><span>دنبال‌کننده</span><b><?php echo esc_html( $tot['followers'] ? bdb_num( $tot['followers'] ) : '—' ); ?></b><small>+<?php echo esc_html( bdb_num( $tot['new_followers'] ) ); ?> <?php echo bdb_delta( $tot['new_followers'], $prev['new_followers'] ); // phpcs:ignore ?></small></div>
	<div class="bdb-stat"><span>نمایش</span><b><?php echo esc_html( bdb_compact( $tot['impressions'] ) ); ?></b><small><?php echo bdb_delta( $tot['impressions'], $prev['impressions'] ); // phpcs:ignore ?> نسبت به دوره قبل</small></div>
	<div class="bdb-stat"><span>تعامل</span><b><?php echo esc_html( bdb_compact( $tot['engagements'] ) ); ?></b><small><?php echo bdb_delta( $tot['engagements'], $prev['engagements'] ); // phpcs:ignore ?></small></div>
	<div class="bdb-stat is-gem"><span>نرخ تعامل</span><b><?php echo esc_html( null !== $tot['er'] ? bdb_pct( $tot['er'], 1 ) : '—' ); ?></b><small>قبل: <?php echo esc_html( null !== $prev['er'] ? bdb_pct( $prev['er'], 1 ) : '—' ); ?></small></div>
	<div class="bdb-stat"><span>پست منتشرشده</span><b><?php echo esc_html( bdb_num( count( $posts ) ) ); ?></b><small><?php echo esc_html( count( $posts ) ? bdb_compact( $tot['impressions'] / count( $posts ) ) . ' نمایش به ازای هر پست' : '' ); ?></small></div>
	<div class="bdb-stat"><span>لید و گفتگوی کاری</span><b><?php echo esc_html( bdb_num( $leads ) ); ?></b><small>از آمار ثبت‌شده پست‌ها</small></div>
</div>

<?php if ( count( $series ) >= 3 ) : ?>
	<div class="bdb-grid-2 is-top">
		<div class="bdb-panel"><h2>نمایش روزانه</h2><?php echo bdb_svg_line( array( array( 'نمایش', array_column( $series, 'impressions' ), 's1', true ) ), $labels ); // phpcs:ignore ?></div>
		<div class="bdb-panel"><h2>تعامل روزانه</h2><?php echo bdb_svg_line( array( array( 'تعامل', array_column( $series, 'engagements' ), 's2', true ) ), $labels ); // phpcs:ignore ?></div>
	</div>
	<?php if ( count( $foll ) >= 3 ) : ?>
		<div class="bdb-panel"><h2>رشد دنبال‌کننده</h2>
			<?php
			$fs = array_values( array_filter( $series, function ( $r ) {
				return $r['followers'] > 0;
			} ) );
			echo bdb_svg_line( array( array( 'دنبال‌کننده', array_column( $fs, 'followers' ), 's1' ) ), array_map( function ( $r ) { return bdb_jdate( $r['day'], 'day' ); }, $fs ), 180, false ); // phpcs:ignore
			?>
		</div>
	<?php endif; ?>
<?php else : ?>
	<div class="bdb-empty is-small"><p>برای نمودار روند، فایل Export آنالیتیکس لینکدین را بارگذاری کنید.</p></div>
<?php endif; ?>

<?php if ( $ins ) : ?>
	<div class="bdb-panel is-soft">
		<h2><?php echo bdb_icon( 'spark', 18 ); // phpcs:ignore ?>چه چیزی جواب می‌دهد</h2>
		<ul class="bdb-insights"><?php foreach ( $ins as $line ) : ?><li><?php echo esc_html( $line ); ?></li><?php endforeach; ?></ul>
		<p class="bdb-muted bdb-small">این یافته‌ها در هر پست تازه به نویسنده داده می‌شوند (حلقه یادگیری).</p>
	</div>
<?php endif; ?>

<div class="bdb-grid-3 is-top">
	<?php foreach ( $perf as $by => $groups ) : ?>
		<?php if ( ! $groups ) { continue; } ?>
		<div class="bdb-panel">
			<h2><?php echo esc_html( $titles[ $by ] ); ?> <small>نرخ تعامل میانگین</small></h2>
			<?php
			$rows = array();
			foreach ( $groups as $v => $g ) {
				$rows[] = array( bdb_perf_label( $by, $v ) . ' (' . bdb_num( $g['n'] ) . ')', $g['er'], bdb_pct( $g['er'], 1 ) );
			}
			echo bdb_bars( $rows ); // phpcs:ignore
			?>
		</div>
	<?php endforeach; ?>
</div>

<div class="bdb-panel">
	<h2><?php echo bdb_icon( 'flame', 18 ); // phpcs:ignore ?>پست‌های برتر <small>بر اساس نرخ تعامل</small></h2>
	<?php if ( ! $withm ) : ?>
		<p class="bdb-muted">هنوز پستی با آمار نیست. آمار هر پست را در استودیو (بخش «آمار این پست») وارد کنید یا فایل Export را بارگذاری کنید.</p>
	<?php else : ?>
		<div class="bdb-table-wrap">
			<table class="bdb-table">
				<thead><tr><th>پست</th><th>قالب</th><th class="num">نمایش</th><th class="num">واکنش</th><th class="num">کامنت</th><th class="num">بازنشر</th><th class="num">نرخ تعامل</th><th class="num">لید</th></tr></thead>
				<tbody>
					<?php foreach ( array_slice( $withm, 0, 20 ) as $p ) : ?>
						<?php $m = $p['metrics']; ?>
						<tr>
							<td><a href="<?php echo esc_url( bdb_url( 'studio/' . $p['id'] ) ); ?>"><?php echo esc_html( $p['title'] ); ?></a><br><small class="bdb-muted"><?php echo esc_html( bdb_when( $p['published_at'], 'fa' ) ); ?> · <?php echo 'fa' === $p['lang'] ? 'فارسی' : 'English'; ?> · <?php echo esc_html( bdb_pillar_label( $p['pillar'] ) ); ?></small></td>
							<td><?php echo bdb_chip_format( $p['format'] ); // phpcs:ignore ?></td>
							<td class="num"><?php echo esc_html( bdb_num( $m['impressions'] ?? 0 ) ); ?></td>
							<td class="num"><?php echo esc_html( bdb_num( $m['reactions'] ?? 0 ) ); ?></td>
							<td class="num"><?php echo esc_html( bdb_num( $m['comments'] ?? 0 ) ); ?></td>
							<td class="num"><?php echo esc_html( bdb_num( $m['reposts'] ?? 0 ) ); ?></td>
							<td class="num"><b><?php echo esc_html( bdb_pct( bdb_post_er( $p ), 1 ) ); ?></b></td>
							<td class="num"><?php echo esc_html( bdb_num( $m['leads'] ?? 0 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>

<?php if ( $demo ) : ?>
	<div class="bdb-grid-3 is-top">
		<?php foreach ( $demo as $cat => $rows ) : ?>
			<div class="bdb-panel">
				<h2 dir="ltr" style="justify-content:flex-end"><?php echo esc_html( $cat ); ?></h2>
				<?php
				echo bdb_bars( array_map( function ( $r ) { // phpcs:ignore
					return array( $r[0], $r[1], bdb_pct( $r[1], 1 ) );
				}, array_slice( $rows, 0, 6 ) ) );
				?>
			</div>
		<?php endforeach; ?>
	</div>
	<p class="bdb-muted bdb-small">مخاطب‌شناسی از فایل Export: آیا عنوان‌های شغلی و صنایع مخاطب با «مخاطب هدف» استراتژی برند می‌خواند؟</p>
<?php endif; ?>
