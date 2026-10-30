<?php
/**
 * All posts, by status.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

$tabs   = array(
	'all'       => 'همه',
	'ready'     => 'آماده تأیید',
	'scheduled' => 'زمان‌بندی‌شده',
	'draft'     => 'پیش‌نویس',
	'published' => 'منتشرشده',
	'failed'    => 'ناموفق',
);
$counts = bdb_post_counts();
$tab    = sanitize_key( $_GET['tab'] ?? 'all' ); // phpcs:ignore
$tab    = isset( $tabs[ $tab ] ) ? $tab : 'all';
$f      = array(
	'lang'   => in_array( $_GET['lang'] ?? '', array( 'en', 'fa' ), true ) ? $_GET['lang'] : '', // phpcs:ignore
	'format' => isset( bdb_formats()[ $_GET['format'] ?? '' ] ) ? $_GET['format'] : '', // phpcs:ignore
	'pillar' => isset( bdb_pillars()[ $_GET['pillar'] ?? '' ] ) ? $_GET['pillar'] : '', // phpcs:ignore
	'search' => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ), // phpcs:ignore
);
$posts = bdb_posts( array_merge( $f, array(
	'status' => 'all' === $tab ? array( 'draft', 'ready', 'scheduled', 'publishing', 'published', 'failed' ) : ( 'scheduled' === $tab ? array( 'scheduled', 'publishing' ) : $tab ),
	'order'  => in_array( $tab, array( 'scheduled', 'ready' ), true ) ? 'schedule' : 'time',
	'limit'  => 300,
) ) );
?>
<div class="bdb-head">
	<div>
		<p class="bdb-eyebrow"><?php echo bdb_icon( 'list', 14 ); // phpcs:ignore ?>کتابخانه محتوا</p>
		<h1>پست‌ها</h1>
		<p class="bdb-muted">همه پست‌های نوشته‌شده، زمان‌بندی‌شده و منتشرشده با آمار هرکدام.</p>
	</div>
	<div class="bdb-actions">
		<?php if ( ! empty( $counts['ready'] ) ) : ?>
			<button type="button" class="bdb-btn" data-bdb-approve-all><?php echo bdb_icon( 'check', 16 ); // phpcs:ignore ?>تأیید همه آماده‌ها (<?php echo esc_html( bdb_num( $counts['ready'] ) ); ?>)</button>
		<?php endif; ?>
		<a class="bdb-btn is-primary" href="<?php echo esc_url( bdb_url( 'studio' ) ); ?>"><?php echo bdb_icon( 'plus', 16 ); // phpcs:ignore ?>پست تازه</a>
	</div>
</div>

<div class="bdb-tabs">
	<?php foreach ( $tabs as $k => $label ) : ?>
		<?php $n = 'all' === $k ? array_sum( array_diff_key( $counts, array( 'archived' => 1 ) ) ) : (int) ( $counts[ $k ] ?? 0 ) + ( 'scheduled' === $k ? (int) ( $counts['publishing'] ?? 0 ) : 0 ); ?>
		<a class="<?php echo $k === $tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( bdb_url( 'queue', array_filter( array_merge( $f, array( 'tab' => $k, 's' => $f['search'] ) ) ) ) ); ?>"><?php echo esc_html( $label ); ?> <em><?php echo esc_html( bdb_num( $n ) ); ?></em></a>
	<?php endforeach; ?>
</div>

<form class="bdb-filters" method="get" action="<?php echo esc_url( bdb_url( 'queue' ) ); ?>">
	<?php if ( ! get_option( 'permalink_structure' ) ) : ?><input type="hidden" name="bdb_app" value="1"><input type="hidden" name="bdb_route" value="queue"><?php endif; ?>
	<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
	<input type="search" name="s" value="<?php echo esc_attr( $f['search'] ); ?>" placeholder="جستجو در عنوان و متن">
	<select name="lang"><option value="">همه زبان‌ها</option><?php bdb_options( array( 'en' => 'English', 'fa' => 'فارسی' ), $f['lang'] ); ?></select>
	<select name="format"><option value="">همه قالب‌ها</option><?php bdb_options( bdb_formats(), $f['format'] ); ?></select>
	<select name="pillar"><option value="">همه ستون‌ها</option><?php bdb_options( bdb_pillars(), $f['pillar'] ); ?></select>
	<button class="bdb-btn" type="submit"><?php echo bdb_icon( 'filter', 16 ); // phpcs:ignore ?>فیلتر</button>
</form>

<?php if ( ! $posts ) : ?>
	<div class="bdb-empty">
		<?php echo bdb_icon( 'pen', 40 ); // phpcs:ignore ?>
		<h2>پستی در این بخش نیست</h2>
		<p>از استودیو یک پست تازه بنویسید یا در داشبورد «برنامه‌ریزی و نوشتن ۲ هفته آینده» را بزنید.</p>
		<a class="bdb-btn is-primary" href="<?php echo esc_url( bdb_url( 'studio' ) ); ?>">پست تازه</a>
	</div>
<?php else : ?>
	<div class="bdb-panel">
		<div class="bdb-postlist">
			<?php foreach ( $posts as $p ) : ?>
				<?php bdb_view( 'part-post-row', array( 'p' => $p ) ); ?>
			<?php endforeach; ?>
		</div>
	</div>
<?php endif; ?>
