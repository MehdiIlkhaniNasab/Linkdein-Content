<?php
/**
 * Dashboard: consistency, growth, what is next, what needs attention.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

$b       = bdb_brand();
$cons    = bdb_consistency();
$today   = current_time( 'Y-m-d' );
$d30     = gmdate( 'Y-m-d', strtotime( $today . ' -29 days' ) );
$d60     = gmdate( 'Y-m-d', strtotime( $today . ' -59 days' ) );
$d31     = gmdate( 'Y-m-d', strtotime( $today . ' -30 days' ) );
$now30   = bdb_stats_totals( $d30, $today );
$prev30  = bdb_stats_totals( $d60, $d31 );
$series  = bdb_stats( $d30, $today );
$counts  = bdb_post_counts();
$month0  = gmdate( 'Y-m-01 00:00:00' );
$pub_m   = count( bdb_posts( array( 'status' => 'published', 'from' => $month0, 'limit' => 500 ) ) );
$next    = bdb_posts( array( 'status' => array( 'scheduled', 'ready', 'publishing' ), 'order' => 'schedule', 'limit' => 6 ) );
$failed  = bdb_posts( array( 'status' => 'failed', 'limit' => 5 ) );
$ideas   = bdb_ideas( array( 'limit' => 5 ) );
$balance = bdb_pillar_balance();
$left    = bdb_li_days_left();
$cron    = (int) get_option( 'bdb_cron_last', 0 );
$import  = get_option( 'bdb_last_import', array() );
$alerts  = array();
if ( ! bdb_li_connected() ) {
	$alerts[] = array( 'err', 'لینکدین وصل نیست؛ انتشار خودکار انجام نمی‌شود.', bdb_url( 'settings' ), 'اتصال' );
} elseif ( null !== $left && $left <= 10 ) {
	$alerts[] = array( 'warn', sprintf( 'توکن لینکدین %s روز دیگر منقضی می‌شود؛ یک بار دوباره متصل شوید.', bdb_num( max( 0, $left ) ) ), bdb_url( 'linkedin-connect' ), 'اتصال دوباره' );
}
if ( ! bdb_ai_connected() ) {
	$alerts[] = array( 'err', 'هوش مصنوعی وصل نیست (نه مدل سکان، نه کلید اختصاصی).', bdb_url( 'settings' ), 'تنظیمات' );
}
foreach ( $failed as $f ) {
	$alerts[] = array( 'err', 'انتشار ناموفق: «' . $f['title'] . '» — ' . mb_substr( (string) $f['error'], 0, 140 ), bdb_url( 'studio/' . $f['id'] ), 'بررسی' );
}
if ( ( $counts['scheduled'] ?? 0 ) && $cron < time() - 30 * MINUTE_IN_SECONDS ) {
	$alerts[] = array( 'warn', 'زمان‌بند وردپرس (WP-Cron) در ۳۰ دقیقه اخیر اجرا نشده؛ برای انتشار سر وقت، کران واقعی سرور را طبق راهنما تنظیم کنید.', bdb_url( 'guide' ), 'راهنما' );
}
if ( empty( $import['at'] ) || strtotime( $import['at'] ) < time() - 14 * DAY_IN_SECONDS ) {
	$alerts[] = array( 'info', 'آمار لینکدین بیش از دو هفته است وارد نشده؛ فایل Export آنالیتیکس را بارگذاری کنید.', bdb_url( 'analytics' ), 'ورود آمار' );
}
if ( ( $counts['ready'] ?? 0 ) ) {
	$alerts[] = array( 'info', sprintf( '%s پست آماده منتظر تأیید شماست.', bdb_num( $counts['ready'] ) ), bdb_url( 'queue', array( 'tab' => 'ready' ) ), 'تأیید' );
}
$hour  = (int) current_time( 'G' );
$hello = $hour < 12 ? 'صبح بخیر' : ( $hour < 18 ? 'روز بخیر' : 'عصر بخیر' );
?>
<div class="bdb-head">
	<div>
		<p class="bdb-eyebrow"><?php echo bdb_icon( 'sail', 14 ); // phpcs:ignore ?><?php echo esc_html( bdb_jdate( $today ) ); ?></p>
		<h1><?php echo esc_html( $hello . '، ' . strtok( $b['name_fa'], ' ' ) ); ?></h1>
		<p class="bdb-muted">بادبان برند شخصی شما: ثبات در انتشار، محتوای کاربردی از دل پروژه‌ها، رشد قابل اندازه‌گیری.</p>
	</div>
	<div class="bdb-actions">
		<a class="bdb-btn" href="<?php echo esc_url( bdb_url( 'ideas' ) ); ?>"><?php echo bdb_icon( 'idea', 16 ); // phpcs:ignore ?>بانک ایده</a>
		<a class="bdb-btn is-primary" href="<?php echo esc_url( bdb_url( 'studio' ) ); ?>"><?php echo bdb_icon( 'pen', 16 ); // phpcs:ignore ?>پست تازه</a>
	</div>
</div>

<?php foreach ( $alerts as $a ) : ?>
	<div class="bdb-note is-<?php echo esc_attr( $a[0] ); ?>"><?php echo bdb_icon( 'err' === $a[0] ? 'alert' : ( 'warn' === $a[0] ? 'clock' : 'spark' ), 16 ); // phpcs:ignore ?><span><?php echo esc_html( $a[1] ); ?></span><a class="bdb-btn is-small" href="<?php echo esc_url( $a[2] ); ?>"><?php echo esc_html( $a[3] ); ?></a></div>
<?php endforeach; ?>

<div class="bdb-panel is-hero">
	<div class="bdb-hero">
		<div>
			<p class="bdb-eyebrow is-light"><?php echo bdb_icon( 'flame', 14 ); // phpcs:ignore ?>ثبات انتشار</p>
			<h2><?php echo $cons['streak'] ? esc_html( sprintf( '%s هفته پشت سر هم با حداقل %s پست', bdb_num( $cons['streak'] ), bdb_num( $cons['need'] ) ) ) : 'هفته اول ثبات را از همین حالا بسازید'; ?></h2>
			<p>این هفته <b><?php echo esc_html( bdb_num( $cons['this_week'] ) ); ?></b> از <b><?php echo esc_html( bdb_num( $cons['goal'] ) ); ?></b> پست هدف منتشر شده · در صف: <b><?php echo esc_html( bdb_num( (int) ( $counts['scheduled'] ?? 0 ) ) ); ?></b> زمان‌بندی‌شده، <b><?php echo esc_html( bdb_num( (int) ( $counts['ready'] ?? 0 ) ) ); ?></b> آماده تأیید</p>
			<div class="bdb-actions" style="margin-top:14px">
				<button type="button" class="bdb-btn is-gem" data-bdb-plan-start="2"><?php echo bdb_icon( 'spark', 16 ); // phpcs:ignore ?>برنامه‌ریزی و نوشتن ۲ هفته آینده</button>
				<a class="bdb-btn is-glass" href="<?php echo esc_url( bdb_url( 'calendar' ) ); ?>"><?php echo bdb_icon( 'calendar', 16 ); // phpcs:ignore ?>تقویم</a>
			</div>
		</div>
		<div class="bdb-weeks" title="پست‌های منتشرشده در ۱۲ هفته اخیر">
			<?php $wmax = max( array_merge( array( $cons['goal'] ), array_values( $cons['weeks'] ) ) ); ?>
			<?php foreach ( $cons['weeks'] as $w => $n ) : ?>
				<span class="<?php echo $n >= $cons['need'] ? 'is-hit' : ''; ?>" title="<?php echo esc_attr( bdb_jdate( gmdate( 'Y-m-d', $w ), 'day' ) . ': ' . bdb_num( $n ) . ' پست' ); ?>"><i style="height:<?php echo esc_attr( max( 6, round( 100 * $n / max( 1, $wmax ) ) ) ); ?>%"></i></span>
			<?php endforeach; ?>
			<small>۱۲ هفته اخیر</small>
		</div>
	</div>
	<?php bdb_view( 'part-plan' ); ?>
</div>

<div class="bdb-stats is-6">
	<div class="bdb-stat"><span>دنبال‌کننده</span><b><?php echo esc_html( $now30['followers'] ? bdb_num( $now30['followers'] ) : '—' ); ?></b><small><?php echo $now30['new_followers'] ? esc_html( '+' . bdb_num( $now30['new_followers'] ) . ' در ۳۰ روز' ) : 'از فایل آنالیتیکس'; ?></small></div>
	<div class="bdb-stat"><span>نمایش ۳۰ روز</span><b><?php echo esc_html( bdb_compact( $now30['impressions'] ) ); ?></b><small><?php echo bdb_delta( $now30['impressions'], $prev30['impressions'] ); // phpcs:ignore ?> نسبت به ۳۰ روز قبل</small></div>
	<div class="bdb-stat"><span>نرخ تعامل</span><b><?php echo esc_html( null !== $now30['er'] ? bdb_pct( $now30['er'], 1 ) : '—' ); ?></b><small><?php echo esc_html( bdb_compact( $now30['engagements'] ) ); ?> تعامل</small></div>
	<div class="bdb-stat is-gem"><span>منتشرشده این ماه</span><b><?php echo esc_html( bdb_num( $pub_m ) ); ?></b><small>کل: <?php echo esc_html( bdb_num( (int) ( $counts['published'] ?? 0 ) ) ); ?> پست</small></div>
	<div class="bdb-stat"><span>زمان‌بندی‌شده</span><b><?php echo esc_html( bdb_num( (int) ( $counts['scheduled'] ?? 0 ) ) ); ?></b><small>انتشار خودکار <?php echo (int) bdb_setting( 'auto_publish', 1 ) ? 'روشن' : 'خاموش'; ?></small></div>
	<div class="bdb-stat"><span>آماده تأیید</span><b><?php echo esc_html( bdb_num( (int) ( $counts['ready'] ?? 0 ) ) ); ?></b><small><?php echo esc_html( bdb_num( (int) ( $counts['draft'] ?? 0 ) ) ); ?> پیش‌نویس</small></div>
</div>

<div class="bdb-grid-main">
	<div>
		<div class="bdb-panel">
			<div class="bdb-panel-head">
				<h2><?php echo bdb_icon( 'clock', 18 ); // phpcs:ignore ?>پست‌های بعدی</h2>
				<a class="bdb-btn is-small" href="<?php echo esc_url( bdb_url( 'calendar' ) ); ?>">همه در تقویم</a>
			</div>
			<?php if ( ! $next ) : ?>
				<p class="bdb-muted">صف خالی است. «برنامه‌ریزی و نوشتن ۲ هفته آینده» را بزنید تا جایگاه‌های هفته پر شوند.</p>
			<?php endif; ?>
			<div class="bdb-postlist">
				<?php foreach ( $next as $p ) : ?>
					<?php bdb_view( 'part-post-row', array( 'p' => $p ) ); ?>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="bdb-panel">
			<div class="bdb-panel-head">
				<h2><?php echo bdb_icon( 'chart', 18 ); // phpcs:ignore ?>نمایش روزانه <small>۳۰ روز اخیر</small></h2>
				<a class="bdb-btn is-small" href="<?php echo esc_url( bdb_url( 'analytics' ) ); ?>">آنالیتیکس کامل</a>
			</div>
			<?php if ( count( $series ) >= 3 ) : ?>
				<?php
				echo bdb_svg_line( // phpcs:ignore
					array( array( 'نمایش', array_column( $series, 'impressions' ), 's1', true ) ),
					array_map( function ( $r ) {
						return bdb_jdate( $r['day'], 'day' );
					}, $series ),
					200
				);
				?>
			<?php else : ?>
				<div class="bdb-empty is-small">
					<p>هنوز آماری وارد نشده. در لینکدین از Analytics › Content › Export فایل XLSX را بگیرید و در صفحه آنالیتیکس بارگذاری کنید.</p>
					<a class="bdb-btn is-small" href="<?php echo esc_url( bdb_url( 'analytics' ) ); ?>"><?php echo bdb_icon( 'upload', 14 ); // phpcs:ignore ?>ورود آمار</a>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div>
		<div class="bdb-panel">
			<h2><?php echo bdb_icon( 'layers', 18 ); // phpcs:ignore ?>تعادل ستون‌های محتوا <small>۳۰ روز</small></h2>
			<div class="bdb-balance">
				<?php foreach ( $balance as $k => $bal ) : ?>
					<div class="bdb-balance-row">
						<span><?php echo esc_html( bdb_pillar_label( $k ) ); ?></span>
						<div class="bdb-bar is-dual"><i style="width:<?php echo esc_attr( min( 100, $bal[1] ) ); ?>%"></i><u style="right:<?php echo esc_attr( min( 100, $bal[0] ) ); ?>%" title="هدف <?php echo esc_attr( bdb_num( $bal[0] ) ); ?>٪"></u></div>
						<b><?php echo esc_html( bdb_num( $bal[1] ) . '٪' ); ?></b>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="bdb-muted bdb-small">نوار: سهم واقعی · خط عمودی: سهم هدف (از استراتژی برند). برنامه‌ریزی خودکار ستون‌های عقب‌مانده را جلو می‌اندازد.</p>
		</div>

		<div class="bdb-panel">
			<div class="bdb-panel-head">
				<h2><?php echo bdb_icon( 'idea', 18 ); // phpcs:ignore ?>بهترین ایده‌ها</h2>
				<a class="bdb-btn is-small" href="<?php echo esc_url( bdb_url( 'ideas' ) ); ?>">بانک ایده</a>
			</div>
			<?php if ( ! $ideas ) : ?>
				<p class="bdb-muted">بانک ایده خالی است.</p>
				<button type="button" class="bdb-btn is-small" data-bdb-harvest><?php echo bdb_icon( 'refresh', 14 ); // phpcs:ignore ?>جمع‌آوری ایده از سکان، قطب‌نما و فانوس</button>
			<?php endif; ?>
			<ul class="bdb-idea-mini">
				<?php foreach ( $ideas as $i ) : ?>
					<li>
						<span class="bdb-score"><?php echo esc_html( bdb_num( $i['score'] ) ); ?></span>
						<div><b><?php echo esc_html( $i['title'] ); ?></b><small><?php echo esc_html( ( bdb_idea_sources()[ $i['source'] ][0] ?? $i['source'] ) . ' · ' . bdb_pillar_label( $i['pillar'] ) ); ?></small></div>
						<button type="button" class="bdb-icon-btn" data-bdb-write-idea="<?php echo esc_attr( $i['id'] ); ?>" title="نوشتن پست از این ایده"><?php echo bdb_icon( 'pen', 16 ); // phpcs:ignore ?></button>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</div>
