<?php
/**
 * Settings: LinkedIn connection, network route, AI, publishing.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

$s     = bdb_settings();
$left  = bdb_li_days_left();
$usage = bdb_ai_usage();
$cron  = (int) get_option( 'bdb_cron_last', 0 );
$log   = array_reverse( (array) get_option( 'bdb_log', array() ) );
$mask  = function ( $k ) use ( $s ) {
	return bdb_mask( $s[ $k ] );
};
?>
<div class="bdb-head">
	<div>
		<p class="bdb-eyebrow"><?php echo bdb_icon( 'settings', 14 ); // phpcs:ignore ?>اتصال‌ها</p>
		<h1>تنظیمات</h1>
		<p class="bdb-muted">اتصال به پروفایل لینکدین، مسیر شبکه (سرور در ایران)، هوش مصنوعی و انتشار خودکار.</p>
	</div>
</div>

<form method="post">
	<?php bdb_form_fields( 'save_settings' ); ?>

	<div class="bdb-panel">
		<div class="bdb-panel-head">
			<h2><?php echo bdb_icon( 'linkedin', 18 ); // phpcs:ignore ?>اتصال به لینکدین</h2>
			<button type="button" class="bdb-btn is-small" data-bdb-test="li"><?php echo bdb_icon( 'bolt', 14 ); // phpcs:ignore ?>تست اتصال</button>
		</div>
		<div class="bdb-status-row">
			<?php if ( bdb_li_connected() ) : ?>
				<?php bdb_conn( 'متصل: ' . $s['li_name'] . ( $s['li_email'] ? ' (' . $s['li_email'] . ')' : '' ), true ); ?>
				<span class="bdb-muted bdb-small">توکن تا <?php echo esc_html( bdb_jdate( gmdate( 'Y-m-d', (int) $s['li_expires'] ) ) ); ?> (<?php echo esc_html( bdb_num( max( 0, (int) $left ) ) ); ?> روز) معتبر است؛ ۱۰ روز قبل از پایان ایمیل یادآوری می‌آید.</span>
			<?php else : ?>
				<?php bdb_conn( null !== $left && $left < 0 ? 'توکن منقضی شده' : 'وصل نیست', false ); ?>
			<?php endif; ?>
		</div>
		<div class="bdb-test-out" data-bdb-test-out="li" hidden></div>
		<div class="bdb-grid-3">
			<label>Client ID<input type="text" name="li_client_id" dir="ltr" value="<?php echo esc_attr( $s['li_client_id'] ); ?>"></label>
			<label>Client Secret<input type="password" name="li_client_secret" dir="ltr" value="<?php echo esc_attr( $mask( 'li_client_secret' ) ); ?>" autocomplete="off"></label>
			<label>نسخه API (LinkedIn-Version)<input type="text" name="li_version" dir="ltr" value="<?php echo esc_attr( $s['li_version'] ); ?>"><small>YYYYMM؛ اگر لینکدین خطای نسخه داد، ماه جدیدتر بگذارید.</small></label>
		</div>
		<div class="bdb-kv">
			<div><span>Redirect URL (در برنامه لینکدین)</span><code data-bdb-copy dir="ltr"><?php echo esc_html( bdb_li_redirect_uri() ); ?></code></div>
		</div>
		<div class="bdb-actions" style="margin-top:12px">
			<a class="bdb-btn is-primary" href="<?php echo esc_url( bdb_url( 'linkedin-connect' ) ); ?>"><?php echo bdb_icon( 'linkedin', 16 ); // phpcs:ignore ?><?php echo bdb_li_connected() ? 'اتصال دوباره (تمدید توکن)' : 'اتصال به لینکدین'; ?></a>
			<?php if ( '' !== (string) $s['li_token'] ) : ?>
				<button class="bdb-btn is-ghost is-danger" type="submit" form="bdb-disconnect">قطع اتصال</button>
			<?php endif; ?>
		</div>
		<p class="bdb-muted bdb-small">ساخت برنامه: developer.linkedin.com › Create app (یک صفحه لینکدین برای تأیید برنامه لازم است) › Products: «Share on LinkedIn» و «Sign In with LinkedIn using OpenID Connect» › Auth: Redirect URL بالا. مرورگر شما هنگام اتصال باید به لینکدین دسترسی داشته باشد (VPN).</p>
	</div>

	<div class="bdb-panel">
		<h2><?php echo bdb_icon( 'globe', 18 ); // phpcs:ignore ?>مسیر شبکه تا لینکدین <small>مسیر فعلی: <?php echo esc_html( bdb_li_route_label() ); ?></small></h2>
		<?php if ( bdb_proxy_defined() ) : ?>
			<p class="bdb-small">پروکسی وردپرس شناسایی شد: <code dir="ltr"><?php echo esc_html( bdb_proxy_label() ); ?></code>. در حالت «هوشمند» فقط لینکدین و APIهای خارجی هوش مصنوعی از پروکسی می‌روند؛ GapGPT و بقیه مستقیم.</p>
			<div><p class="bdb-label">حالت پروکسی</p><?php bdb_segment( 'proxy_mode', array( 'smart' => 'هوشمند (پیشنهادی)', 'all' => 'همه از پروکسی', 'none' => 'همه مستقیم' ), $s['proxy_mode'] ); ?></div>
		<?php else : ?>
			<div class="bdb-note is-warn"><?php echo bdb_icon( 'alert', 16 ); // phpcs:ignore ?><span>پروکسی وردپرس (WP_PROXY_HOST در wp-config.php) تعریف نشده. چون سرور در ایران است، یکی از دو راه لازم است: پروکسی روی سرور، یا رله کلادفلر پایین (راهنما را ببینید).</span></div>
		<?php endif; ?>
		<div class="bdb-grid-2" style="margin-top:12px">
			<label>آدرس رله (اختیاری) <small>Cloudflare Worker روی دامنه خودتان؛ فایل relay/cloudflare-worker.js</small><input type="url" name="relay_url" dir="ltr" value="<?php echo esc_attr( $s['relay_url'] ); ?>" placeholder="https://li-relay.example.com"></label>
			<label>کلید رله<input type="password" name="relay_secret" dir="ltr" value="<?php echo esc_attr( $mask( 'relay_secret' ) ); ?>" autocomplete="off"><small>همان مقدار RELAY_SECRET در Worker</small></label>
		</div>
	</div>

	<div class="bdb-panel">
		<div class="bdb-panel-head">
			<h2><?php echo bdb_icon( 'bot', 18 ); // phpcs:ignore ?>هوش مصنوعی</h2>
			<button type="button" class="bdb-btn is-small" data-bdb-test="ai"><?php echo bdb_icon( 'bolt', 14 ); // phpcs:ignore ?>تست</button>
		</div>
		<div class="bdb-status-row">
			<?php bdb_conn( bdb_ai_label() ?: 'وصل نیست', bdb_ai_via_sokan() ? 'sokan' : bdb_ai_connected() ); ?>
			<?php if ( ! bdb_ai_via_sokan() ) : ?><span class="bdb-muted bdb-small">مصرف این ماه: <?php echo esc_html( bdb_num( $usage['cost'], 2 ) ); ?> از <?php echo esc_html( bdb_num( $usage['budget'], 2 ) ); ?> دلار · <?php echo esc_html( bdb_num( (int) ( $usage['requests'] ?? 0 ) ) ); ?> درخواست</span><?php else : ?><span class="bdb-muted bdb-small">بودجه و مصرف در تنظیمات سکان مدیریت می‌شود.</span><?php endif; ?>
		</div>
		<div class="bdb-test-out" data-bdb-test-out="ai" hidden></div>
		<div><p class="bdb-label">منبع</p><?php bdb_segment( 'ai_source', array( 'sokan' => 'مدل سکان (پیشنهادی)', 'own' => 'کلید اختصاصی بادبان' ), $s['ai_source'], true ); ?></div>
		<div data-bdb-when="ai_source-own">
			<div class="bdb-grid-3">
				<label>سرویس<select name="ai_provider"><?php bdb_options( array( 'gateway' => 'GapGPT / سازگار با OpenAI', 'anthropic' => 'Anthropic مستقیم' ), $s['ai_provider'] ); ?></select></label>
				<label>آدرس API<input type="url" name="ai_base_url" dir="ltr" value="<?php echo esc_attr( $s['ai_base_url'] ); ?>"></label>
				<label>کلید API<input type="password" name="ai_gateway_key" dir="ltr" value="<?php echo esc_attr( $mask( 'ai_gateway_key' ) ); ?>" autocomplete="off"></label>
				<label>کلید Anthropic<input type="password" name="anthropic_key" dir="ltr" value="<?php echo esc_attr( $mask( 'anthropic_key' ) ); ?>" autocomplete="off"></label>
				<label>مدل نوشتن پست<input type="text" name="ai_model" dir="ltr" list="bdb-models" value="<?php echo esc_attr( $s['ai_model'] ); ?>"></label>
				<label>مدل سبک (ایده، ویرایش سریع)<input type="text" name="ai_model_light" dir="ltr" list="bdb-models" value="<?php echo esc_attr( $s['ai_model_light'] ); ?>"></label>
				<label>سقف بودجه ماهانه (دلار)<input type="number" step="0.5" min="0" name="ai_budget" value="<?php echo esc_attr( $s['ai_budget'] ); ?>"></label>
			</div>
			<datalist id="bdb-models"><?php foreach ( bdb_ai_models() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option><?php endforeach; ?></datalist>
		</div>
		<p class="bdb-muted bdb-small">پیشنهاد: نوشتن پست با Claude Sonnet 5.5 (کیفیت متن انگلیسی و فارسی)، ایده و ویرایش سریع با Claude Haiku 5.5. هزینه هر پست تقریباً یک تا دو سنت است.</p>
	</div>

	<div class="bdb-panel">
		<h2><?php echo bdb_icon( 'send', 18 ); // phpcs:ignore ?>انتشار</h2>
		<label class="bdb-check"><input type="checkbox" name="auto_publish" value="1" <?php checked( (int) $s['auto_publish'] ); ?>> انتشار خودکار پست‌های زمان‌بندی‌شده (هر ۵ دقیقه بررسی می‌شود)</label>
		<label class="bdb-check"><input type="checkbox" name="require_approval" value="1" <?php checked( (int) $s['require_approval'] ); ?>> پست‌های نوشته‌شده قبل از زمان‌بندی منتظر تأیید من بمانند (پیشنهادی)</label>
		<label class="bdb-check"><input type="checkbox" name="first_comment" value="1" <?php checked( (int) $s['first_comment'] ); ?>> کامنت اول خودکار بعد از انتشار</label>
		<div class="bdb-grid-2">
			<label>منطقه زمانی مخاطب انگلیسی<select name="audience_tz"><?php bdb_options( bdb_timezones(), $s['audience_tz'] ); ?></select></label>
			<label>ایمیل اعلان‌ها <small>انتشار ناموفق، انقضای توکن</small><input type="email" name="notify_email" dir="ltr" value="<?php echo esc_attr( $s['notify_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"></label>
		</div>
		<p class="bdb-small">آخرین اجرای زمان‌بند: <b><?php echo $cron ? esc_html( bdb_when( gmdate( 'Y-m-d H:i:s', $cron ), 'fa' ) ) : 'هنوز اجرا نشده'; ?></b>. برای انتشار دقیق سر وقت، کران سرور:</p>
		<code class="bdb-code" data-bdb-copy dir="ltr">*/5 * * * * wget -q -O - <?php echo esc_html( site_url( 'wp-cron.php?doing_wp_cron' ) ); ?> >/dev/null 2>&1</code>
	</div>

	<div class="bdb-sticky-foot"><button class="bdb-btn is-primary" type="submit"><?php echo bdb_icon( 'check', 16 ); // phpcs:ignore ?>ذخیره تنظیمات</button></div>
</form>
<form method="post" id="bdb-disconnect"><?php bdb_form_fields( 'li_disconnect' ); ?></form>

<?php if ( $log ) : ?>
	<details class="bdb-panel">
		<summary><?php echo bdb_icon( 'log', 18 ); // phpcs:ignore ?>گزارش رویدادهای انتشار</summary>
		<ul class="bdb-eventlog">
			<?php foreach ( array_slice( $log, 0, 60 ) as $e ) : ?>
				<li class="is-<?php echo esc_attr( $e['l'] ); ?>"><b><?php echo esc_html( bdb_when( $e['t'], 'fa' ) ); ?></b><?php echo esc_html( $e['m'] ); ?></li>
			<?php endforeach; ?>
		</ul>
	</details>
<?php endif; ?>
