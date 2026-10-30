<?php
/**
 * UI helpers: flash messages, chips, small form parts, inline SVG charts.
 *
 * @package Badban
 */

defined( 'ABSPATH' ) || exit;

/**
 * Queue a flash message for the next screen.
 */
function bdb_flash_set( $msg, $ok = true ) {
	set_transient( 'bdb_flash_' . get_current_user_id(), array( 'm' => $msg, 'ok' => $ok ), 120 );
}

/**
 * Print and clear the flash message.
 */
function bdb_flash() {
	$key = 'bdb_flash_' . get_current_user_id();
	$f   = get_transient( $key );
	if ( $f ) {
		delete_transient( $key );
		printf( '<div class="bdb-flash %s">%s</div>', $f['ok'] ? 'is-ok' : 'is-err', esc_html( $f['m'] ) );
	}
}

/**
 * Hidden fields of an app form.
 */
function bdb_form_fields( $action, $extra = array() ) {
	echo '<input type="hidden" name="bdb_action" value="' . esc_attr( $action ) . '">';
	wp_nonce_field( 'bdb_' . $action, '_bdb_nonce' );
	foreach ( $extra as $k => $v ) {
		echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}
}

/**
 * <option> list.
 */
function bdb_options( $options, $current ) {
	foreach ( $options as $v => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $v ), selected( (string) $current, (string) $v, false ), esc_html( is_array( $label ) ? $label[0] : $label ) );
	}
}

/**
 * Status chip.
 */
function bdb_chip_status( $status ) {
	$s = bdb_statuses()[ $status ] ?? array( $status, '' );
	return '<span class="bdb-chip ' . esc_attr( $s[1] ) . '">' . esc_html( $s[0] ) . '</span>';
}

/**
 * Format chip (with icon).
 */
function bdb_chip_format( $format ) {
	$f = bdb_formats()[ $format ] ?? array( $format, 'pen' );
	return '<span class="bdb-chip is-format">' . bdb_icon( $f[1], 13 ) . esc_html( $f[0] ) . '</span>'; // phpcs:ignore
}

/**
 * Language chip.
 */
function bdb_chip_lang( $lang ) {
	return 'fa' === $lang ? '<span class="bdb-chip is-fa">FA · فارسی</span>' : '<span class="bdb-chip is-en">EN · English</span>';
}

/**
 * Pillar chip.
 */
function bdb_chip_pillar( $pillar ) {
	return '' === (string) $pillar ? '' : '<span class="bdb-chip is-pillar">' . esc_html( bdb_pillar_label( $pillar ) ) . '</span>';
}

/**
 * Connection indicator.
 */
function bdb_conn( $label, $state ) {
	$cls = 'sokan' === $state ? 'is-sokan' : ( $state ? 'is-on' : '' );
	printf( '<span class="bdb-conn %s"><i></i>%s</span>', esc_attr( $cls ), esc_html( $label ) );
}

/**
 * Segmented radio control.
 */
function bdb_segment( $name, $options, $current, $toggle = false ) {
	echo '<div class="bdb-seg is-radio">';
	foreach ( $options as $v => $label ) {
		printf(
			'<label><input type="radio" name="%1$s" value="%2$s"%3$s%5$s><span>%4$s</span></label>',
			esc_attr( $name ),
			esc_attr( $v ),
			checked( (string) $current, (string) $v, false ),
			esc_html( $label ),
			$toggle ? ' data-bdb-toggle="' . esc_attr( $name ) . '"' : ''
		);
	}
	echo '</div>';
}

/**
 * First lines of a post for a list (the hook).
 */
function bdb_hook_preview( $body, $max = 160 ) {
	$t = trim( preg_replace( '/\s+/u', ' ', implode( ' ', array_slice( preg_split( '/\n/', trim( (string) $body ) ), 0, 2 ) ) ) );
	return mb_strlen( $t ) > $max ? mb_substr( $t, 0, $max - 1 ) . '…' : $t;
}

/**
 * Inline SVG line/area chart.
 *
 * @param array $series [ [label, values[], class], … ] (values aligned with $labels).
 * @param array $labels x labels (shown sparsely).
 */
