<?php
/**
 * Form posts of the app (settings, brand, schedule). Writing, publishing and
 * uploads go through the REST API instead.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handle a POSTed app form, then redirect (PRG).
 */
function bdb_handle_post( $route ) {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['bdb_action'] ) ) { // phpcs:ignore
		return;
	}
	$action = sanitize_key( wp_unslash( $_POST['bdb_action'] ) ); // phpcs:ignore
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_bdb_nonce'] ?? '' ) ), 'bdb_' . $action ) ) { // phpcs:ignore
		bdb_flash_set( 'نشست منقضی شد؛ دوباره امتحان کنید.', false );
		wp_safe_redirect( bdb_url( $route ) );
		exit;
	}
	$post = wp_unslash( $_POST ); // phpcs:ignore
	switch ( $action ) {
		case 'save_settings':
			$tzs     = bdb_timezones();
			$changes = array(
				'li_client_id'     => sanitize_text_field( (string) ( $post['li_client_id'] ?? '' ) ),
				'li_version'       => preg_replace( '/\D/', '', (string) ( $post['li_version'] ?? '' ) ) ?: '202608',
				'ai_source'        => 'own' === ( $post['ai_source'] ?? '' ) ? 'own' : 'sokan',
				'ai_provider'      => 'anthropic' === ( $post['ai_provider'] ?? '' ) ? 'anthropic' : 'gateway',
				'ai_base_url'      => esc_url_raw( trim( (string) ( $post['ai_base_url'] ?? '' ) ) ) ?: 'https://api.gapgpt.app/v1',
				'ai_model'         => sanitize_text_field( (string) ( $post['ai_model'] ?? '' ) ) ?: 'claude-sonnet-5-5',
				'ai_model_light'   => sanitize_text_field( (string) ( $post['ai_model_light'] ?? '' ) ),
				'ai_budget'        => max( 0, (float) ( $post['ai_budget'] ?? 5 ) ),
				'proxy_mode'       => in_array( $post['proxy_mode'] ?? '', array( 'smart', 'all', 'none' ), true ) ? $post['proxy_mode'] : 'smart',
				'relay_url'        => esc_url_raw( trim( (string) ( $post['relay_url'] ?? '' ) ) ),
				'auto_publish'     => empty( $post['auto_publish'] ) ? 0 : 1,
				'require_approval' => empty( $post['require_approval'] ) ? 0 : 1,
				'first_comment'    => empty( $post['first_comment'] ) ? 0 : 1,
				'audience_tz'      => isset( $tzs[ $post['audience_tz'] ?? '' ] ) ? $post['audience_tz'] : 'Europe/London',
				'notify_email'     => sanitize_email( (string) ( $post['notify_email'] ?? '' ) ),
			);
			// Secrets: keep the stored value when the masked placeholder comes back.
			foreach ( array( 'li_client_secret', 'ai_gateway_key', 'anthropic_key', 'relay_secret' ) as $k ) {
				$v = trim( (string) ( $post[ $k ] ?? '' ) );
				if ( '' !== $v && false === strpos( $v, '•' ) ) {
					$changes[ $k ] = $v;
				} elseif ( ! empty( $post[ $k . '_clear' ] ) ) {
					$changes[ $k ] = '';
				}
			}
			bdb_update_settings( $changes );
			bdb_flash_set( 'تنظیمات ذخیره شد.' );
			wp_safe_redirect( bdb_url( 'settings' ) );
			exit;

		case 'save_schedule':
			$sched = array( 'en' => array(), 'fa' => array(), 'weekly' => max( 1, min( 14, (int) ( $post['weekly'] ?? 5 ) ) ) );
			foreach ( array( 'en', 'fa' ) as $lang ) {
				foreach ( (array) ( $post[ 'slot_' . $lang ] ?? array() ) as $day => $time ) {
					$time = preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', trim( (string) $time ) ) ? trim( (string) $time ) : '';
					if ( '' !== $time && ! empty( $post[ 'on_' . $lang ][ $day ] ) ) {
						$sched[ $lang ][] = array( 'day' => (int) $day, 'time' => $time );
					}
				}
			}
			update_option( 'bdb_schedule', $sched, false );
			$tzs = bdb_timezones();
			if ( isset( $tzs[ $post['audience_tz'] ?? '' ] ) ) {
				bdb_update_settings( array( 'audience_tz' => $post['audience_tz'] ) );
			}
			bdb_flash_set( sprintf( 'برنامه هفتگی ذخیره شد: %s پست انگلیسی و %s پست فارسی در هفته.', bdb_num( count( $sched['en'] ) ), bdb_num( count( $sched['fa'] ) ) ) );
			wp_safe_redirect( bdb_url( 'calendar' ) );
			exit;

		case 'save_brand':
			$b = bdb_brand();
			foreach ( array( 'name', 'name_fa', 'headline', 'headline_fa', 'handle', 'hashtags_en', 'hashtags_fa', 'magnet', 'magnet_word', 'signature', 'signature_fa' ) as $k ) {
				$b[ $k ] = sanitize_text_field( (string) ( $post[ $k ] ?? '' ) );
			}
			foreach ( array( 'positioning', 'audience', 'audience_fa', 'goals', 'proof', 'voice', 'avoid', 'samples', 'private_terms' ) as $k ) {
				$b[ $k ] = sanitize_textarea_field( (string) ( $post[ $k ] ?? '' ) );
			}
			foreach ( array( 'booking_url', 'photo_url' ) as $k ) {
				$b[ $k ] = esc_url_raw( trim( (string) ( $post[ $k ] ?? '' ) ) );
			}
			foreach ( array( 'brand_color', 'brand_color2', 'brand_dark' ) as $k ) {
				$b[ $k ] = sanitize_hex_color( (string) ( $post[ $k ] ?? '' ) ) ?: bdb_brand_defaults()[ $k ];
			}
			$pillars = array();
			foreach ( (array) ( $post['pillar_key'] ?? array() ) as $i => $key ) {
				$key   = sanitize_key( (string) $key );
				$label = sanitize_text_field( (string) ( $post['pillar_label'][ $i ] ?? '' ) );
				if ( '' === $label ) {
					continue;
				}
				if ( '' === $key ) {
					$key = 'p' . substr( md5( $label ), 0, 6 );
				}
				$pillars[ $key ] = array( $label, sanitize_text_field( (string) ( $post['pillar_desc'][ $i ] ?? '' ) ), max( 0, min( 100, (int) ( $post['pillar_weight'][ $i ] ?? 10 ) ) ) );
			}
			$b['pillars'] = $pillars ?: bdb_pillars_defaults();
			bdb_brand_save( $b );
			delete_transient( 'bdb_private_terms' );
			bdb_flash_set( 'استراتژی برند ذخیره شد؛ پست‌های بعدی با همین تنظیمات نوشته می‌شوند.' );
			wp_safe_redirect( bdb_url( 'brand' ) );
			exit;

		case 'li_disconnect':
			bdb_li_disconnect();
			bdb_flash_set( 'اتصال لینکدین قطع شد.' );
			wp_safe_redirect( bdb_url( 'settings' ) );
			exit;
	}
}
