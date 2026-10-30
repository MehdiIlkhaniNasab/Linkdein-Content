<?php
/**
 * Login screen of the app.
 *
 * @package Badban
 * @var string $redirect
 */

defined( 'ABSPATH' ) || exit;
$failed = isset( $_GET['bdb_login'] ); // phpcs:ignore
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title>ورود · بادبان</title>
	<?php bdb_app_assets(); ?>
</head>
<body class="bdb-app">
<div class="bdb-login">
	<div class="bdb-login-box">
		<span class="bdb-brand-mark is-lg"><?php echo bdb_icon( 'sail', 32 ); // phpcs:ignore ?></span>
		<h1>بادبان</h1>
		<p class="bdb-muted">برند شخصی شما در لینکدین · رونق</p>
		<?php if ( $failed ) : ?>
			<div class="bdb-flash is-err" style="margin-top:14px">نام کاربری یا رمز عبور اشتباه است.</div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>">
			<label>نام کاربری یا ایمیل<input type="text" name="log" autocomplete="username" required dir="ltr"></label>
			<label>رمز عبور<input type="password" name="pwd" autocomplete="current-password" required dir="ltr"></label>
			<label class="bdb-check"><input type="checkbox" name="rememberme" value="forever" checked> مرا به خاطر بسپار</label>
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect ); ?>">
			<input type="hidden" name="bdb_login" value="1">
			<button class="bdb-btn is-primary is-block" type="submit">ورود</button>
		</form>
	</div>
</div>
</body>
</html>
