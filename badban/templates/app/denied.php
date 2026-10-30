<?php
/**
 * No access.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>دسترسی ندارید · بادبان</title>
	<?php bdb_app_assets(); ?>
</head>
<body class="bdb-app">
<div class="bdb-login">
	<div class="bdb-login-box">
		<span class="bdb-brand-mark is-lg"><?php echo bdb_icon( 'shield', 30 ); // phpcs:ignore ?></span>
		<h1>دسترسی ندارید</h1>
		<p class="bdb-muted">این ابزار فقط برای مدیر برند فعال است.</p>
		<p style="margin-top:16px"><a class="bdb-btn" href="<?php echo esc_url( wp_logout_url( bdb_url() ) ); ?>">ورود با حساب دیگر</a></p>
	</div>
</div>
</body>
</html>
