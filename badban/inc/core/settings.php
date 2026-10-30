<?php
/**
 * Global settings: LinkedIn app, AI, network, publishing schedule.
 *
 * Badban shares its AI with Sokan (سُکان) installed on the same site: by default
 * the posts are written with Sokan's model and budget, so the agency configures
 * the key once. The brand strategy (positioning, pillars, voice) lives in its
 * own option, see inc/engine/brand.php.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

const BDB_SECRET_KEYS = array( 'ai_gateway_key', 'anthropic_key', 'li_client_secret', 'li_token', 'relay_secret' );

/**
 * Default settings.
 */
function bdb_settings_defaults() {
	return array(
		// LinkedIn app (developer.linkedin.com › My apps): products «Share on LinkedIn» + «Sign In with LinkedIn using OpenID Connect».
		'li_client_id'     => '',
		'li_client_secret' => '',
		'li_version'       => '202608',  // LinkedIn-Version header (YYYYMM) of the versioned REST API.
		// Connected member (filled by the OAuth callback).
		'li_token'         => '',
		'li_expires'       => 0,
		'li_person'        => '',  // urn:li:person:xxxx
		'li_name'          => '',
		'li_picture'       => '',
		'li_email'         => '',
		'li_connected_at'  => '',
		// AI: «sokan» = the model connected to Sokan; «own» = the fields below.
		'ai_source'        => 'sokan',
		'ai_provider'      => 'gateway',
		'ai_base_url'      => 'https://api.gapgpt.app/v1',
		'ai_gateway_key'   => '',
		'anthropic_key'    => '',
		'ai_model'         => 'claude-sonnet-5-5', // Writing posts and carousels.
		'ai_model_light'   => 'claude-haiku-5-5',  // Ideas, hooks, quick rewrites.
		'ai_budget'        => 5,
		// Network: the server is in Iran, LinkedIn is not reachable directly.
		'proxy_mode'       => 'smart',  // smart|all|none (when WP_PROXY_HOST is set).
		'relay_url'        => '',       // Optional Cloudflare Worker relay (relay/cloudflare-worker.js).
		'relay_secret'     => '',
		// Publishing.
		'auto_publish'     => 1,        // Scheduled posts go out by themselves (cron).
		'require_approval' => 1,        // Written posts wait in «ready» for one click before they are scheduled.
		'first_comment'    => 1,        // Post the prepared first comment after publishing.
		'audience_tz'      => 'Europe/London',
		'fa_tz'            => 'Asia/Tehran',
		'notify_email'     => '',
	);
}

/**
 * All settings (secrets decrypted).
 */
function bdb_settings() {
	if ( isset( $GLOBALS['bdb_settings_cache'] ) ) {
		return $GLOBALS['bdb_settings_cache'];
	}
	$stored = get_option( 'bdb_settings', array() );
	$s      = wp_parse_args( is_array( $stored ) ? $stored : array(), bdb_settings_defaults() );
	foreach ( BDB_SECRET_KEYS as $k ) {
		$s[ $k ] = bdb_decrypt( $s[ $k ] );
	}
	$GLOBALS['bdb_settings_cache'] = $s;
	return $s;
}

/**
 * One setting.
 */
function bdb_setting( $key, $default = '' ) {
	$s = bdb_settings();
	return isset( $s[ $key ] ) && '' !== $s[ $key ] ? $s[ $key ] : $default;
}

/**
 * Partial update. Secrets are encrypted at rest.
 */
function bdb_update_settings( $changes ) {
	$stored = get_option( 'bdb_settings', array() );
	$stored = is_array( $stored ) ? $stored : array();
	foreach ( $changes as $k => $v ) {
		$stored[ $k ] = in_array( $k, BDB_SECRET_KEYS, true ) ? bdb_encrypt( $v ) : $v;
	}
	update_option( 'bdb_settings', $stored, false );
	unset( $GLOBALS['bdb_settings_cache'] );
}

/**
 * Sokan's settings on this site (secrets decrypted), or [] when Sokan never ran here.
 */
function bdb_sokan_settings() {
	if ( isset( $GLOBALS['bdb_sokan_cache'] ) ) {
		return $GLOBALS['bdb_sokan_cache'];
	}
	$s = get_option( 'sap_settings', array() );
	if ( ! is_array( $s ) || ! $s ) {
		$GLOBALS['bdb_sokan_cache'] = array();
		return array();
	}
	foreach ( array( 'ai_gateway_key', 'anthropic_key' ) as $k ) {
		if ( isset( $s[ $k ] ) ) {
			$s[ $k ] = bdb_decrypt( $s[ $k ], 'seo-action-plan' );
		}
	}
	$GLOBALS['bdb_sokan_cache'] = $s;
	return $s;
}

/**
 * Effective AI configuration of Badban's own engine (used when Sokan's engine
 * is not active on this request; Sokan's stored key is still reused).
 *
 * @return array{source: string, provider: string, base_url: string, key: string, anthropic_key: string, model: string, model_light: string}
 */
function bdb_ai_conf() {
	$own = bdb_settings();
	$sk  = bdb_sokan_settings();
	if ( 'sokan' === $own['ai_source'] && $sk && ( '' !== (string) ( $sk['ai_gateway_key'] ?? '' ) || '' !== (string) ( $sk['anthropic_key'] ?? '' ) ) ) {
		return array(
			'source'        => 'sokan',
			'provider'      => (string) ( $sk['ai_provider'] ?? 'gateway' ),
			'base_url'      => (string) ( $sk['ai_base_url'] ?? 'https://api.gapgpt.app/v1' ),
			'key'           => (string) ( $sk['ai_gateway_key'] ?? '' ),
			'anthropic_key' => (string) ( $sk['anthropic_key'] ?? '' ),
			'model'         => (string) ( ( $sk['ai_model'] ?? '' ) ?: 'claude-sonnet-5-5' ),
			'model_light'   => (string) ( ( $sk['ai_model_assist'] ?? '' ) ?: ( $sk['ai_model'] ?? 'claude-haiku-5-5' ) ),
		);
	}
	return array(
		'source'        => 'own',
		'provider'      => $own['ai_provider'],
		'base_url'      => $own['ai_base_url'],
		'key'           => $own['ai_gateway_key'],
		'anthropic_key' => $own['anthropic_key'],
		'model'         => $own['ai_model'],
		'model_light'   => $own['ai_model_light'] ?: $own['ai_model'],
	);
}

/**
 * Timezone object of a language's audience (en → international audience, fa → Iran).
 */
function bdb_tz( $lang = 'en' ) {
	$name = 'fa' === $lang ? bdb_setting( 'fa_tz', 'Asia/Tehran' ) : bdb_setting( 'audience_tz', 'Europe/London' );
	try {
		return new DateTimeZone( $name );
	} catch ( Exception $e ) {
		return new DateTimeZone( 'fa' === $lang ? 'Asia/Tehran' : 'UTC' );
	}
}

/**
 * Timezones offered for the audience.
 */
function bdb_timezones() {
	return array(
		'America/New_York'    => 'نیویورک / تورنتو (ET)',
		'America/Los_Angeles' => 'کالیفرنیا (PT)',
		'Europe/London'       => 'لندن (GMT/BST)',
		'Europe/Berlin'       => 'برلین / پاریس / آمستردام (CET)',
		'Asia/Dubai'          => 'دبی (GST)',
		'Asia/Riyadh'         => 'ریاض',
		'Europe/Istanbul'     => 'استانبول',
		'Asia/Tehran'         => 'تهران',
		'Australia/Sydney'    => 'سیدنی',
		'UTC'                 => 'UTC',
	);
}
