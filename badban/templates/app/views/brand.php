<?php
/**
 * Brand strategy: positioning, audience, pillars, voice, CTAs, visual identity.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

$b = bdb_brand();
?>
<div class="bdb-head">
	<div>
		<p class="bdb-eyebrow"><?php echo bdb_icon( 'diamond', 14 ); // phpcs:ignore ?>پایه همه پست‌ها</p>
		<h1>استراتژی برند</h1>
		<p class="bdb-muted">هر پست از همین‌جا شروع می‌شود: چه کسی هستید، برای چه کسی می‌نویسید، درباره چه، با چه صدایی. تغییر این صفحه همه پست‌های بعدی را تغییر می‌دهد.</p>
	</div>
</div>

<form method="post" class="bdb-brand-form">
	<?php bdb_form_fields( 'save_brand' ); ?>
	<div class="bdb-grid-main">
		<div>
			<div class="bdb-panel">
				<h2><?php echo bdb_icon( 'target', 18 ); // phpcs:ignore ?>جایگاه و مخاطب</h2>
				<div class="bdb-grid-2">
					<label>نام (انگلیسی)<input type="text" name="name" dir="ltr" value="<?php echo esc_attr( $b['name'] ); ?>"></label>
					<label>نام (فارسی)<input type="text" name="name_fa" value="<?php echo esc_attr( $b['name_fa'] ); ?>"></label>
					<label>تیتر پروفایل (انگلیسی)<input type="text" name="headline" dir="ltr" value="<?php echo esc_attr( $b['headline'] ); ?>"></label>
					<label>تیتر پروفایل (فارسی)<input type="text" name="headline_fa" value="<?php echo esc_attr( $b['headline_fa'] ); ?>"></label>
				</div>
				<label>جایگاه (Positioning) <small>یک پاراگراف: چه مشکلی را برای چه کسی حل می‌کنید و چرا شما</small><textarea name="positioning" rows="3" dir="ltr"><?php echo esc_textarea( $b['positioning'] ); ?></textarea></label>
				<div class="bdb-grid-2">
					<label>مخاطب هدف انگلیسی <small>هر خط یک گروه</small><textarea name="audience" rows="4" dir="ltr"><?php echo esc_textarea( $b['audience'] ); ?></textarea></label>
					<label>مخاطب هدف فارسی<textarea name="audience_fa" rows="4"><?php echo esc_textarea( $b['audience_fa'] ); ?></textarea></label>
					<label>اهداف برند شخصی<textarea name="goals" rows="4" dir="ltr"><?php echo esc_textarea( $b['goals'] ); ?></textarea></label>
					<label>شواهد و اعتبار <small>واقعی؛ نویسنده اغراق نمی‌کند</small><textarea name="proof" rows="4" dir="ltr"><?php echo esc_textarea( $b['proof'] ); ?></textarea></label>
				</div>
			</div>

			<div class="bdb-panel">
				<div class="bdb-panel-head">
					<h2><?php echo bdb_icon( 'layers', 18 ); // phpcs:ignore ?>ستون‌های محتوا <small>وزن = سهم هدف از پست‌ها</small></h2>
					<button type="button" class="bdb-btn is-small" data-bdb-pillar-add><?php echo bdb_icon( 'plus', 14 ); // phpcs:ignore ?>ستون</button>
				</div>
				<div class="bdb-pillars-edit" data-bdb-pillars>
					<?php foreach ( $b['pillars'] as $k => $pl ) : ?>
						<div class="bdb-pillar-row">
							<input type="hidden" name="pillar_key[]" value="<?php echo esc_attr( $k ); ?>">
							<input type="text" name="pillar_label[]" value="<?php echo esc_attr( $pl[0] ); ?>" placeholder="نام ستون">
							<input type="text" name="pillar_desc[]" dir="ltr" value="<?php echo esc_attr( $pl[1] ); ?>" placeholder="Description for the writer">
							<input type="number" name="pillar_weight[]" min="0" max="100" value="<?php echo esc_attr( $pl[2] ); ?>" title="وزن ٪">
							<button type="button" class="bdb-icon-btn is-del" data-bdb-row-del title="حذف"><?php echo bdb_icon( 'trash', 16 ); // phpcs:ignore ?></button>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="bdb-panel">
				<h2><?php echo bdb_icon( 'pen', 18 ); // phpcs:ignore ?>صدای برند</h2>
				<div class="bdb-grid-2">
					<label>لحن و سبک <small>هر خط یک قاعده</small><textarea name="voice" rows="5" dir="ltr"><?php echo esc_textarea( $b['voice'] ); ?></textarea></label>
					<label>هرگز استفاده نکن <small>عبارت‌های کلیشه‌ای؛ بررسی کیفیت هم چک می‌کند</small><textarea name="avoid" rows="5" dir="ltr"><?php echo esc_textarea( $b['avoid'] ); ?></textarea></label>
				</div>
				<label>نمونه نوشته‌های شما <small>۳ تا ۵ پست یا متن خودتان (فارسی یا انگلیسی)؛ نویسنده ریتم و لحن را از این‌ها یاد می‌گیرد</small><textarea name="samples" rows="8" dir="auto"><?php echo esc_textarea( $b['samples'] ); ?></textarea></label>
			</div>
		</div>

		<div>
			<div class="bdb-panel">
				<h2><?php echo bdb_icon( 'magnet', 18 ); // phpcs:ignore ?>CTA و لید</h2>
				<label>لینک رزرو جلسه <small>در کامنت اول پست‌های «رزرو جلسه»</small><input type="url" name="booking_url" dir="ltr" value="<?php echo esc_attr( $b['booking_url'] ); ?>" placeholder="https://calendly.com/…"></label>
				<div class="bdb-grid-2">
					<label>لید مگنت<input type="text" name="magnet" dir="ltr" value="<?php echo esc_attr( $b['magnet'] ); ?>"></label>
					<label>کلمه کامنت<input type="text" name="magnet_word" dir="ltr" value="<?php echo esc_attr( $b['magnet_word'] ); ?>"></label>
				</div>
				<label>امضای پایان پست (انگلیسی)<input type="text" name="signature" dir="ltr" value="<?php echo esc_attr( $b['signature'] ); ?>"></label>
				<label>امضای پایان پست (فارسی)<input type="text" name="signature_fa" value="<?php echo esc_attr( $b['signature_fa'] ); ?>"></label>
				<label>هشتگ‌های انگلیسی<input type="text" name="hashtags_en" dir="ltr" value="<?php echo esc_attr( $b['hashtags_en'] ); ?>"></label>
				<label>هشتگ‌های فارسی<input type="text" name="hashtags_fa" value="<?php echo esc_attr( $b['hashtags_fa'] ); ?>"></label>
			</div>

			<div class="bdb-panel">
				<h2><?php echo bdb_icon( 'diamond', 18 ); // phpcs:ignore ?>هویت بصری اسلایدها</h2>
				<div class="bdb-grid-3 is-tight">
					<label>رنگ اصلی<input type="color" name="brand_color" value="<?php echo esc_attr( $b['brand_color'] ); ?>"></label>
					<label>رنگ کریستالی<input type="color" name="brand_color2" value="<?php echo esc_attr( $b['brand_color2'] ); ?>"></label>
					<label>رنگ تیره<input type="color" name="brand_dark" value="<?php echo esc_attr( $b['brand_dark'] ); ?>"></label>
				</div>
				<label>آدرس عکس پروفایل <small>از رسانه همین وردپرس (برای اسلاید آخر)</small><input type="url" name="photo_url" dir="ltr" value="<?php echo esc_attr( $b['photo_url'] ); ?>"></label>
				<label>هندل / آدرس پروفایل <small>روی اسلایدها</small><input type="text" name="handle" dir="ltr" value="<?php echo esc_attr( $b['handle'] ); ?>" placeholder="linkedin.com/in/…"></label>
				<canvas class="bdb-brand-preview" data-bdb-brand-preview width="1080" height="1350"></canvas>
			</div>

			<div class="bdb-panel">
				<h2><?php echo bdb_icon( 'shield', 18 ); // phpcs:ignore ?>حریم کارفرماها</h2>
				<p class="bdb-small bdb-muted">نام، دامنه، برندها و رقبای همه پروژه‌های سکان، قطب‌نما و فانوس خودکار ناشناس می‌شوند و اگر در پستی بمانند، انتشار متوقف می‌شود. کلمات دیگری که نباید دیده شوند:</p>
				<label>کلمات خصوصی دیگر <small>هر خط یکی</small><textarea name="private_terms" rows="4" dir="auto"><?php echo esc_textarea( $b['private_terms'] ?? '' ); ?></textarea></label>
				<p class="bdb-small">در حال حاضر <b><?php echo esc_html( bdb_num( count( bdb_private_terms() ) ) ); ?></b> نام و دامنه محافظت می‌شود.</p>
			</div>
		</div>
	</div>
	<div class="bdb-sticky-foot"><button class="bdb-btn is-primary" type="submit"><?php echo bdb_icon( 'check', 16 ); // phpcs:ignore ?>ذخیره استراتژی برند</button></div>
</form>
