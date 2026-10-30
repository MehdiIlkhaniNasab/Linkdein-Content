<?php
/**
 * App document: sidebar + main area.
 *
 * @package Badban
 * @var string $view
 * @var string $title
 * @var string $nav
 * @var array  $vars
 */

defined( 'ABSPATH' ) || exit;

$user    = wp_get_current_user();
$counts  = bdb_post_counts();
$badges  = array( 'queue' => (int) ( $counts['ready'] ?? 0 ) + (int) ( $counts['failed'] ?? 0 ), 'ideas' => array_sum( bdb_idea_counts() ) );
$li_ok   = bdb_li_connected();
$li_left = bdb_li_days_left();
$slides  = in_array( $view, array( 'studio', 'calendar', 'dashboard', 'queue', 'brand' ), true );
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $title . ' · بادبان' ); ?></title>
	<?php bdb_app_assets( $slides ); ?>
</head>
<body class="bdb-app view-<?php echo esc_attr( $view ); ?>">
<div class="bdb-shell">
	<aside class="bdb-side" id="bdb-side">
		<a class="bdb-brand" href="<?php echo esc_url( bdb_url() ); ?>">
			<span class="bdb-brand-mark"><?php echo bdb_icon( 'sail', 24 ); // phpcs:ignore ?></span>
			<span><b>بادبان</b><small>برند شخصی در لینکدین · رونق</small></span>
		</a>
		<a class="bdb-btn is-primary is-block bdb-side-cta" href="<?php echo esc_url( bdb_url( 'studio' ) ); ?>"><?php echo bdb_icon( 'plus', 16 ); // phpcs:ignore ?>پست تازه</a>
		<nav class="bdb-nav">
			<?php foreach ( bdb_nav_items() as $key => $item ) : ?>
				<a class="<?php echo $key === $nav ? 'is-active' : ''; ?>" href="<?php echo esc_url( bdb_url( $item[2] ) ); ?>">
					<?php echo bdb_icon( $item[1], 18 ); // phpcs:ignore ?><span><?php echo esc_html( $item[0] ); ?></span>
					<?php if ( ! empty( $badges[ $key ] ) ) : ?><em><?php echo esc_html( bdb_num( $badges[ $key ] ) ); ?></em><?php endif; ?>
				</a>
			<?php endforeach; ?>
			<p class="bdb-nav-h">ابزار</p>
			<a class="<?php echo 'report' === $nav ? 'is-active' : ''; ?>" href="<?php echo esc_url( bdb_url( 'report' ) ); ?>" target="_blank"><?php echo bdb_icon( 'doc', 18 ); // phpcs:ignore ?><span>گزارش ماهانه (PDF)</span></a>
			<a class="<?php echo 'settings' === $nav ? 'is-active' : ''; ?>" href="<?php echo esc_url( bdb_url( 'settings' ) ); ?>"><?php echo bdb_icon( 'settings', 18 ); // phpcs:ignore ?><span>تنظیمات و اتصال‌ها</span></a>
			<a class="<?php echo 'guide' === $nav ? 'is-active' : ''; ?>" href="<?php echo esc_url( bdb_url( 'guide' ) ); ?>"><?php echo bdb_icon( 'help', 18 ); // phpcs:ignore ?><span>راهنما</span></a>
			<?php if ( function_exists( 'sap_url' ) || function_exists( 'qtb_url' ) || function_exists( 'sr_app_home' ) ) : ?>
				<p class="bdb-nav-h">خانواده رونق</p>
				<?php if ( function_exists( 'qtb_url' ) ) : ?><a href="<?php echo esc_url( qtb_url() ); ?>"><?php echo bdb_icon( 'compass', 18 ); // phpcs:ignore ?><span>قطب‌نما</span></a><?php endif; ?>
				<?php if ( function_exists( 'sap_url' ) ) : ?><a href="<?php echo esc_url( sap_url() ); ?>"><?php echo bdb_icon( 'target', 18 ); // phpcs:ignore ?><span>سُکان</span></a><?php endif; ?>
				<?php if ( function_exists( 'sr_app_home' ) ) : ?><a href="<?php echo esc_url( sr_app_home() ); ?>"><?php echo bdb_icon( 'idea', 18 ); // phpcs:ignore ?><span>فانوس</span></a><?php endif; ?>
			<?php endif; ?>
		</nav>
		<div class="bdb-li-card <?php echo $li_ok ? ( null !== $li_left && $li_left <= 10 ? 'is-warn' : 'is-on' ) : 'is-off'; ?>">
			<?php if ( $li_ok ) : ?>
				<?php if ( bdb_setting( 'li_picture' ) ) : ?><img src="<?php echo esc_url( bdb_setting( 'li_picture' ) ); ?>" alt="" referrerpolicy="no-referrer"><?php else : ?><i><?php echo bdb_icon( 'linkedin', 18 ); // phpcs:ignore ?></i><?php endif; ?>
				<span><b><?php echo esc_html( bdb_setting( 'li_name' ) ); ?></b><small><?php echo esc_html( null !== $li_left && $li_left <= 10 ? sprintf( 'توکن: %s روز مانده', bdb_num( max( 0, $li_left ) ) ) : 'لینکدین متصل است' ); ?></small></span>
			<?php else : ?>
				<i><?php echo bdb_icon( 'linkedin', 18 ); // phpcs:ignore ?></i>
				<span><b>لینکدین وصل نیست</b><small><a href="<?php echo esc_url( bdb_url( 'settings' ) ); ?>">اتصال در تنظیمات</a></small></span>
			<?php endif; ?>
		</div>
		<div class="bdb-side-foot">
			<span class="bdb-user"><i><?php echo esc_html( mb_substr( $user->display_name, 0, 1 ) ); ?></i><?php echo esc_html( $user->display_name ); ?></span>
			<span class="bdb-side-links">
				<a href="<?php echo esc_url( admin_url() ); ?>">وردپرس</a>
				<a href="<?php echo esc_url( wp_logout_url( bdb_url() ) ); ?>">خروج</a>
			</span>
		</div>
	</aside>
	<div class="bdb-backdrop" data-bdb-close-nav></div>
	<main class="bdb-main">
		<div class="bdb-topbar">
			<button type="button" class="bdb-menu-btn" data-bdb-open-nav aria-label="منو"><?php echo bdb_icon( 'list', 22 ); // phpcs:ignore ?></button>
			<a href="<?php echo esc_url( bdb_url() ); ?>">بادبان</a>
		</div>
		<div class="bdb-wrap">
			<?php
			bdb_flash();
			bdb_view( $view, $vars );
			?>
		</div>
	</main>
</div>
<div class="bdb-toast" id="bdb-toast" role="status"></div>
</body>
</html>
