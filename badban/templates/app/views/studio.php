<?php
/**
 * Studio: edit a post with a live LinkedIn preview, the slide designer, the
 * quality check, AI tools, and schedule / publish. Driven by app.js + slides.js.
 *
 * @package Badban
 * @var array $post
 */

defined( 'ABSPATH' ) || exit;

$app    = bdb_post_for_app( $post );
$p      = $post;
$b      = bdb_brand();
$locked = in_array( $p['status'], array( 'published', 'publishing' ), true );
$pair   = $p['pair_id'] ? bdb_post( $p['pair_id'] ) : null;
$tzname = bdb_tz( $p['lang'] )->getName();
$m      = $p['metrics'];
?>
<div class="bdb-studio" data-bdb-studio="<?php echo esc_attr( wp_json_encode( $app ) ); ?>" data-locked="<?php echo $locked ? '1' : '0'; ?>">
	<div class="bdb-head">
		<div>
			<p class="bdb-eyebrow"><?php echo bdb_icon( 'pen', 14 ); // phpcs:ignore ?>استودیو محتوا · <span data-bdb-status-chip><?php echo bdb_chip_status( $p['status'] ); // phpcs:ignore ?></span></p>
			<input class="bdb-title-input" type="text" name="title" value="<?php echo esc_attr( $p['title'] ); ?>" placeholder="عنوان داخلی پست" <?php disabled( $locked ); ?>>
		</div>
		<div class="bdb-actions">
			<span class="bdb-save-state" data-bdb-save-state></span>
			<?php if ( ! $locked ) : ?>
				<button type="button" class="bdb-btn" data-bdb-save><?php echo bdb_icon( 'check', 16 ); // phpcs:ignore ?>ذخیره</button>
			<?php endif; ?>
			<div class="bdb-menu">
				<button type="button" class="bdb-btn" data-bdb-menu-toggle><?php echo bdb_icon( 'list', 16 ); // phpcs:ignore ?>بیشتر</button>
				<div class="bdb-menu-pop" hidden>
					<?php if ( in_array( $p['format'], array( 'carousel', 'image' ), true ) ) : ?>
						<button type="button" data-bdb-download="pdf"><?php echo bdb_icon( 'download', 15 ); // phpcs:ignore ?>دانلود PDF</button>
						<button type="button" data-bdb-download="pptx"><?php echo bdb_icon( 'slides', 15 ); // phpcs:ignore ?>دانلود پاورپوینت (PPTX)</button>
						<button type="button" data-bdb-download="png"><?php echo bdb_icon( 'doc', 15 ); // phpcs:ignore ?>دانلود اسلاید جاری (PNG)</button>
					<?php endif; ?>
					<button type="button" data-bdb-copy-text><?php echo bdb_icon( 'copy', 15 ); // phpcs:ignore ?>کپی متن پست</button>
					<?php if ( $pair ) : ?>
						<a href="<?php echo esc_url( bdb_url( 'studio/' . $pair['id'] ) ); ?>"><?php echo bdb_icon( 'translate', 15 ); // phpcs:ignore ?>نسخه <?php echo 'fa' === $pair['lang'] ? 'فارسی' : 'انگلیسی'; ?></a>
					<?php else : ?>
						<button type="button" data-bdb-counterpart><?php echo bdb_icon( 'translate', 15 ); // phpcs:ignore ?>ساخت نسخه <?php echo 'en' === $p['lang'] ? 'فارسی' : 'انگلیسی'; ?></button>
					<?php endif; ?>
					<button type="button" data-bdb-duplicate><?php echo bdb_icon( 'copy', 15 ); // phpcs:ignore ?>کپی به پیش‌نویس تازه</button>
					<?php if ( ! $locked ) : ?>
						<button type="button" class="is-danger" data-bdb-delete><?php echo bdb_icon( 'trash', 15 ); // phpcs:ignore ?>حذف پست</button>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<?php if ( 'failed' === $p['status'] && $p['error'] ) : ?>
		<div class="bdb-note is-err"><?php echo bdb_icon( 'alert', 16 ); // phpcs:ignore ?><span>انتشار ناموفق: <?php echo esc_html( $p['error'] ); ?></span></div>
	<?php endif; ?>
	<?php if ( ! empty( $p['meta']['first_comment_error'] ) ) : ?>
		<div class="bdb-note is-warn"><?php echo bdb_icon( 'chat', 16 ); // phpcs:ignore ?><span>پست منتشر شد اما کامنت اول ثبت نشد: <?php echo esc_html( $p['meta']['first_comment_error'] ); ?> — کامنت را دستی بگذارید.</span></div>
	<?php endif; ?>

	<div class="bdb-studio-grid">
		<div class="bdb-studio-main">
			<div class="bdb-panel">
				<div class="bdb-grid-4 is-tight">
					<label>زبان<select name="lang" <?php disabled( $locked ); ?>><?php bdb_options( array( 'en' => 'English', 'fa' => 'فارسی' ), $p['lang'] ); ?></select></label>
					<label>قالب<select name="format" <?php disabled( $locked ); ?>><?php bdb_options( bdb_formats(), $p['format'] ); ?></select></label>
					<label>ستون محتوا<select name="pillar" <?php disabled( $locked ); ?>><option value="">—</option><?php bdb_options( bdb_pillars(), $p['pillar'] ); ?></select></label>
					<label>کال تو اکشن<select name="cta_type" <?php disabled( $locked ); ?>><option value="">—</option><?php bdb_options( bdb_cta_types(), $p['cta_type'] ); ?></select></label>
				</div>
				<label class="bdb-body-label">
					<span>متن پست <small data-bdb-count></small></span>
					<textarea name="body" rows="16" dir="<?php echo 'fa' === $p['lang'] ? 'rtl' : 'ltr'; ?>" class="bdb-body" <?php disabled( $locked ); ?>><?php echo esc_textarea( $p['body'] ); ?></textarea>
				</label>
				<?php if ( ! $locked ) : ?>
					<div class="bdb-ai-tools">
						<span class="bdb-muted bdb-small"><?php echo bdb_icon( 'spark', 14 ); // phpcs:ignore ?>هوش مصنوعی:</span>
						<button type="button" class="bdb-btn is-small" data-bdb-polish="hook">قلاب قوی‌تر</button>
						<button type="button" class="bdb-btn is-small" data-bdb-polish="shorter">کوتاه‌تر</button>
						<button type="button" class="bdb-btn is-small" data-bdb-polish="story">داستانی‌تر</button>
						<button type="button" class="bdb-btn is-small" data-bdb-polish="clearer">ساده‌تر</button>
						<button type="button" class="bdb-btn is-small" data-bdb-polish="punchy">پرانرژی‌تر</button>
						<button type="button" class="bdb-btn is-small is-ghost" data-bdb-rewrite><?php echo bdb_icon( 'refresh', 14 ); // phpcs:ignore ?>بازنویسی کامل…</button>
					</div>
				<?php endif; ?>
				<div class="bdb-hooks" data-bdb-hooks></div>
				<div class="bdb-grid-2">
					<label>هشتگ‌ها<input type="text" name="hashtags" dir="auto" value="<?php echo esc_attr( $p['hashtags'] ); ?>" <?php disabled( $locked ); ?>></label>
					<label>کامنت اول <small>لینک‌ها و منابع اینجا (لینک داخل پست دیده‌شدن را کم می‌کند)</small><textarea name="first_comment" rows="2" dir="auto" <?php disabled( $locked ); ?>><?php echo esc_textarea( $p['first_comment'] ); ?></textarea></label>
				</div>
			</div>

			<div class="bdb-panel" data-bdb-format-panel="carousel image">
				<div class="bdb-panel-head">
					<h2><?php echo bdb_icon( 'slides', 18 ); // phpcs:ignore ?>اسلایدها <small data-bdb-slide-count></small></h2>
					<div class="bdb-actions">
						<label class="bdb-inline-label" data-bdb-format-panel="carousel">عنوان سند <input type="text" name="doc_title" dir="auto" value="<?php echo esc_attr( $p['meta']['doc_title'] ?? '' ); ?>" <?php disabled( $locked ); ?>></label>
						<?php if ( ! $locked ) : ?><button type="button" class="bdb-btn is-small" data-bdb-slide-add><?php echo bdb_icon( 'plus', 14 ); // phpcs:ignore ?>اسلاید</button><?php endif; ?>
					</div>
				</div>
				<div class="bdb-slides-editor" data-bdb-slides></div>
			</div>

			<div class="bdb-panel" data-bdb-format-panel="poll">
				<h2><?php echo bdb_icon( 'chart', 18 ); // phpcs:ignore ?>نظرسنجی</h2>
				<?php $poll = (array) ( $p['meta']['poll'] ?? array() ); ?>
				<label>سؤال <small>حداکثر ۱۴۰ کاراکتر</small><input type="text" name="poll_question" maxlength="140" dir="auto" value="<?php echo esc_attr( $poll['question'] ?? '' ); ?>" <?php disabled( $locked ); ?>></label>
				<div class="bdb-grid-2">
					<?php for ( $i = 0; $i < 4; $i++ ) : ?>
						<label>گزینه <?php echo esc_html( bdb_num( $i + 1 ) ); ?><input type="text" name="poll_option" maxlength="30" dir="auto" value="<?php echo esc_attr( $poll['options'][ $i ] ?? '' ); ?>" <?php disabled( $locked ); ?>></label>
					<?php endfor; ?>
				</div>
				<label>مدت<select name="poll_duration" <?php disabled( $locked ); ?>><?php bdb_options( array( 'ONE_DAY' => '۱ روز', 'THREE_DAYS' => '۳ روز', 'SEVEN_DAYS' => '۱ هفته', 'FOURTEEN_DAYS' => '۲ هفته' ), $poll['duration'] ?? 'THREE_DAYS' ); ?></select></label>
			</div>

			<?php if ( 'published' === $p['status'] ) : ?>
				<form class="bdb-panel" data-bdb-metrics="<?php echo esc_attr( $p['id'] ); ?>">
					<div class="bdb-panel-head">
						<h2><?php echo bdb_icon( 'chart', 18 ); // phpcs:ignore ?>آمار این پست</h2>
						<?php if ( bdb_post_url( $p ) ) : ?><a class="bdb-btn is-small" href="<?php echo esc_url( bdb_post_url( $p ) ); ?>" target="_blank" rel="noopener"><?php echo bdb_icon( 'linkedin', 14 ); // phpcs:ignore ?>مشاهده در لینکدین</a><?php endif; ?>
					</div>
					<p class="bdb-muted bdb-small">از صفحه آمار همین پست در لینکدین وارد کنید (یا با فایل Export آنالیتیکس خودکار پر می‌شود). نرخ تعامل و یادگیری نویسنده از همین اعداد ساخته می‌شود.</p>
					<div class="bdb-grid-4 is-tight">
						<?php foreach ( array( 'impressions' => 'نمایش', 'reactions' => 'واکنش', 'comments' => 'کامنت', 'reposts' => 'بازنشر', 'saves' => 'ذخیره', 'clicks' => 'کلیک', 'followers' => 'دنبال‌کننده جدید', 'leads' => 'لید / گفتگوی کاری' ) as $k => $label ) : ?>
							<label><?php echo esc_html( $label ); ?><input type="number" min="0" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $m[ $k ] ?? '' ); ?>"></label>
						<?php endforeach; ?>
					</div>
					<button class="bdb-btn is-small is-primary" type="submit">ذخیره آمار</button>
				</form>
			<?php endif; ?>
		</div>

		<aside class="bdb-studio-side">
			<?php if ( ! $locked ) : ?>
				<div class="bdb-panel is-publish">
					<h2><?php echo bdb_icon( 'send', 18 ); // phpcs:ignore ?>زمان‌بندی و انتشار</h2>
					<label>زمان انتشار <small>به وقت <?php echo esc_html( $tzname ); ?></small>
						<span class="bdb-inline"><input type="datetime-local" name="when" value="<?php echo esc_attr( $app['post']['when_local'] ); ?>" dir="ltr"><button type="button" class="bdb-btn is-small" data-bdb-next-slot title="اولین جایگاه خالی تقویم">جایگاه بعدی</button></span>
					</label>
					<p class="bdb-muted bdb-small" data-bdb-when-label><?php echo esc_html( $app['post']['when_label'] ); ?></p>
					<div class="bdb-publish-btns">
						<button type="button" class="bdb-btn is-gem is-block" data-bdb-render data-bdb-format-panel="carousel image"><?php echo bdb_icon( 'doc', 16 ); // phpcs:ignore ?>ساخت فایل <?php echo 'image' === $p['format'] ? 'تصویر' : 'PDF'; ?></button>
						<button type="button" class="bdb-btn is-primary is-block" data-bdb-approve-this><?php echo bdb_icon( 'check', 16 ); // phpcs:ignore ?>تأیید و زمان‌بندی</button>
						<button type="button" class="bdb-btn is-block" data-bdb-publish-now><?php echo bdb_icon( 'linkedin', 16 ); // phpcs:ignore ?>همین حالا منتشر کن</button>
						<?php if ( 'scheduled' === $p['status'] ) : ?>
							<button type="button" class="bdb-btn is-ghost is-block" data-bdb-unschedule="<?php echo esc_attr( $p['id'] ); ?>"><?php echo bdb_icon( 'undo', 16 ); // phpcs:ignore ?>لغو زمان‌بندی</button>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<div class="bdb-panel is-preview">
				<div class="bdb-li-preview" data-bdb-preview>
					<div class="bdb-li-head">
						<?php if ( bdb_setting( 'li_picture' ) || $b['photo_url'] ) : ?>
							<img src="<?php echo esc_url( bdb_setting( 'li_picture' ) ?: $b['photo_url'] ); ?>" alt="" referrerpolicy="no-referrer">
						<?php else : ?>
							<i><?php echo esc_html( mb_substr( $b['name'], 0, 1 ) ); ?></i>
						<?php endif; ?>
						<span><b><?php echo esc_html( bdb_setting( 'li_name' ) ?: $b['name'] ); ?></b><small><?php echo esc_html( 'fa' === $p['lang'] ? $b['headline_fa'] : $b['headline'] ); ?></small><small>now · 🌐</small></span>
					</div>
					<div class="bdb-li-text" data-bdb-preview-text dir="auto"></div>
					<div class="bdb-li-media" data-bdb-preview-media></div>
					<div class="bdb-li-foot"><span>👍 ❤️ 💡</span><span>Like · Comment · Repost · Send</span></div>
				</div>
			</div>

			<div class="bdb-panel">
				<div class="bdb-quality" data-bdb-quality></div>
			</div>

			<?php if ( ! empty( $p['meta']['notes'] ) || ! empty( $p['meta']['source'] ) ) : ?>
				<div class="bdb-panel is-soft">
					<h2><?php echo bdb_icon( 'idea', 18 ); // phpcs:ignore ?>یادداشت نویسنده</h2>
					<?php if ( ! empty( $p['meta']['source'] ) ) : ?>
						<p class="bdb-small"><b>منبع ایده:</b> <?php echo esc_html( ( bdb_idea_sources()[ $p['meta']['source']['source'] ][0] ?? '' ) . ' — ' . ( $p['meta']['source']['idea'] ?? '' ) ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $p['meta']['notes'] ) ) : ?><p class="bdb-small"><?php echo esc_html( $p['meta']['notes'] ); ?></p><?php endif; ?>
				</div>
			<?php endif; ?>
		</aside>
	</div>
</div>