function bdb_svg_line( $series, $labels, $height = 220, $from_zero = true ) {
	$w    = 760;
	$h    = (int) $height;
	$pad  = array( 'l' => 8, 'r' => 44, 't' => 14, 'b' => 26 );
	$max  = 0;
	$min  = PHP_INT_MAX;
	foreach ( $series as $s ) {
		$max = max( $max, max( array_merge( array( 0 ), $s[1] ) ) );
		$min = min( $min, min( array_merge( array( PHP_INT_MAX ), $s[1] ) ) );
	}
	// Totals (e.g. followers) zoom to their own range; daily counts start at zero.
	$lo    = $from_zero || PHP_INT_MAX === $min ? 0 : max( 0, $min - ( $max - $min ) * 0.15 );
	$max   = $max > $lo ? $lo + ( $max - $lo ) * 1.1 : $lo + 1;
	$n     = max( 2, count( $labels ) );
	$x     = function ( $i ) use ( $w, $pad, $n ) {
		return $pad['l'] + ( $w - $pad['l'] - $pad['r'] ) * $i / ( $n - 1 );
	};
	$y     = function ( $v ) use ( $h, $pad, $max, $lo ) {
		return $h - $pad['b'] - ( $h - $pad['t'] - $pad['b'] ) * ( $v - $lo ) / ( $max - $lo );
	};
	$data  = array( 'labels' => array_values( $labels ), 'series' => array_map( function ( $s ) {
		return array( 'name' => $s[0], 'values' => array_values( $s[1] ) );
	}, $series ), 'pad' => $pad, 'w' => $w );
	$out   = sprintf( '<div class="bdb-chart-wrap"><svg class="bdb-chart" style="height:%2$dpx" viewBox="0 0 %1$d %2$d" preserveAspectRatio="none" role="img" data-bdb-chart="%3$s">',$w, $h, esc_attr( wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) ) );
	$top = $lo + ( $max - $lo ) / 1.1;
	for ( $g = 0; $g <= 3; $g++ ) {
		$v    = $lo + ( $top - $lo ) * $g / 3;
		$gy   = $y( $v );
		$out .= sprintf( '<line class="grid" x1="%d" x2="%d" y1="%.1f" y2="%.1f"/><text class="axis" x="%d" y="%.1f">%s</text>', $pad['l'], $w - $pad['r'], $gy, $gy, $w - $pad['r'] + 6, $gy + 4, esc_html( bdb_compact( $v ) ) );
	}
	$step = max( 1, (int) ceil( $n / 7 ) );
	foreach ( $labels as $i => $l ) {
		if ( 0 === $i % $step ) {
			$out .= sprintf( '<text class="axis is-x" x="%.1f" y="%d">%s</text>', $x( $i ), $h - 6, esc_html( $l ) );
		}
	}
	foreach ( $series as $s ) {
		$pts = array();
		foreach ( array_values( $s[1] ) as $i => $v ) {
			$pts[] = sprintf( '%.1f,%.1f', $x( $i ), $y( $v ) );
		}
		if ( ! $pts ) {
			continue;
		}
		$cls = esc_attr( $s[2] ?? 's1' );
		if ( ! empty( $s[3] ) ) { // Area.
			$out .= sprintf( '<polygon class="area %s" points="%.1f,%.1f %s %.1f,%.1f"/>', $cls, $x( 0 ), $y( $lo ), implode( ' ', $pts ), $x( count( $pts ) - 1 ), $y( $lo ) );
		}
		$out .= sprintf( '<polyline class="line %s" points="%s"><title>%s</title></polyline>', $cls, implode( ' ', $pts ), esc_html( $s[0] ) );
	}
	return $out . '<line class="cross" x1="0" x2="0" y1="' . $pad['t'] . '" y2="' . ( $h - $pad['b'] ) . '" hidden/></svg><div class="bdb-tip" hidden></div></div>';
}

/**
 * Horizontal bars: rows of [label, value, display, class].
 */
function bdb_bars( $rows ) {
	$max = max( array_merge( array( 0 ), array_column( $rows, 1 ) ) ) ?: 1;
	$out = '<div class="bdb-bars">';
	foreach ( $rows as $r ) {
		$out .= sprintf(
			'<div class="bdb-barrow"><span title="%1$s">%1$s</span><div class="bdb-bar"><i class="%4$s" style="width:%2$.1f%%"></i></div><b>%3$s</b></div>',
			esc_html( $r[0] ),
			max( 2, 100 * $r[1] / $max ),
			esc_html( $r[2] ),
			esc_attr( $r[3] ?? '' )
		);
	}
	return $out . '</div>';
}

/**
 * Delta badge (+12٪ / −4٪).
 */
function bdb_delta( $now, $before ) {
	if ( ! $before ) {
		return '';
	}
	$d = ( $now - $before ) / $before;
	return sprintf( '<em class="bdb-delta %s">%s%s</em>', $d >= 0 ? 'is-up' : 'is-down', $d >= 0 ? '+' : '−', esc_html( bdb_pct( abs( $d ) ) ) );
}
