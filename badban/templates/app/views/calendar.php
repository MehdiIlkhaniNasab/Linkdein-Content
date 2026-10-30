<?php
/**
 * Publishing calendar: the weekly slots, the next four weeks, the automatic plan.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

$sched = bdb_schedule();
$days  = bdb_weekdays();
$weeks = 4;
$from  = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
$to    = gmdate( 'Y-m-d H:i:s', time() + $weeks * WEEK_IN_SECONDS );
$posts = bdb_posts( array( 'status' => array( 'draft', 'ready', 'scheduled', 'publishing', 'published', 'failed' ), 'from' => $from, 'to' => $to, 'order' => 'schedule', 'limit' => 200 ) );
// Agenda by Tehran day: posts + empty slots.
$agenda = array();
$teh    = new DateTimeZone( 'Asia/Tehran' );
foreach ( $posts as $p ) {
	$t = $p['published_at'] ?: $p['scheduled_at'];
	if ( ! $t ) {
		continue;
	}
	$d                            = ( new DateTime( $t, new DateTimeZone( 'UTC' ) ) )->setTimezone( $teh );
	$agenda[ $d->format( 'Y-m-d' ) ][] = array( 'post' => $p, 'time' => $d->format( 'H:i' ) );
}
foreach ( array( 'en', 'fa' ) as $lang ) {
	foreach ( bdb_slot_times( $lang, $weeks * 7 ) as $slot ) {
		if ( bdb_slot_taken( $slot, $lang ) ) {
			continue;
		}
		$d                            = ( new DateTime( $slot, new DateTimeZone( 'UTC' ) ) )->setTimezone( $teh );
		$agenda[ $d->format( 'Y-m-d' ) ][] = array( 'slot' => $slot, 'lang' => $lang, 'time' => $d->format( 'H:i' ) );
	}
}
ksort( $agenda );
$today = ( new DateTime( 'now', $teh ) )->format( 'Y-m-d' );
?>
<div class="bdb-head">
	<div>
		<p class="bdb-eyebrow"><?php echo bdb_icon( 'calendar', 14 ); // phpcs:ignore ?>ثبات، رمز برند شخصی است</p>
		<h1>تقویم انتشار</h1>
		<p class="bdb-muted">انگلیسی و فارسی در روزهای جدا. زمان‌ها به وقت مخاطب هر زبان تنظیم می‌شوند و این‌جا به وقت تهران نمایش داده می‌شوند.</p>
	</div>
	<div class="bdb-actions">
		<button type="button" class="bdb-btn" data-bdb-plan-start="1"><?php echo bdb_icon( 'spark', 16 ); // phpcs:ignore ?>نوشتن هفته آینده</button>
		<button type="button" class="bdb-btn is-primary" data-bdb-plan-start="4"><?php echo bdb_icon( 'spark', 16 ); // phpcs:ignore ?>برنامه‌ریزی ۴ هفته</button>
	</div>
</div>

<div class="bdb-panel is-hero is-compact"><?php bdb_view( 'part-plan' ); ?><p class="bdb-small" style="margin:0">برنامه‌ریزی خودکار: هر جایگاه خالی با بهترین ایده بانک (اولویت با ستون‌های عقب‌مانده)، قالب متنوع و CTA چرخشی پر می‌شود؛ جایگاه فارسی نسخه فارسیِ پست انگلیسیِ چند روز قبل را می‌گیرد. پست‌ها در «آماده تأیید» می‌مانند تا شما تأیید کنید.</p></div>

<div class="bdb-agenda">
	<?php if ( ! $agenda ) : ?>
		<div class="bdb-empty"><h2>جایگاهی تعریف نشده</h2><p>در پایین همین صفحه روزها و ساعت‌های انتشار را انتخاب کنید.</p></div>
	<?php endif; ?>
	<?php foreach ( $agenda as $day => $items ) : ?>
		<?php usort( $items, function ( $a, $b ) { return strcmp( $a['time'], $b['time'] ); } ); ?>
		<div class="bdb-day <?php echo $day === $today ? 'is-today' : ''; ?>">
			<div class="bdb-day-h"><b><?php echo esc_html( bdb_jweekday( $day ) ); ?></b><span><?php echo esc_html( bdb_jdate( $day, 'day' ) ); ?></span><small dir="ltr"><?php echo esc_html( gmdate( 'D j M', strtotime( $day ) ) ); ?></small></div>
			<div class="bdb-day-items">
				<?php foreach ( $items as $it ) : ?>
					<?php if ( isset( $it['post'] ) ) : ?>
						<?php bdb_view( 'part-post-row', array( 'p' => $it['post'] ) ); ?>
					<?php else : ?>
						<div class="bdb-slot">
							<span><?php echo bdb_chip_lang( $it['lang'] ); // phpcs:ignore ?> <b><?php echo esc_html( bdb_fa( $it['time'] ) ); ?></b> تهران · <?php echo esc_html( bdb_when( $it['slot'], $it['lang'], false ) ); ?></span>
							<span class="bdb-muted">جایگاه خالی</span>
							<a class="bdb-btn is-small" href="<?php echo esc_url( bdb_url( 'studio', array( 'lang' => $it['lang'] ) ) ); ?>"><?php echo bdb_icon( 'pen', 14 ); // phpcs:ignore ?>نوشتن</a>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<form class="bdb-panel" method="post" id="schedule">
	<?php bdb_form_fields( 'save_schedule' ); ?>
	<h2><?php echo bdb_icon( 'clock', 18 ); // phpcs:ignore ?>برنامه هفتگی انتشار</h2>
	<div class="bdb-grid-2">
		<label>منطقه زمانی مخاطب انگلیسی<select name="audience_tz"><?php bdb_options( bdb_timezones(), bdb_setting( 'audience_tz', 'Europe/London' ) ); ?></select><small>بهترین زمان لینکدین: صبح روزهای کاری مخاطب (۸ تا ۱۰).</small></label>
		<label>هدف هفتگی (هر دو زبان)<input type="number" name="weekly" min="1" max="14" value="<?php echo esc_attr( $sched['weekly'] ); ?>"><small>هفته‌ای با ۶۰٪ هدف «موفق» حساب می‌شود (رکورد ثبات).</small></label>
	</div>
	<div class="bdb-table-wrap">
		<table class="bdb-table bdb-slots-table">
			<thead><tr><th>روز</th><th>English <small>(وقت مخاطب)</small></th><th>فارسی <small>(وقت تهران)</small></th></tr></thead>
			<tbody>
				<?php foreach ( $days as $d => $label ) : ?>
					<?php
					$en = array_values( array_filter( $sched['en'], function ( $s ) use ( $d ) { return (int) $s['day'] === $d; } ) );
					$fa = array_values( array_filter( $sched['fa'], function ( $s ) use ( $d ) { return (int) $s['day'] === $d; } ) );
					?>
					<tr>
						<td><b><?php echo esc_html( $label ); ?></b></td>
						<td><label class="bdb-check"><input type="checkbox" name="on_en[<?php echo esc_attr( $d ); ?>]" value="1" <?php checked( (bool) $en ); ?>><input type="time" name="slot_en[<?php echo esc_attr( $d ); ?>]" value="<?php echo esc_attr( $en[0]['time'] ?? '08:30' ); ?>" dir="ltr"></label></td>
						<td><label class="bdb-check"><input type="checkbox" name="on_fa[<?php echo esc_attr( $d ); ?>]" value="1" <?php checked( (bool) $fa ); ?>><input type="time" name="slot_fa[<?php echo esc_attr( $d ); ?>]" value="<?php echo esc_attr( $fa[0]['time'] ?? '09:00' ); ?>" dir="ltr"></label></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<p class="bdb-muted bdb-small">پیشنهاد: انگلیسی سه‌شنبه تا پنجشنبه ۸:۳۰ صبح وقت مخاطب؛ فارسی شنبه و دوشنبه. روز فارسی و انگلیسی یکی نباشد تا مخاطب دو بار پشت سر هم پست نبیند.</p>
	<button class="bdb-btn is-primary" type="submit">ذخیره برنامه</button>
</form>
