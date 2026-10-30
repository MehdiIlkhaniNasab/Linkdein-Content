<?php
/**
 * Idea bank.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

$src     = bdb_sources_status();
$status  = in_array( $_GET['status'] ?? 'new', array( 'new', 'used', 'dismissed' ), true ) ? ( $_GET['status'] ?? 'new' ) : 'new'; // phpcs:ignore
$source  = isset( bdb_idea_sources()[ $_GET['source'] ?? '' ] ) ? $_GET['source'] : ''; // phpcs:ignore
$pillar  = isset( bdb_pillars()[ $_GET['pillar'] ?? '' ] ) ? $_GET['pillar'] : ''; // phpcs:ignore
$ideas   = bdb_ideas( array( 'status' => $status, 'source' => $source, 'pillar' => $pillar ) );
$counts  = bdb_idea_counts();
$last    = get_option( 'bdb_last_harvest', array() );
$labels  = array( 'sokan' => 'سکان', 'ghotbnama' => 'قطب‌نما', 'fanus' => 'فانوس' );
?>
<div class="bdb-head">
	<div>
		<p class="bdb-eyebrow"><?php echo bdb_icon( 'idea', 14 ); // phpcs:ignore ?>ایده از دل کار واقعی</p>
		<h1>بانک ایده</h1>
		<p class="bdb-muted">نتایج سنجیده‌شده سکان، کیورد ریسرچ‌های قطب‌نما و گزارش‌های فانوس به ایده پست تبدیل می‌شوند؛ همه ناشناس. هر روز خودکار به‌روز می‌شود.</p>
	</div>
	<div class="bdb-actions">
		<button type="button" class="bdb-btn is-primary" data-bdb-harvest><?php echo bdb_icon( 'refresh', 16 ); // phpcs:ignore ?>جمع‌آوری ایده‌های تازه</button>
	</div>
</div>

<div class="bdb-status-row">
	<?php foreach ( $labels as $k => $label ) : ?>
		<?php bdb_conn( $label . ( $src[ $k ] ? ' (متصل)' : ' (روی این وردپرس نصب نیست)' ), $src[ $k ] ); ?>
	<?php endforeach; ?>
	<?php if ( ! empty( $last['at'] ) ) : ?><span class="bdb-muted bdb-small">آخرین جمع‌آوری: <?php echo esc_html( bdb_when( $last['at'], 'fa' ) ); ?></span><?php endif; ?>
</div>

<div class="bdb-grid-2 is-top">
	<form class="bdb-panel" data-bdb-ideas-ai>
		<h2><?php echo bdb_icon( 'spark', 18 ); // phpcs:ignore ?>طوفان فکری با هوش مصنوعی</h2>
		<div class="bdb-inline">
			<select name="pillar"><option value="">همه ستون‌ها</option><?php bdb_options( bdb_pillars(), '' ); ?></select>
			<input type="number" name="count" min="3" max="15" value="8" style="max-width:90px">
			<button class="bdb-btn" type="submit">ایده بده</button>
		</div>
		<p class="bdb-muted bdb-small">با استراتژی برند، ایده‌های موجود (بدون تکرار) و آنچه تا حالا بهتر جواب داده.</p>
	</form>
	<form class="bdb-panel" data-bdb-idea-add>
		<h2><?php echo bdb_icon( 'plus', 18 ); // phpcs:ignore ?>ایده خودم</h2>
		<input type="text" name="title" dir="auto" placeholder="عنوان یا موضوع ایده" required>
		<div class="bdb-inline" style="margin-top:8px">
			<input type="text" name="angle" dir="auto" placeholder="زاویه / حرف اصلی (اختیاری)">
			<select name="pillar"><option value="">ستون</option><?php bdb_options( bdb_pillars(), '' ); ?></select>
			<select name="framework"><?php bdb_options( bdb_frameworks(), 'lesson' ); ?></select>
			<button class="bdb-btn" type="submit">افزودن</button>
		</div>
	</form>
</div>

<div class="bdb-tabs">
	<?php foreach ( array( 'new' => 'تازه', 'used' => 'استفاده‌شده', 'dismissed' => 'کنار گذاشته' ) as $k => $label ) : ?>
		<a class="<?php echo $k === $status ? 'is-active' : ''; ?>" href="<?php echo esc_url( bdb_url( 'ideas', array_filter( array( 'status' => $k, 'source' => $source, 'pillar' => $pillar ) ) ) ); ?>"><?php echo esc_html( $label ); ?><?php if ( 'new' === $k ) : ?> <em><?php echo esc_html( bdb_num( array_sum( $counts ) ) ); ?></em><?php endif; ?></a>
	<?php endforeach; ?>
</div>
<div class="bdb-pills">
	<a class="<?php echo '' === $source ? 'is-active' : ''; ?>" href="<?php echo esc_url( bdb_url( 'ideas', array_filter( array( 'status' => $status, 'pillar' => $pillar ) ) ) ); ?>">همه منابع</a>
	<?php foreach ( bdb_idea_sources() as $k => $s ) : ?>
		<a class="<?php echo $k === $source ? 'is-active' : ''; ?>" href="<?php echo esc_url( bdb_url( 'ideas', array_filter( array( 'status' => $status, 'source' => $k, 'pillar' => $pillar ) ) ) ); ?>"><?php echo bdb_icon( $s[1], 13 ); // phpcs:ignore ?><?php echo esc_html( $s[0] ); ?><?php if ( ! empty( $counts[ $k ] ) && 'new' === $status ) : ?><small><?php echo esc_html( bdb_num( $counts[ $k ] ) ); ?></small><?php endif; ?></a>
	<?php endforeach; ?>
	<span class="bdb-sep"></span>
	<?php foreach ( bdb_pillars() as $k => $pl ) : ?>
		<a class="<?php echo $k === $pillar ? 'is-active' : ''; ?>" href="<?php echo esc_url( bdb_url( 'ideas', array_filter( array( 'status' => $status, 'source' => $source, 'pillar' => $k === $pillar ? '' : $k ) ) ) ); ?>"><?php echo esc_html( $pl[0] ); ?></a>
	<?php endforeach; ?>
</div>

<?php if ( ! $ideas ) : ?>
	<div class="bdb-empty">
		<?php echo bdb_icon( 'idea', 40 ); // phpcs:ignore ?>
		<h2>ایده‌ای این‌جا نیست</h2>
		<p>«جمع‌آوری ایده‌های تازه» ایده‌ها را از سکان، قطب‌نما، فانوس و کتابخانه بادبان می‌آورد.</p>
	</div>
<?php else : ?>
	<div class="bdb-cards">
		<?php foreach ( $ideas as $i ) : ?>
			<?php $fw = (string) ( $i['payload']['framework'] ?? '' ); ?>
			<div class="bdb-card is-idea" data-bdb-idea="<?php echo esc_attr( $i['id'] ); ?>">
				<div class="bdb-card-top">
					<span class="bdb-score <?php echo $i['score'] >= 75 ? 'is-hi' : ''; ?>" title="امتیاز"><?php echo esc_html( bdb_num( $i['score'] ) ); ?></span>
					<span class="bdb-chip is-source"><?php echo bdb_icon( bdb_idea_sources()[ $i['source'] ][1] ?? 'idea', 12 ); // phpcs:ignore ?><?php echo esc_html( bdb_idea_sources()[ $i['source'] ][0] ?? $i['source'] ); ?></span>
					<?php echo bdb_chip_pillar( $i['pillar'] ); // phpcs:ignore ?>
					<?php if ( $fw ) : ?><span class="bdb-chip"><?php echo esc_html( bdb_frameworks()[ $fw ][0] ?? $fw ); ?></span><?php endif; ?>
				</div>
				<h3 dir="auto"><?php echo esc_html( $i['title'] ); ?></h3>
				<?php if ( $i['angle'] ) : ?><p class="bdb-small bdb-muted" dir="auto"><?php echo esc_html( $i['angle'] ); ?></p><?php endif; ?>
				<?php $facts = array_diff_key( $i['payload'], array( 'framework' => 1 ) ); ?>
				<?php if ( $facts ) : ?>
					<details class="bdb-facts"><summary>داده‌های ناشناس‌شده</summary><pre dir="auto"><?php echo esc_html( wp_json_encode( $facts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ); ?></pre></details>
				<?php endif; ?>
				<div class="bdb-card-foot">
					<?php if ( 'new' === $i['status'] ) : ?>
						<button type="button" class="bdb-btn is-small is-primary" data-bdb-write-idea="<?php echo esc_attr( $i['id'] ); ?>" data-lang="en"><?php echo bdb_icon( 'pen', 14 ); // phpcs:ignore ?>English</button>
						<button type="button" class="bdb-btn is-small" data-bdb-write-idea="<?php echo esc_attr( $i['id'] ); ?>" data-lang="fa">فارسی</button>
						<a class="bdb-btn is-small is-ghost" href="<?php echo esc_url( bdb_url( 'studio', array( 'idea' => $i['id'] ) ) ); ?>" title="انتخاب قالب و CTA">تنظیمات…</a>
						<button type="button" class="bdb-icon-btn" data-bdb-idea-status="dismissed" title="کنار گذاشتن"><?php echo bdb_icon( 'x', 16 ); // phpcs:ignore ?></button>
					<?php else : ?>
						<button type="button" class="bdb-btn is-small" data-bdb-idea-status="new"><?php echo bdb_icon( 'undo', 14 ); // phpcs:ignore ?>برگرداندن</button>
						<button type="button" class="bdb-icon-btn is-del" data-bdb-idea-status="delete" title="حذف"><?php echo bdb_icon( 'trash', 16 ); // phpcs:ignore ?></button>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
