<?php
/**
 * Plugin Name:       بادبان — Badban by Ronagh
 * Description:       بادبان، برندسازی شخصی در لینکدین: استراتژی و ستون‌های محتوا، ایده از دل پروژه‌های سکان، قطب‌نما و فانوس، نوشتن پست انگلیسی و فارسی با هوش مصنوعی سکان، کاروسل PDF و کارت تصویری با تم برند، CTAهای متنوع، تقویم و انتشار خودکار روی پروفایل لینکدین، آنالیتیکس و گزارش ماهانه.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Mehdi Ilkhani Nasab
 * License:           GPL-2.0-or-later
 * Text Domain:       badban
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

const BDB_VERSION    = '1.0.0';
const BDB_DB_VERSION = '1';
const BDB_CAP        = 'bdb_manage';

define( 'BDB_FILE', __FILE__ );
define( 'BDB_DIR', __DIR__ );
define( 'BDB_URL', plugin_dir_url( __FILE__ ) );

foreach ( array(
	'core/helpers',
	'core/jalali',
	'core/settings',
	'core/network',
	'core/install',
	'models/posts',
	'models/ideas',
	'models/stats',
	'connectors/llm',
	'connectors/linkedin',
	'export/xlsx-reader',
	'engine/brand',
	'engine/sources',
	'engine/writer',
	'engine/quality',
	'engine/planner',
	'engine/publisher',
	'engine/analytics',
	'rest',
	'app/ui',
	'app/router',
	'app/actions',
) as $bdb_file ) {
	require_once BDB_DIR . '/inc/' . $bdb_file . '.php';
}
unset( $bdb_file );

register_activation_hook( __FILE__, 'bdb_install' );
register_deactivation_hook( __FILE__, 'bdb_deactivate' );
