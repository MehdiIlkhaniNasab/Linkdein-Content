<?php
/**
 * 404 inside the app.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="bdb-empty">
	<?php echo bdb_icon( 'alert', 40 ); // phpcs:ignore ?>
	<h2>این صفحه پیدا نشد</h2>
	<p>شاید پست حذف شده باشد.</p>
	<a class="bdb-btn is-primary" href="<?php echo esc_url( bdb_url() ); ?>">بازگشت به داشبورد</a>
</div>
