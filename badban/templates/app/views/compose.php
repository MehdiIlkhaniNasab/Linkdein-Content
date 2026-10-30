<?php
/**
 * Write a new post: topic or idea, language, format, structure, CTA.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

$ideas   = bdb_ideas( array( 'limit' => 60 ) );
$pick    = absint( $_GET['idea'] ?? 0 ); // phpcs:ignore
$lang    = 'fa' === ( $_GET['lang'] ?? '' ) ? 'fa' : 'en'; // phpcs:ignore
?>
<div class="bdb-head">
	<div>
		<p class="bdb-eyebrow"><?php echo bdb_icon( 'pen', 14 ); // phpcs:ignore ?>استودیو محتوا</p>
		<h1>نوشتن پست تازه</h1>
		<p class="bdb-muted">موضوع یا ایده را انتخاب کنید؛ بادبان با صدای برند شما، قواعد لینکدین و تجربه پست‌های قبلی می‌نویسد. بعد در ویرایشگر همه‌چیز قابل تغییر است.</p>
	</div>
</div>

<form class="bdb-compose" data-bdb-compose>
	<div class="bdb-panel">
		<h2><?php echo bdb_icon( 'idea', 18 ); // phpcs:ignore ?>درباره چه بنویسیم؟</h2>
		<?php bdb_segment( 'source', array( 'idea' => 'از بانک ایده', 'topic' => 'موضوع خودم' ), $pick || $ideas ? 'idea' : 'topic', true ); ?>
		<div data-bdb-when="source-idea">
			<label>ایده
				<select name="idea">
					<option value="">— انتخاب ایده —</option>
					<?php foreach ( $ideas as $i ) : ?>
						<option value="<?php echo esc_attr( $i['id'] ); ?>" <?php selected( $pick, $i['id'] ); ?>><?php echo esc_html( '[' . bdb_num( $i['score'] ) . '] ' . $i['title'] . ' — ' . ( bdb_idea_sources()[ $i['source'] ][0] ?? '' ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<small>ایده‌های سکان، قطب‌نما و فانوس با داده‌های ناشناس‌شده همان پروژه نوشته می‌شوند.</small>
			</label>
		</div>
		<div data-bdb-when="source-topic">
			<label>موضوع<textarea name="topic" rows="2" dir="auto" placeholder="مثلاً: How I fixed indexing for a 20k-page store in 3 weeks"></textarea></label>
			<label>زاویه و نکته اصلی (اختیاری)<textarea name="angle" rows="2" dir="auto" placeholder="حرف اصلی، تجربه شخصی یا عددی که می‌خواهید در پست باشد"></textarea></label>
		</div>
	</div>

	<div class="bdb-panel">
		<h2><?php echo bdb_icon( 'layers', 18 ); // phpcs:ignore ?>قالب و زبان</h2>
		<div class="bdb-grid-2">
			<div><p class="bdb-label">زبان</p><?php bdb_segment( 'lang', array( 'en' => 'English (مخاطب بین‌المللی)', 'fa' => 'فارسی' ), $lang ); ?></div>
			<label>ستون محتوا<select name="pillar"><option value="">پیشنهاد ایده / خودکار</option><?php bdb_options( bdb_pillars(), '' ); ?></select></label>
		</div>
		<div class="bdb-choice is-formats">
			<label><input type="radio" name="format" value="" checked><span><b>خودکار</b><small>بهترین قالب برای ساختار ایده</small></span></label>
			<?php foreach ( bdb_formats() as $k => $f ) : ?>
				<label><input type="radio" name="format" value="<?php echo esc_attr( $k ); ?>"><span><b><?php echo bdb_icon( $f[1], 15 ); // phpcs:ignore ?><?php echo esc_html( $f[0] ); ?></b><small><?php echo esc_html( $f[2] ); ?></small></span></label>
			<?php endforeach; ?>
		</div>
	</div>

	<details class="bdb-panel">
		<summary><?php echo bdb_icon( 'settings', 18 ); // phpcs:ignore ?>ساختار، قلاب و CTA <small class="bdb-muted">(پیش‌فرض: خودکار)</small></summary>
		<div class="bdb-grid-3">
			<label>ساختار پست<select name="framework"><option value="">خودکار</option><?php bdb_options( bdb_frameworks(), '' ); ?></select></label>
			<label>نوع قلاب<select name="hook_type"><option value="">خودکار</option><?php bdb_options( bdb_hook_types(), '' ); ?></select></label>
			<label>کال تو اکشن<select name="cta_type"><option value="">چرخشی خودکار</option><?php bdb_options( bdb_cta_types(), '' ); ?></select></label>
			<label>تعداد اسلاید (کاروسل)<input type="number" name="slides" min="6" max="10" value="8"></label>
			<label class="bdb-span-2">دستور اضافه به نویسنده<input type="text" name="extra" dir="auto" placeholder="مثلاً: لحن داستانی‌تر؛ به تجربه مهاجرت سایت اشاره کن"></label>
		</div>
	</details>

	<div class="bdb-form-foot">
		<label class="bdb-check"><input type="checkbox" name="when" value="next" checked> در اولین جایگاه خالی تقویم قرار بگیرد</label>
		<button class="bdb-btn is-primary" type="submit"><?php echo bdb_icon( 'spark', 16 ); // phpcs:ignore ?>بنویس</button>
		<span class="bdb-muted bdb-small" data-bdb-compose-status></span>
	</div>
</form>
