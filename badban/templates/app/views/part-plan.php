<?php
/**
 * Plan runner: builds the plan, then writes each planned post (and renders its
 * PDF / image in the browser) one by one. Driven by app.js.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="bdb-run" data-bdb-plan hidden>
	<div class="bdb-progress"><i></i></div>
	<p data-bdb-plan-label>آماده‌سازی برنامه…</p>
	<ul class="bdb-log" data-bdb-plan-log></ul>
</div>
