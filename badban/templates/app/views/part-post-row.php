<?php
/**
 * One post in a list (dashboard, queue, calendar).
 *
 * @package Badban
 * @var array $p
 */

defined( 'ABSPATH' ) || exit;

$er = bdb_post_er( $p );
?>
<div class="bdb-postrow is-<?php echo esc_attr( $p['status'] ); ?>" data-bdb-post-row="<?php echo esc_attr( $p['id'] ); ?>">
	<div class="bdb-postrow-when">
		<?php $t = $p['published_at'] ?: $p['scheduled_at']; ?>
		<?php if ( $t ) : ?>
			<?php $d = bdb_local( $t, 'fa' ); ?>
			<b><?php echo esc_html( bdb_jweekday( $d->format( 'Y-m-d' ) ) ); ?></b>
			<span><?php echo esc_html( bdb_jdate( $d->format( 'Y-m-d' ), 'day' ) ); ?></span>
			<small><?php echo esc_html( bdb_fa( $d->format( 'H:i' ) ) ); ?> تهران</small>
		<?php else : ?>
			<span class="bdb-muted">بدون زمان</span>
		<?php endif; ?>
	</div>
	<a class="bdb-postrow-main" href="<?php echo esc_url( bdb_url( 'studio/' . $p['id'] ) ); ?>">
		<span class="bdb-postrow-chips"><?php echo bdb_chip_status( $p['status'] ) . bdb_chip_lang( $p['lang'] ) . bdb_chip_format( $p['format'] ) . bdb_chip_pillar( $p['pillar'] ); // phpcs:ignore ?></span>
		<b><?php echo esc_html( $p['title'] ?: 'بدون عنوان' ); ?></b>
		<small dir="auto"><?php echo esc_html( bdb_hook_preview( $p['body'] ) ); ?></small>
		<?php if ( 'failed' === $p['status'] && $p['error'] ) : ?><small class="bdb-err-text"><?php echo esc_html( mb_substr( $p['error'], 0, 200 ) ); ?></small><?php endif; ?>
	</a>
	<div class="bdb-postrow-side">
		<?php if ( 'published' === $p['status'] ) : ?>
			<?php if ( ! empty( $p['metrics']['impressions'] ) ) : ?>
				<span class="bdb-mini-metric"><?php echo bdb_icon( 'eye', 14 ); // phpcs:ignore ?><?php echo esc_html( bdb_compact( $p['metrics']['impressions'] ) ); ?></span>
				<span class="bdb-mini-metric"><?php echo bdb_icon( 'heart', 14 ); // phpcs:ignore ?><?php echo esc_html( null !== $er ? bdb_pct( $er, 1 ) : '—' ); ?></span>
			<?php endif; ?>
			<?php if ( bdb_post_url( $p ) ) : ?><a class="bdb-icon-btn" href="<?php echo esc_url( bdb_post_url( $p ) ); ?>" target="_blank" rel="noopener" title="مشاهده در لینکدین"><?php echo bdb_icon( 'linkedin', 18 ); // phpcs:ignore ?></a><?php endif; ?>
		<?php elseif ( 'ready' === $p['status'] ) : ?>
			<button type="button" class="bdb-btn is-small is-primary" data-bdb-approve="<?php echo esc_attr( $p['id'] ); ?>"><?php echo bdb_icon( 'check', 14 ); // phpcs:ignore ?>تأیید</button>
		<?php elseif ( 'scheduled' === $p['status'] ) : ?>
			<button type="button" class="bdb-btn is-small" data-bdb-unschedule="<?php echo esc_attr( $p['id'] ); ?>" title="برگشت به صف تأیید"><?php echo bdb_icon( 'undo', 14 ); // phpcs:ignore ?></button>
		<?php endif; ?>
	</div>
</div>
