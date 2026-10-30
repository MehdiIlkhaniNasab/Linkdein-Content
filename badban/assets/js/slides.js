/* Badban (بادبان) — slide renderer and exporters.
 *
 * Slides are drawn on a 1080×1350 canvas (LinkedIn's 4:5 portrait document
 * size) in the brand's diamond-purple theme, Persian slides right-to-left.
 * The same drawing is used for the live preview, the PDF uploaded to LinkedIn
 * (carousel), the PNG (image card) and the PPTX download for presenting.
 * No libraries: the PDF (JPEG pages) and the PPTX (zip, stored) are written here,
 * so nothing is loaded from a CDN.
 */
( function () {
	'use strict';

	var W = 1080, H = 1350, PAD = 96;
	var conf = ( window.BDB && window.BDB.render ) || {};
	var fontReady = null;
	var photo = null, photoTried = false;

	function ready( c ) {
		if ( c ) { conf = c; }
		if ( ! fontReady ) {
			fontReady = ( function () {
				if ( ! window.FontFace || ! document.fonts ) { return Promise.resolve(); }
				var f = new FontFace( 'BadbanVazir', 'url(' + conf.font + ')', { weight: '100 900' } );
				return f.load().then( function ( ff ) {
					document.fonts.add( ff );
					return Promise.all( [ '400', '600', '800' ].map( function ( w ) { return document.fonts.load( w + ' 40px BadbanVazir' ); } ) );
				} ).catch( function () {} );
			} )();
		}
		var p = fontReady;
		if ( conf.photo && ! photoTried ) {
			photoTried = true;
			p = p.then( function () {
				return new Promise( function ( res ) {
					var img = new Image();
					img.crossOrigin = 'anonymous';
					img.onload = function () { photo = img; res(); };
					img.onerror = function () { res(); };
					img.src = conf.photo;
				} );
			} );
		}
		return p;
	}

	// ---- Colours ----
	function hex( h ) {
		h = String( h || '#7C3AED' ).replace( '#', '' );
		if ( h.length === 3 ) { h = h.replace( /./g, '$&$&' ); }
		var n = parseInt( h, 16 );
		return [ ( n >> 16 ) & 255, ( n >> 8 ) & 255, n & 255 ];
	}
	function rgba( h, a ) { var c = hex( h ); return 'rgba(' + c[ 0 ] + ',' + c[ 1 ] + ',' + c[ 2 ] + ',' + a + ')'; }
	function mix( h1, h2, t ) {
		var a = hex( h1 ), b = hex( h2 );
		return 'rgb(' + a.map( function ( v, i ) { return Math.round( v + ( b[ i ] - v ) * t ); } ).join( ',' ) + ')';
	}
	function C() {
		return {
			main: conf.color || '#7C3AED',
			crystal: conf.color2 || '#C4B5FD',
			dark: conf.dark || '#160B2E',
			ink: '#1B1530',
			ink2: '#4A4363',
			paper: '#FBF9FF'
		};
	}

	// ---- Text ----
	function font( weight, size ) { return weight + ' ' + size + 'px BadbanVazir, Vazirmatn, Tahoma, sans-serif'; }
	function isRtl( t ) {
		var fa = ( String( t ).match( /[؀-ۿ]/g ) || [] ).length;
		var en = ( String( t ).match( /[A-Za-z]/g ) || [] ).length;
		return fa > en;
	}
	function wrap( ctx, text, maxW ) {
		var out = [];
		String( text || '' ).split( /\n/ ).forEach( function ( para ) {
			var words = para.split( /\s+/ ).filter( Boolean ), line = '';
			words.forEach( function ( w ) {
				var test = line ? line + ' ' + w : w;
				if ( ctx.measureText( test ).width > maxW && line ) {
					out.push( line );
					line = w;
				} else {
					line = test;
				}
			} );
			out.push( line );
		} );
		return out;
	}
	/* Largest size (step 2px) at which the text fits in maxW × maxH. */
	function fit( ctx, text, maxW, maxH, size, min, weight, lh ) {
		var s = size, lines;
		for ( ; s >= min; s -= 2 ) {
			ctx.font = font( weight, s );
			lines = wrap( ctx, text, maxW );
			if ( lines.length * s * lh <= maxH ) { break; }
		}
		s = Math.max( s, min );
		ctx.font = font( weight, s );
		return { size: s, lines: wrap( ctx, text, maxW ), lh: lh };
	}
	/* Draw fitted lines starting at y (top). Returns the y below the block. */
	function lines( ctx, f, x, y, color, rtl ) {
		ctx.fillStyle = color;
		ctx.textBaseline = 'top';
		ctx.textAlign = rtl ? 'right' : 'left';
		ctx.direction = rtl ? 'rtl' : 'ltr';
		f.lines.forEach( function ( l, i ) { ctx.fillText( l, x, y + i * f.size * f.lh ); } );
		return y + f.lines.length * f.size * f.lh;
	}
	function rr( ctx, x, y, w, h, r ) {
		ctx.beginPath();
		ctx.moveTo( x + r, y );
		ctx.arcTo( x + w, y, x + w, y + h, r );
		ctx.arcTo( x + w, y + h, x, y + h, r );
		ctx.arcTo( x, y + h, x, y, r );
		ctx.arcTo( x, y, x + w, y, r );
		ctx.closePath();
	}
	function diamond( ctx, cx, cy, s, fill ) {
		ctx.beginPath();
		ctx.moveTo( cx - s, cy - s * 0.35 );
		ctx.lineTo( cx - s * 0.55, cy - s * 0.9 );
		ctx.lineTo( cx + s * 0.55, cy - s * 0.9 );
		ctx.lineTo( cx + s, cy - s * 0.35 );
		ctx.lineTo( cx, cy + s );
		ctx.closePath();
		ctx.fillStyle = fill;
		ctx.fill();
	}

	// ---- Backgrounds ----
	function bgDark( ctx ) {
		var c = C();
		var g = ctx.createLinearGradient( 0, 0, W, H );
		g.addColorStop( 0, c.dark );
		g.addColorStop( 1, mix( c.dark, c.main, 0.45 ) );
		ctx.fillStyle = g;
		ctx.fillRect( 0, 0, W, H );
		var r = ctx.createRadialGradient( W * 0.85, H * 0.08, 10, W * 0.85, H * 0.08, W * 0.75 );
		r.addColorStop( 0, rgba( c.main, 0.55 ) );
		r.addColorStop( 1, rgba( c.main, 0 ) );
		ctx.fillStyle = r;
		ctx.fillRect( 0, 0, W, H );
		facets( ctx, c.crystal, 0.07 );
	}
	function bgLight( ctx ) {
		var c = C();
		ctx.fillStyle = c.paper;
		ctx.fillRect( 0, 0, W, H );
		var r = ctx.createRadialGradient( W, 0, 10, W, 0, W * 0.9 );
		r.addColorStop( 0, rgba( c.crystal, 0.45 ) );
		r.addColorStop( 1, rgba( c.crystal, 0 ) );
		ctx.fillStyle = r;
		ctx.fillRect( 0, 0, W, H );
		facets( ctx, c.main, 0.035 );
		var g = ctx.createLinearGradient( 0, 0, W, 0 );
		g.addColorStop( 0, c.main );
		g.addColorStop( 1, c.crystal );
		ctx.fillStyle = g;
		ctx.fillRect( 0, 0, W, 10 );
	}
	/* Diamond facets: large faint triangles in one corner. */
	function facets( ctx, color, a ) {
		var pts = [ [ W, H * 0.55 ], [ W * 0.62, H ], [ W, H ], [ W * 0.78, H * 0.72 ], [ W * 0.9, H * 0.86 ] ];
		var tris = [ [ 0, 3, 2 ], [ 3, 1, 2 ], [ 0, 4, 3 ], [ 4, 1, 3 ] ];
		tris.forEach( function ( t, i ) {
			ctx.beginPath();
			ctx.moveTo( pts[ t[ 0 ] ][ 0 ], pts[ t[ 0 ] ][ 1 ] );
			ctx.lineTo( pts[ t[ 1 ] ][ 0 ], pts[ t[ 1 ] ][ 1 ] );
			ctx.lineTo( pts[ t[ 2 ] ][ 0 ], pts[ t[ 2 ] ][ 1 ] );
			ctx.closePath();
			ctx.fillStyle = rgba( color, a * ( 1 + ( i % 2 ) ) );
			ctx.fill();
		} );
	}

	// ---- Chrome: header, footer, author ----
	function header( ctx, dark, rtl, lang ) {
		var c = C();
		var name = lang === 'fa' ? ( conf.name_fa || conf.name ) : conf.name;
		var x = rtl ? W - PAD : PAD;
		diamond( ctx, rtl ? x - 18 : x + 18, 92, 18, dark ? c.crystal : c.main );
		ctx.font = font( 700, 26 );
		ctx.fillStyle = dark ? '#FFFFFF' : c.ink;
		ctx.textBaseline = 'middle';
		ctx.textAlign = rtl ? 'right' : 'left';
		ctx.direction = rtl ? 'rtl' : 'ltr';
		ctx.fillText( name || '', rtl ? x - 50 : x + 50, 84 );
		if ( conf.handle ) {
			ctx.font = font( 400, 20 );
			ctx.fillStyle = dark ? rgba( '#FFFFFF', 0.6 ) : c.ink2;
			ctx.direction = 'ltr';
			ctx.fillText( conf.handle, rtl ? x - 50 : x + 50, 114 );
		}
	}
	function footer( ctx, dark, rtl, i, n, lang ) {
		var c = C();
		var y = H - 92;
		ctx.textBaseline = 'middle';
		ctx.font = font( 700, 24 );
		ctx.fillStyle = dark ? rgba( '#FFFFFF', 0.7 ) : c.ink2;
		ctx.direction = 'ltr';
		ctx.textAlign = rtl ? 'right' : 'left';
		var num = pad2( i + 1 ) + ' / ' + pad2( n );
		if ( lang === 'fa' ) { num = toFa( num ); }
		ctx.fillText( num, rtl ? W - PAD : PAD, y );
		// Progress.
		var bw = 220, bx = rtl ? W - PAD - bw : PAD, by = y + 34;
		ctx.fillStyle = dark ? rgba( '#FFFFFF', 0.15 ) : rgba( c.main, 0.12 );
		rr( ctx, bx, by, bw, 6, 3 ); ctx.fill();
		ctx.fillStyle = dark ? c.crystal : c.main;
		var pw = bw * ( i + 1 ) / n;
		rr( ctx, rtl ? bx + bw - pw : bx, by, pw, 6, 3 ); ctx.fill();
		if ( i < n - 1 ) {
			var label = lang === 'fa' ? 'ورق بزنید  ←' : 'Swipe  →';
			ctx.font = font( 700, 24 );
			var tw = ctx.measureText( label ).width + 48;
			var px = rtl ? PAD : W - PAD - tw;
			ctx.fillStyle = dark ? rgba( '#FFFFFF', 0.12 ) : rgba( c.main, 0.1 );
			rr( ctx, px, y - 30, tw, 60, 30 ); ctx.fill();
			ctx.fillStyle = dark ? '#FFFFFF' : c.main;
			ctx.textAlign = 'center';
			ctx.direction = rtl ? 'rtl' : 'ltr';
			ctx.fillText( label, px + tw / 2, y );
		}
	}
	function author( ctx, x, y, rtl, dark, lang, center ) {
		var c = C(), r = 46;
		var cx = center ? W / 2 : ( rtl ? x - r : x + r );
		ctx.save();
		ctx.beginPath();
		ctx.arc( cx, y + r, r, 0, Math.PI * 2 );
		ctx.closePath();
		if ( photo ) {
			ctx.clip();
			var s = Math.min( photo.width, photo.height );
			ctx.drawImage( photo, ( photo.width - s ) / 2, ( photo.height - s ) / 2, s, s, cx - r, y, r * 2, r * 2 );
		} else {
			var g = ctx.createLinearGradient( cx - r, y, cx + r, y + 2 * r );
			g.addColorStop( 0, c.main ); g.addColorStop( 1, c.crystal );
			ctx.fillStyle = g; ctx.fill();
			ctx.fillStyle = '#FFFFFF';
			ctx.font = font( 800, 40 );
			ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
			ctx.fillText( String( ( lang === 'fa' ? conf.name_fa : conf.name ) || 'B' ).charAt( 0 ), cx, y + r + 2 );
		}
		ctx.restore();
		ctx.strokeStyle = dark ? c.crystal : c.main; ctx.lineWidth = 4;
		ctx.beginPath(); ctx.arc( cx, y + r, r + 4, 0, Math.PI * 2 ); ctx.stroke();
		var name = lang === 'fa' ? ( conf.name_fa || conf.name ) : conf.name;
		var head = lang === 'fa' ? ( conf.headline_fa || conf.headline ) : conf.headline;
		ctx.textBaseline = 'top';
		ctx.direction = rtl ? 'rtl' : 'ltr';
		if ( center ) {
			ctx.textAlign = 'center';
			ctx.font = font( 800, 34 ); ctx.fillStyle = dark ? '#FFFFFF' : c.ink;
			ctx.fillText( name || '', W / 2, y + 2 * r + 24 );
			var hf = fit( ctx, head || '', W - 2 * PAD - 80, 80, 26, 20, 400, 1.4 );
			ctx.font = font( 400, hf.size );
			ctx.fillStyle = dark ? rgba( '#FFFFFF', 0.75 ) : c.ink2;
			hf.lines.forEach( function ( l, i ) { ctx.fillText( l, W / 2, y + 2 * r + 72 + i * hf.size * 1.4 ); } );
			return y + 2 * r + 72 + hf.lines.length * hf.size * 1.4;
		}
		var tx = rtl ? x - 2 * r - 28 : x + 2 * r + 28;
		ctx.textAlign = rtl ? 'right' : 'left';
		ctx.font = font( 800, 30 ); ctx.fillStyle = dark ? '#FFFFFF' : c.ink;
		ctx.fillText( name || '', tx, y + 14 );
		ctx.font = font( 400, 22 ); ctx.fillStyle = dark ? rgba( '#FFFFFF', 0.72 ) : c.ink2;
		var hl = fit( ctx, head || '', W - 2 * PAD - 2 * r - 40, 60, 22, 18, 400, 1.35 );
		hl.lines.slice( 0, 2 ).forEach( function ( l, i ) { ctx.fillText( l, tx, y + 56 + i * hl.size * 1.35 ); } );
		return y + 2 * r;
	}
	function pad2( n ) { return ( n < 10 ? '0' : '' ) + n; }
	function toFa( s ) { return String( s ).replace( /\d/g, function ( d ) { return '۰۱۲۳۴۵۶۷۸۹'[ d ]; } ); }
	function kickerPill( ctx, text, x, y, rtl, dark ) {
		if ( ! text ) { return y; }
		var c = C();
		ctx.font = font( 700, 26 );
		ctx.direction = rtl ? 'rtl' : 'ltr';
		var tw = ctx.measureText( text ).width + 44;
		var px = rtl ? x - tw : x;
		ctx.fillStyle = dark ? rgba( c.crystal, 0.18 ) : rgba( c.main, 0.1 );
		rr( ctx, px, y, tw, 52, 26 ); ctx.fill();
		ctx.fillStyle = dark ? c.crystal : c.main;
		ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
		ctx.fillText( text, px + tw / 2, y + 27 );
		return y + 52;
	}

	// ---- Slide types ----
	var DARK = { cover: 1, stat: 1, quote: 1, cta: 1 };

	function draw( ctx, s, i, n, lang ) {
		var all = [ s.title, s.body ].concat( s.items || [] ).join( ' ' );
		var rtl = lang === 'fa' || ( lang !== 'en' && isRtl( all ) );
		var dark = !! DARK[ s.type ];
		var c = C();
		var x = rtl ? W - PAD : PAD;
		var cw = W - 2 * PAD;
		var top = 200, bottom = H - 170;
		dark ? bgDark( ctx ) : bgLight( ctx );
		header( ctx, dark, rtl, lang );
		var y = top, f;
		var ink = dark ? '#FFFFFF' : c.ink;
		var ink2 = dark ? rgba( '#FFFFFF', 0.78 ) : c.ink2;

		switch ( s.type ) {
			case 'cover':
				y = kickerPill( ctx, s.kicker, x, y + 60, rtl, true ) + 44;
				f = fit( ctx, s.title, cw, 520, 104, 56, 800, 1.18 );
				y = lines( ctx, f, x, y, '#FFFFFF', rtl ) + 34;
				if ( s.body ) {
					f = fit( ctx, s.body, cw, 210, 38, 26, 400, 1.5 );
					lines( ctx, f, x, y, rgba( c.crystal, 0.95 ), rtl );
				}
				author( ctx, x, H - 330, rtl, true, lang, false );
				break;

			case 'stat':
				y = kickerPill( ctx, s.kicker, x, y + 40, rtl, true ) + 40;
				ctx.font = font( 800, 230 );
				var sv = s.stat_value || '—';
				var size = 230;
				while ( ctx.measureText( sv ).width > cw && size > 100 ) { size -= 10; ctx.font = font( 800, size ); }
				var g = ctx.createLinearGradient( 0, y, 0, y + size );
				g.addColorStop( 0, '#FFFFFF' ); g.addColorStop( 1, c.crystal );
				ctx.fillStyle = g; ctx.textBaseline = 'top'; ctx.textAlign = rtl ? 'right' : 'left'; ctx.direction = 'ltr';
				ctx.fillText( sv, x, y );
				y += size * 1.12 + 20;
				f = fit( ctx, s.stat_label || s.title, cw, 260, 52, 32, 700, 1.3 );
				y = lines( ctx, f, x, y, '#FFFFFF', rtl ) + 28;
				if ( s.body ) {
					f = fit( ctx, s.body, cw, bottom - y, 34, 24, 400, 1.5 );
					lines( ctx, f, x, y, ink2, rtl );
				}
				break;

			case 'quote':
				ctx.font = font( 800, 300 );
				ctx.fillStyle = rgba( c.crystal, 0.25 );
				ctx.textBaseline = 'top'; ctx.textAlign = rtl ? 'right' : 'left';
				ctx.fillText( rtl ? '»' : '“', x, y - 40 );
				y += 210;
				f = fit( ctx, s.body || s.title, cw, 600, 64, 36, 700, 1.35 );
				y = lines( ctx, f, x, y, '#FFFFFF', rtl ) + 40;
				if ( s.kicker || s.title && s.body ) {
					ctx.fillStyle = c.crystal;
					ctx.fillRect( rtl ? x - 60 : x, y, 60, 5 );
					f = fit( ctx, s.kicker || s.title, cw, 100, 30, 22, 600, 1.4 );
					lines( ctx, f, x, y + 26, ink2, rtl );
				}
				break;

			case 'cta':
				y += 40;
				f = fit( ctx, s.title, cw, 330, 76, 44, 800, 1.2 );
				ctx.textAlign = 'center';
				y = linesCenter( ctx, f, y, '#FFFFFF', rtl ) + 26;
				if ( s.body ) {
					f = fit( ctx, s.body, cw - 40, 200, 36, 26, 400, 1.5 );
					y = linesCenter( ctx, f, y, rgba( c.crystal, 0.95 ), rtl ) + 50;
				}
				y = author( ctx, x, Math.max( y, 760 ), rtl, true, lang, true ) + 50;
				var pills = lang === 'fa' ? [ '➕ دنبال کنید', '♻️ بازنشر', '🔖 ذخیره' ] : [ '➕ Follow', '♻️ Repost', '🔖 Save' ];
				ctx.font = font( 700, 28 );
				var widths = pills.map( function ( p ) { return ctx.measureText( p ).width + 56; } );
				var total = widths.reduce( function ( a, b ) { return a + b; }, 0 ) + 24 * ( pills.length - 1 );
				var px = ( W - total ) / 2;
				if ( rtl ) { pills.reverse(); widths.reverse(); }
				pills.forEach( function ( p, k ) {
					ctx.fillStyle = k === ( rtl ? pills.length - 1 : 0 ) ? c.main : rgba( '#FFFFFF', 0.12 );
					rr( ctx, px, y, widths[ k ], 68, 34 ); ctx.fill();
					ctx.fillStyle = '#FFFFFF'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.direction = rtl ? 'rtl' : 'ltr';
					ctx.fillText( p, px + widths[ k ] / 2, y + 35 );
					px += widths[ k ] + 24;
				} );
				break;

			case 'compare':
				y = kickerPill( ctx, s.kicker, x, y, rtl, false ) + ( s.kicker ? 30 : 0 );
				f = fit( ctx, s.title, cw, 200, 60, 40, 800, 1.22 );
				y = lines( ctx, f, x, y, ink, rtl ) + 40;
				var colW = ( cw - 28 ) / 2, colH = Math.min( bottom - y, 140 + 120 * Math.max( ( s.left_items || [] ).length, ( s.right_items || [] ).length, 1 ) );
				var first = rtl ? W - PAD - colW : PAD, second = rtl ? PAD : PAD + colW + 28;
				column( ctx, first, y, colW, colH, s.left_title, s.left_items, false, rtl );
				column( ctx, second, y, colW, colH, s.right_title, s.right_items, true, rtl );
				break;

			case 'list':
			case 'steps':
				y = kickerPill( ctx, s.kicker, x, y, rtl, false ) + ( s.kicker ? 30 : 0 );
				f = fit( ctx, s.title, cw, 230, 62, 40, 800, 1.22 );
				y = lines( ctx, f, x, y, ink, rtl ) + 44;
				var items = ( s.items || [] ).filter( Boolean );
				if ( ! items.length && s.body ) { items = [ s.body ]; }
				var avail = bottom - y, per = avail / Math.max( 1, items.length );
				var isz = Math.min( 48, Math.max( 24, per / 2.6 ) );
				var gap = Math.min( 54, Math.max( 18, per * 0.22 ) );
				items.forEach( function ( it, k ) {
					var mark = 72;
					var tx = rtl ? x - mark : x + mark;
					var fi = fit( ctx, it, cw - mark, per - gap, isz, 22, 500, 1.38 );
					if ( s.type === 'steps' ) {
						var cx = rtl ? x - 28 : x + 28;
						var gg = ctx.createLinearGradient( cx - 28, y, cx + 28, y + 56 );
						gg.addColorStop( 0, c.main ); gg.addColorStop( 1, mix( c.main, c.crystal, 0.5 ) );
						ctx.fillStyle = gg; ctx.beginPath(); ctx.arc( cx, y + fi.size * 0.7, 28, 0, Math.PI * 2 ); ctx.fill();
						ctx.fillStyle = '#FFFFFF'; ctx.font = font( 800, 28 ); ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
						ctx.fillText( lang === 'fa' ? toFa( k + 1 ) : String( k + 1 ), cx, y + fi.size * 0.7 + 1 );
					} else {
						diamond( ctx, rtl ? x - 18 : x + 18, y + fi.size * 0.68, 15, c.main );
					}
					ctx.font = font( 500, fi.size );
					y = lines( ctx, fi, tx, y, ink, rtl ) + gap;
					if ( k < items.length - 1 ) {
						ctx.fillStyle = rgba( c.main, 0.12 );
						ctx.fillRect( rtl ? x - cw : x, y - gap / 2, cw, 2 );
					}
				} );
				break;

			default: // point
				var big = s.kicker && s.kicker.length <= 3;
				if ( big ) {
					ctx.font = font( 800, 150 );
					ctx.lineWidth = 3; ctx.strokeStyle = rgba( c.main, 0.45 );
					ctx.textAlign = rtl ? 'right' : 'left'; ctx.textBaseline = 'top'; ctx.direction = 'ltr';
					ctx.strokeText( lang === 'fa' ? toFa( s.kicker ) : s.kicker, x, y - 20 );
					y += 150;
				} else {
					y = kickerPill( ctx, s.kicker, x, y, rtl, false ) + ( s.kicker ? 34 : 0 );
				}
				f = fit( ctx, s.title, cw, 330, 72, 44, 800, 1.2 );
				y = lines( ctx, f, x, y, ink, rtl ) + 36;
				ctx.fillStyle = c.main;
				ctx.fillRect( rtl ? x - 80 : x, y, 80, 6 );
				y += 46;
				if ( s.body ) {
					f = fit( ctx, s.body, cw, bottom - y - ( s.items && s.items.length ? 200 : 0 ), 40, 26, 400, 1.55 );
					y = lines( ctx, f, x, y, ink2, rtl ) + 26;
				}
				( s.items || [] ).filter( Boolean ).slice( 0, 4 ).forEach( function ( it ) {
					var fi = fit( ctx, it, cw - 50, 120, 32, 22, 600, 1.4 );
					diamond( ctx, rtl ? x - 12 : x + 12, y + fi.size * 0.68, 10, c.main );
					y = lines( ctx, fi, rtl ? x - 44 : x + 44, y, ink, rtl ) + 14;
				} );
		}
		footer( ctx, dark, rtl, i, n, lang );
	}
	function linesCenter( ctx, f, y, color ) {
		ctx.fillStyle = color; ctx.textBaseline = 'top'; ctx.textAlign = 'center';
		f.lines.forEach( function ( l, i ) { ctx.fillText( l, W / 2, y + i * f.size * f.lh ); } );
		return y + f.lines.length * f.size * f.lh;
	}
	function column( ctx, x, y, w, h, title, items, hi, rtl ) {
		var c = C();
		ctx.fillStyle = hi ? rgba( c.main, 0.09 ) : 'rgba(27,21,48,0.045)';
		rr( ctx, x, y, w, h, 28 ); ctx.fill();
		if ( hi ) { ctx.strokeStyle = rgba( c.main, 0.5 ); ctx.lineWidth = 3; rr( ctx, x, y, w, h, 28 ); ctx.stroke(); }
		var ix = rtl ? x + w - 34 : x + 34, iw = w - 68, iy = y + 34;
		var tf = fit( ctx, title || '', iw, 110, 42, 26, 800, 1.25 );
		iy = lines( ctx, tf, ix, iy, hi ? c.main : c.ink2, rtl ) + 28;
		var list = ( items || [] ).filter( Boolean );
		var per = ( y + h - iy - 20 ) / Math.max( 1, list.length );
		list.forEach( function ( it ) {
			var fi = fit( ctx, it, iw - 40, per - 16, 36, 20, 500, 1.36 );
			ctx.font = font( 800, fi.size );
			ctx.fillStyle = hi ? c.main : '#9A93AE';
			ctx.textAlign = rtl ? 'right' : 'left'; ctx.textBaseline = 'top';
			ctx.fillText( hi ? '✓' : '✕', ix, iy );
			ctx.font = font( 500, fi.size );
			iy = lines( ctx, fi, rtl ? ix - 40 : ix + 40, iy, c.ink, rtl ) + 18;
		} );
	}

	function render( slide, i, n, lang, canvas ) {
		canvas = canvas || document.createElement( 'canvas' );
		canvas.width = W; canvas.height = H;
		var ctx = canvas.getContext( '2d' );
		ctx.clearRect( 0, 0, W, H );
		draw( ctx, slide, i, n, lang );
		return canvas;
	}
	function renderAll( slides, lang ) {
		return ready().then( function () {
			return slides.map( function ( s, i ) { return render( s, i, slides.length, lang ); } );
		} );
	}
	/* A canvas with a cross-origin photo cannot be exported: drop the photo and redraw. */
	function safe( canvases, slides, lang ) {
		try {
			canvases[ 0 ].toDataURL( 'image/jpeg', 0.5 );
			return canvases;
		} catch ( e ) {
			photo = null;
			return slides.map( function ( s, i ) { return render( s, i, slides.length, lang ); } );
		}
	}

	// ---- Bytes ----
	function b64bytes( dataUrl ) {
		var bin = atob( dataUrl.split( ',' )[ 1 ] ), out = new Uint8Array( bin.length );
		for ( var i = 0; i < bin.length; i++ ) { out[ i ] = bin.charCodeAt( i ); }
		return out;
	}
	function utf8( s ) { return new TextEncoder().encode( s ); }
	function latin( s ) {
		var out = new Uint8Array( s.length );
		for ( var i = 0; i < s.length; i++ ) { out[ i ] = s.charCodeAt( i ) & 255; }
		return out;
	}

	// ---- PDF (one JPEG per page) ----
	function pdf( canvases, title ) {
		var parts = [], offsets = [], pos = 0;
		function push( b ) { parts.push( b ); pos += b.length; }
		function obj( n, body ) { offsets[ n ] = pos; push( latin( n + ' 0 obj\n' ) ); push( body ); push( latin( '\nendobj\n' ) ); }
		var pw = W * 0.75, ph = H * 0.75; // px → pt.
		var n = canvases.length, kids = [];
		for ( var i = 0; i < n; i++ ) { kids.push( ( 3 + i * 3 ) + ' 0 R' ); }
		push( latin( '%PDF-1.4\n%\xE2\xE3\xCF\xD3\n' ) );
		obj( 1, latin( '<< /Type /Catalog /Pages 2 0 R >>' ) );
		obj( 2, latin( '<< /Type /Pages /Kids [' + kids.join( ' ' ) + '] /Count ' + n + ' >>' ) );
		canvases.forEach( function ( cv, k ) {
			var p = 3 + k * 3, img = b64bytes( cv.toDataURL( 'image/jpeg', 0.92 ) );
			var content = 'q ' + pw + ' 0 0 ' + ph + ' 0 0 cm /Im0 Do Q';
			obj( p, latin( '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + pw + ' ' + ph + '] /Resources << /XObject << /Im0 ' + ( p + 2 ) + ' 0 R >> >> /Contents ' + ( p + 1 ) + ' 0 R >>' ) );
			obj( p + 1, latin( '<< /Length ' + content.length + ' >>\nstream\n' + content + '\nendstream' ) );
			offsets[ p + 2 ] = pos;
			push( latin( ( p + 2 ) + ' 0 obj\n<< /Type /XObject /Subtype /Image /Width ' + cv.width + ' /Height ' + cv.height + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + img.length + ' >>\nstream\n' ) );
			push( img );
			push( latin( '\nendstream\nendobj\n' ) );
		} );
		var info = 3 + n * 3;
		obj( info, latin( '<< /Title (' + pdfText( title ) + ') /Producer (Badban by Ronagh) >>' ) );
		var xref = pos, size = info + 1, x = 'xref\n0 ' + size + '\n0000000000 65535 f \n';
		for ( var o = 1; o < size; o++ ) { x += String( offsets[ o ] ).padStart( 10, '0' ) + ' 00000 n \n'; }
		push( latin( x + 'trailer\n<< /Size ' + size + ' /Root 1 0 R /Info ' + info + ' 0 R >>\nstartxref\n' + xref + '\n%%EOF\n' ) );
		return new Blob( parts, { type: 'application/pdf' } );
	}
	/* PDF text string: ASCII as is, anything else as UTF-16BE hex. */
	function pdfText( s ) {
		s = String( s || 'Badban' );
		if ( /^[\x20-\x7E]*$/.test( s ) ) { return s.replace( /[()\\]/g, '\\$&' ); }
		return ''; // Non-ASCII titles are left empty (the file name carries the title).
	}

	// ---- ZIP (stored) ----
	var CRC = ( function () {
		var t = new Uint32Array( 256 );
		for ( var n = 0; n < 256; n++ ) {
			var c = n;
			for ( var k = 0; k < 8; k++ ) { c = c & 1 ? 0xEDB88320 ^ ( c >>> 1 ) : c >>> 1; }
			t[ n ] = c >>> 0;
		}
		return t;
	} )();
	function crc32( b ) {
		var c = 0xFFFFFFFF;
		for ( var i = 0; i < b.length; i++ ) { c = CRC[ ( c ^ b[ i ] ) & 255 ] ^ ( c >>> 8 ); }
		return ( c ^ 0xFFFFFFFF ) >>> 0;
	}
	function zip( files ) {
		var parts = [], central = [], pos = 0;
		function u16( v ) { return [ v & 255, ( v >> 8 ) & 255 ]; }
		function u32( v ) { return [ v & 255, ( v >> 8 ) & 255, ( v >> 16 ) & 255, ( v >>> 24 ) & 255 ]; }
		files.forEach( function ( f ) {
			var name = utf8( f.name ), data = typeof f.data === 'string' ? utf8( f.data ) : f.data, crc = crc32( data );
			var head = [].concat( u32( 0x04034b50 ), u16( 20 ), u16( 0x0800 ), u16( 0 ), u16( 0 ), u16( 0x21 ), u32( crc ), u32( data.length ), u32( data.length ), u16( name.length ), u16( 0 ) );
			central.push( { name: name, crc: crc, size: data.length, off: pos } );
			parts.push( new Uint8Array( head ), name, data );
			pos += head.length + name.length + data.length;
		} );
		var cdStart = pos, cdSize = 0;
		central.forEach( function ( c ) {
			var h = [].concat( u32( 0x02014b50 ), u16( 20 ), u16( 20 ), u16( 0x0800 ), u16( 0 ), u16( 0 ), u16( 0x21 ), u32( c.crc ), u32( c.size ), u32( c.size ), u16( c.name.length ), u16( 0 ), u16( 0 ), u16( 0 ), u16( 0 ), u32( 0 ), u32( c.off ) );
			parts.push( new Uint8Array( h ), c.name );
			cdSize += h.length + c.name.length;
		} );
		parts.push( new Uint8Array( [].concat( u32( 0x06054b50 ), u16( 0 ), u16( 0 ), u16( central.length ), u16( central.length ), u32( cdSize ), u32( cdStart ), u16( 0 ) ) ) );
		return parts;
	}

	// ---- PPTX (one full-bleed image per slide) ----
	function esc( s ) { return String( s || '' ).replace( /[<>&"]/g, function ( c ) { return { '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;' }[ c ]; } ); }
	function pptx( canvases, title ) {
		var cx = W * 9525, cy = H * 9525, n = canvases.length;
		var NS = 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"';
		var X = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n';
		var REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';
		var grp = '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>';
		var files = [];
		var ct = X + '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="jpeg" ContentType="image/jpeg"/>'
			+ '<Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>'
			+ '<Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/>'
			+ '<Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/>'
			+ '<Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>'
			+ '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
			+ '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>';
		for ( var i = 1; i <= n; i++ ) { ct += '<Override PartName="/ppt/slides/slide' + i + '.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>'; }
		files.push( { name: '[Content_Types].xml', data: ct + '</Types>' } );
		files.push( { name: '_rels/.rels', data: X + '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="' + REL + 'officeDocument" Target="ppt/presentation.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="' + REL + 'extended-properties" Target="docProps/app.xml"/></Relationships>' } );
		files.push( { name: 'docProps/core.xml', data: X + '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>' + esc( title ) + '</dc:title><dc:creator>' + esc( conf.name ) + '</dc:creator></cp:coreProperties>' } );
		files.push( { name: 'docProps/app.xml', data: X + '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Badban by Ronagh</Application><Slides>' + n + '</Slides></Properties>' } );
		var ids = '', rels = '<Relationship Id="rId1" Type="' + REL + 'slideMaster" Target="slideMasters/slideMaster1.xml"/><Relationship Id="rId2" Type="' + REL + 'theme" Target="theme/theme1.xml"/>';
		for ( i = 1; i <= n; i++ ) {
			ids += '<p:sldId id="' + ( 255 + i ) + '" r:id="rId' + ( i + 2 ) + '"/>';
			rels += '<Relationship Id="rId' + ( i + 2 ) + '" Type="' + REL + 'slide" Target="slides/slide' + i + '.xml"/>';
		}
		files.push( { name: 'ppt/presentation.xml', data: X + '<p:presentation ' + NS + ' saveSubsetFonts="1"><p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId1"/></p:sldMasterIdLst><p:sldIdLst>' + ids + '</p:sldIdLst><p:sldSz cx="' + cx + '" cy="' + cy + '"/><p:notesSz cx="6858000" cy="9144000"/></p:presentation>' } );
		files.push( { name: 'ppt/_rels/presentation.xml.rels', data: X + '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' + rels + '</Relationships>' } );
		files.push( { name: 'ppt/slideMasters/slideMaster1.xml', data: X + '<p:sldMaster ' + NS + '><p:cSld><p:bg><p:bgRef idx="1001"><a:schemeClr val="bg1"/></p:bgRef></p:bg><p:spTree>' + grp + '</p:spTree></p:cSld><p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/><p:sldLayoutIdLst><p:sldLayoutId id="2147483649" r:id="rId1"/></p:sldLayoutIdLst><p:txStyles><p:titleStyle/><p:bodyStyle/><p:otherStyle/></p:txStyles></p:sldMaster>' } );
		files.push( { name: 'ppt/slideMasters/_rels/slideMaster1.xml.rels', data: X + '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="' + REL + 'slideLayout" Target="../slideLayouts/slideLayout1.xml"/><Relationship Id="rId2" Type="' + REL + 'theme" Target="../theme/theme1.xml"/></Relationships>' } );
		files.push( { name: 'ppt/slideLayouts/slideLayout1.xml', data: X + '<p:sldLayout ' + NS + ' type="blank" preserve="1"><p:cSld name="Blank"><p:spTree>' + grp + '</p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sldLayout>' } );
		files.push( { name: 'ppt/slideLayouts/_rels/slideLayout1.xml.rels', data: X + '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="' + REL + 'slideMaster" Target="../slideMasters/slideMaster1.xml"/></Relationships>' } );
		files.push( { name: 'ppt/theme/theme1.xml', data: X + theme() } );
		canvases.forEach( function ( cv, k ) {
			var i1 = k + 1;
			files.push( { name: 'ppt/slides/slide' + i1 + '.xml', data: X + '<p:sld ' + NS + '><p:cSld><p:spTree>' + grp + '<p:pic><p:nvPicPr><p:cNvPr id="2" name="Slide ' + i1 + '"/><p:cNvPicPr><a:picLocks noChangeAspect="1"/></p:cNvPicPr><p:nvPr/></p:nvPicPr><p:blipFill><a:blip r:embed="rId2"/><a:stretch><a:fillRect/></a:stretch></p:blipFill><p:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' + cx + '" cy="' + cy + '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr></p:pic></p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sld>' } );
			files.push( { name: 'ppt/slides/_rels/slide' + i1 + '.xml.rels', data: X + '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="' + REL + 'slideLayout" Target="../slideLayouts/slideLayout1.xml"/><Relationship Id="rId2" Type="' + REL + 'image" Target="../media/image' + i1 + '.jpeg"/></Relationships>' } );
			files.push( { name: 'ppt/media/image' + i1 + '.jpeg', data: b64bytes( cv.toDataURL( 'image/jpeg', 0.92 ) ) } );
		} );
		return new Blob( zip( files ), { type: 'application/vnd.openxmlformats-officedocument.presentationml.presentation' } );
	}
	function theme() {
		var c = C();
		var h = function ( x ) { return String( x ).replace( '#', '' ).toUpperCase(); };
		var fill = '<a:solidFill><a:schemeClr val="phClr"/></a:solidFill>';
		var ln = '<a:ln w="9525"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln>';
		return '<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Badban"><a:themeElements>'
			+ '<a:clrScheme name="Badban"><a:dk1><a:srgbClr val="1B1530"/></a:dk1><a:lt1><a:srgbClr val="FFFFFF"/></a:lt1><a:dk2><a:srgbClr val="' + h( c.dark ) + '"/></a:dk2><a:lt2><a:srgbClr val="FBF9FF"/></a:lt2>'
			+ '<a:accent1><a:srgbClr val="' + h( c.main ) + '"/></a:accent1><a:accent2><a:srgbClr val="' + h( c.crystal ) + '"/></a:accent2><a:accent3><a:srgbClr val="0D9488"/></a:accent3><a:accent4><a:srgbClr val="DB2777"/></a:accent4><a:accent5><a:srgbClr val="F59E0B"/></a:accent5><a:accent6><a:srgbClr val="4F46E5"/></a:accent6><a:hlink><a:srgbClr val="' + h( c.main ) + '"/></a:hlink><a:folHlink><a:srgbClr val="6D28D9"/></a:folHlink></a:clrScheme>'
			+ '<a:fontScheme name="Badban"><a:majorFont><a:latin typeface="Vazirmatn"/><a:ea typeface=""/><a:cs typeface="Vazirmatn"/></a:majorFont><a:minorFont><a:latin typeface="Vazirmatn"/><a:ea typeface=""/><a:cs typeface="Vazirmatn"/></a:minorFont></a:fontScheme>'
			+ '<a:fmtScheme name="Badban"><a:fillStyleLst>' + fill + fill + fill + '</a:fillStyleLst><a:lnStyleLst>' + ln + ln + ln + '</a:lnStyleLst><a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst><a:bgFillStyleLst>' + fill + fill + fill + '</a:bgFillStyleLst></a:fmtScheme>'
			+ '</a:themeElements></a:theme>';
	}

	function png( canvas ) {
		return new Promise( function ( res ) { canvas.toBlob( res, 'image/png' ); } );
	}

	window.BadbanSlides = {
		W: W, H: H,
		ready: ready,
		render: render,
		renderAll: renderAll,
		safe: safe,
		pdf: pdf,
		pptx: pptx,
		png: png
	};
} )();
