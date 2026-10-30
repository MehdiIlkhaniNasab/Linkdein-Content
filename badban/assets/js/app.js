/* Badban (بادبان) — app behaviour. No build step, no dependencies. */
( function () {
	'use strict';

	var M = window.BDB || {};
	var S = function () { return window.BadbanSlides; };
	var $ = function ( s, el ) { return ( el || document ).querySelector( s ); };
	var $$ = function ( s, el ) { return Array.prototype.slice.call( ( el || document ).querySelectorAll( s ) ); };
	var fa = function ( n ) { return String( n ).replace( /\d/g, function ( d ) { return '۰۱۲۳۴۵۶۷۸۹'[ d ]; } ); };
	var esc = function ( s ) { return String( s == null ? '' : s ).replace( /[&<>"]/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ]; } ); };

	function toast( msg, bad ) {
		var t = $( '#bdb-toast' );
		if ( ! t ) { return; }
		t.textContent = msg;
		t.className = 'bdb-toast is-show' + ( bad ? ' is-bad' : '' );
		clearTimeout( t._h );
		t._h = setTimeout( function () { t.className = 'bdb-toast'; }, bad ? 8000 : 3500 );
	}

	function api( path, body, method ) {
		var isForm = body instanceof FormData;
		var opt = { method: method || 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': M.nonce } };
		if ( opt.method !== 'GET' ) {
			if ( isForm ) {
				opt.body = body;
			} else {
				opt.headers[ 'Content-Type' ] = 'application/json';
				opt.body = JSON.stringify( body || {} );
			}
		}
		return fetch( M.rest + path, opt ).then( function ( r ) {
			return r.json().catch( function () { return { message: 'پاسخ نامعتبر از سرور (HTTP ' + r.status + ')' }; } ).then( function ( d ) {
				if ( ! r.ok ) { throw new Error( d && d.message ? d.message : 'HTTP ' + r.status ); }
				return d;
			} );
		} );
	}
	function busy( btn, on ) {
		if ( ! btn ) { return; }
		btn.disabled = on;
		btn.classList.toggle( 'is-busy', on );
	}
	function go( url ) { location.href = url; }
	function download( blob, name ) {
		var a = document.createElement( 'a' );
		a.href = URL.createObjectURL( blob );
		a.download = name;
		document.body.appendChild( a );
		a.click();
		setTimeout( function () { URL.revokeObjectURL( a.href ); a.remove(); }, 1500 );
	}
	function slug( s ) { return String( s || 'badban' ).replace( /[^\w؀-ۿ]+/g, '-' ).replace( /^-+|-+$/g, '' ).slice( 0, 60 ) || 'badban'; }

	// ---- Navigation, menus, copy, conditional fields ----
	document.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( '[data-bdb-open-nav]' ) ) { document.body.classList.add( 'nav-open' ); }
		if ( e.target.closest( '[data-bdb-close-nav]' ) ) { document.body.classList.remove( 'nav-open' ); }
		var c = e.target.closest( '[data-bdb-copy]' );
		if ( c && navigator.clipboard ) {
			navigator.clipboard.writeText( c.textContent.trim() ).then( function () { toast( 'کپی شد' ); } );
		}
		var mt = e.target.closest( '[data-bdb-menu-toggle]' );
		$$( '.bdb-menu-pop' ).forEach( function ( p ) {
			if ( ! mt || p !== mt.nextElementSibling ) { p.hidden = true; }
		} );
		if ( mt ) { mt.nextElementSibling.hidden = ! mt.nextElementSibling.hidden; }
	} );
	function syncToggles() {
		$$( '[data-bdb-when]' ).forEach( function ( el ) {
			var parts = el.getAttribute( 'data-bdb-when' ).split( '-' );
			var r = $( 'input[data-bdb-toggle="' + parts[ 0 ] + '"]:checked' );
			el.hidden = !! r && r.value !== parts.slice( 1 ).join( '-' );
		} );
	}
	document.addEventListener( 'change', function ( e ) {
		if ( e.target.matches( '[data-bdb-toggle]' ) ) { syncToggles(); }
	} );
	syncToggles();

	// ---- Post rows: approve / unschedule ----
	document.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '[data-bdb-approve]' );
		if ( b ) {
			busy( b, true );
			api( 'approve/' + b.getAttribute( 'data-bdb-approve' ), {} ).then( function ( d ) {
				toast( d.msg || 'زمان‌بندی شد' );
				setTimeout( function () { location.reload(); }, 700 );
			} ).catch( function ( err ) { busy( b, false ); toast( err.message, true ); } );
		}
		b = e.target.closest( '[data-bdb-unschedule]' );
		if ( b ) {
			busy( b, true );
			api( 'unschedule/' + b.getAttribute( 'data-bdb-unschedule' ), {} ).then( function () { location.reload(); } ).catch( function ( err ) { busy( b, false ); toast( err.message, true ); } );
		}
		b = e.target.closest( '[data-bdb-approve-all]' );
		if ( b ) {
			busy( b, true );
			api( 'approve-all', {} ).then( function ( d ) { toast( d.msg ); setTimeout( function () { location.reload(); }, 700 ); } ).catch( function ( err ) { busy( b, false ); toast( err.message, true ); } );
		}
	} );

	// ---- Ideas ----
	document.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '[data-bdb-harvest]' );
		if ( b ) {
			busy( b, true );
			api( 'ideas/harvest', {} ).then( function ( d ) { toast( d.msg ); setTimeout( function () { location.reload(); }, 900 ); } ).catch( function ( err ) { busy( b, false ); toast( err.message, true ); } );
		}
		b = e.target.closest( '[data-bdb-write-idea]' );
		if ( b ) {
			busy( b, true );
			toast( 'در حال نوشتن… (۳۰ تا ۹۰ ثانیه)' );
			api( 'write', { idea: +b.getAttribute( 'data-bdb-write-idea' ), lang: b.getAttribute( 'data-lang' ) || 'en', when: 'next' } )
				.then( function ( d ) { go( d.url ); } )
				.catch( function ( err ) { busy( b, false ); toast( err.message, true ); } );
		}
		b = e.target.closest( '[data-bdb-idea-status]' );
		if ( b ) {
			var card = b.closest( '[data-bdb-idea]' );
			api( 'idea/' + card.getAttribute( 'data-bdb-idea' ), { status: b.getAttribute( 'data-bdb-idea-status' ) } ).then( function () {
				card.style.opacity = '.3';
				setTimeout( function () { card.remove(); }, 250 );
			} ).catch( function ( err ) { toast( err.message, true ); } );
		}
	} );
	var aiForm = $( '[data-bdb-ideas-ai]' );
	if ( aiForm ) {
		aiForm.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var btn = $( 'button', aiForm );
			busy( btn, true );
			api( 'ideas/ai', { pillar: aiForm.pillar.value, count: +aiForm.count.value } ).then( function ( d ) { toast( d.msg ); setTimeout( function () { location.reload(); }, 900 ); } ).catch( function ( err ) { busy( btn, false ); toast( err.message, true ); } );
		} );
	}
	var addForm = $( '[data-bdb-idea-add]' );
	if ( addForm ) {
		addForm.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			api( 'idea', { title: addForm.title.value, angle: addForm.angle.value, pillar: addForm.pillar.value, framework: addForm.framework.value } ).then( function ( d ) { toast( d.msg ); setTimeout( function () { location.reload(); }, 600 ); } ).catch( function ( err ) { toast( err.message, true ); } );
		} );
	}

	// ---- Compose ----
	var compose = $( '[data-bdb-compose]' );
	if ( compose ) {
		compose.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var f = compose, src = $( 'input[name=source]:checked', f ).value;
			var body = {
				idea: src === 'idea' ? +f.idea.value || 0 : 0,
				topic: src === 'topic' ? f.topic.value : '',
				angle: src === 'topic' ? f.angle.value : '',
				lang: $( 'input[name=lang]:checked', f ).value,
				format: $( 'input[name=format]:checked', f ).value,
				pillar: f.pillar.value,
				framework: f.framework.value,
				hook_type: f.hook_type.value,
				cta_type: f.cta_type.value,
				slides: +f.slides.value,
				extra: f.extra.value,
				when: f.when.checked ? 'next' : ''
			};
			if ( ! body.idea && ! body.topic.trim() ) { toast( 'یک ایده انتخاب کنید یا موضوع را بنویسید.', true ); return; }
			var btn = $( 'button[type=submit]', f ), st = $( '[data-bdb-compose-status]', f );
			busy( btn, true );
			st.textContent = 'در حال نوشتن با هوش مصنوعی… (معمولاً ۳۰ تا ۹۰ ثانیه)';
			api( 'write', body ).then( function ( d ) { go( d.url ); } ).catch( function ( err ) { busy( btn, false ); st.textContent = ''; toast( err.message, true ); } );
		} );
	}

	// ---- Rendering + upload of a post's file (studio and plan runner) ----
	function buildAsset( app ) {
		var p = app.post;
		if ( ! S() ) { return Promise.reject( new Error( 'موتور اسلاید بارگذاری نشده است.' ) ); }
		if ( ! p.slides || ! p.slides.length ) { return Promise.reject( new Error( 'اسلایدی برای ساخت فایل وجود ندارد.' ) ); }
		var slides = p.format === 'image' ? p.slides.slice( 0, 1 ) : p.slides;
		return S().ready( app.render ).then( function () {
			return S().renderAll( slides, p.lang );
		} ).then( function ( canvases ) {
			canvases = S().safe( canvases, slides, p.lang );
			return p.format === 'image' ? S().png( canvases[ 0 ] ) : S().pdf( canvases, p.meta.doc_title || p.title );
		} ).then( function ( blob ) {
			var fd = new FormData();
			fd.append( 'file', blob, p.format === 'image' ? 'card.png' : 'carousel.pdf' );
			fd.append( 'hash', p.hash );
			fd.append( 'pages', String( slides.length ) );
			return api( 'asset/' + p.id, fd );
		} );
	}

	// ---- Plan runner ----
	var plan = $( '[data-bdb-plan]' );
	document.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '[data-bdb-plan-start]' );
		if ( ! b || ! plan ) { return; }
		$$( '[data-bdb-plan-start]' ).forEach( function ( x ) { busy( x, true ); } );
		plan.hidden = false;
		var bar = $( '.bdb-progress i', plan ), label = $( '[data-bdb-plan-label]', plan ), log = $( '[data-bdb-plan-log]', plan );
		var total = 0, done = 0, fails = 0;
		function line( msg, cls ) {
			var li = document.createElement( 'li' );
			li.className = cls || '';
			li.textContent = msg;
			log.appendChild( li );
			log.scrollTop = log.scrollHeight;
		}
		function paint() { bar.style.width = ( total ? Math.round( 100 * done / total ) : 5 ) + '%'; }
		function finish() {
			label.textContent = 'تمام شد؛ پست‌ها در «آماده تأیید» هستند. در حال بارگذاری…';
			bar.style.width = '100%';
			setTimeout( function () { location.href = M.urls.ready; }, 1500 );
		}
		function tick() {
			api( 'plan-tick', {} ).then( function ( d ) {
				fails = 0;
				done++;
				paint();
				line( d.msg, d.error ? 'is-error' : '' );
				var next = function () {
					if ( d.done ) { finish(); return; }
					label.textContent = 'نوشتن پست ' + fa( done + 1 ) + ' از ' + fa( total ) + '…';
					tick();
				};
				if ( d.post && d.needs_render ) {
					label.textContent = 'ساخت فایل اسلایدها…';
					api( 'post/' + d.post, null, 'GET' ).then( buildAsset ).then( function () { line( '  ← فایل اسلایدها ساخته شد' ); next(); } ).catch( function ( err ) { line( '  ← ساخت فایل: ' + err.message, 'is-error' ); next(); } );
				} else {
					next();
				}
			} ).catch( function ( err ) {
				if ( ++fails <= 3 ) { setTimeout( tick, 3000 * fails ); return; }
				label.textContent = 'خطا: ' + err.message;
				$$( '[data-bdb-plan-start]' ).forEach( function ( x ) { busy( x, false ); } );
			} );
		}
		label.textContent = 'ساخت برنامه…';
		paint();
		api( 'plan', { weeks: +b.getAttribute( 'data-bdb-plan-start' ) } ).then( function ( d ) {
			total = d.jobs.length;
			line( d.msg );
			if ( ! total ) {
				label.textContent = d.msg;
				$$( '[data-bdb-plan-start]' ).forEach( function ( x ) { busy( x, false ); } );
				return;
			}
			d.jobs.forEach( function ( j ) { line( '• ' + ( j.lang === 'fa' ? 'فارسی' : 'English' ) + ': ' + ( j.title || 'ایده تازه از هوش مصنوعی' ), 'is-muted' ); } );
			label.textContent = 'نوشتن پست ۱ از ' + fa( total ) + '…';
			tick();
		} ).catch( function ( err ) {
			label.textContent = 'خطا: ' + err.message;
			$$( '[data-bdb-plan-start]' ).forEach( function ( x ) { busy( x, false ); } );
		} );
	} );

	// ---- Studio ----
	var studio = $( '[data-bdb-studio]' );
	if ( studio ) { initStudio( studio ); }

	function initStudio( root ) {
		var app = JSON.parse( root.getAttribute( 'data-bdb-studio' ) );
		var locked = root.getAttribute( 'data-locked' ) === '1';
		var cur = 0, timer = null, dirty = false, saving = null, expanded = false;
		var f = function ( n ) { return $( '[name="' + n + '"]', root ); };
		var TYPES = { cover: 'جلد', point: 'نکته', list: 'فهرست', steps: 'مراحل', stat: 'عدد', compare: 'مقایسه', quote: 'نقل‌قول', cta: 'دعوت به اقدام' };
		var FIELDS = {
			cover: [ 'kicker', 'title', 'body' ],
			point: [ 'kicker', 'title', 'body', 'items' ],
			list: [ 'kicker', 'title', 'items' ],
			steps: [ 'kicker', 'title', 'items' ],
			stat: [ 'kicker', 'stat_value', 'stat_label', 'body' ],
			compare: [ 'kicker', 'title', 'left_title', 'left_items', 'right_title', 'right_items' ],
			quote: [ 'body', 'kicker' ],
			cta: [ 'title', 'body' ]
		};
		var LABELS = { kicker: 'برچسب بالا / شماره', title: 'تیتر', body: 'متن', items: 'موارد (هر خط یکی)', stat_value: 'عدد', stat_label: 'توضیح عدد', left_title: 'ستون اول (قبل / افسانه)', left_items: 'موارد ستون اول', right_title: 'ستون دوم (بعد / واقعیت)', right_items: 'موارد ستون دوم' };

		function post() { return app.post; }
		function commentary() {
			var p = post();
			return ( p.body || '' ).trim() + ( ( p.hashtags || '' ).trim() ? '\n\n' + p.hashtags.trim() : '' );
		}

		// Collect the editor into the post object.
		function collect() {
			var p = post();
			[ 'title' ].forEach( function ( k ) { var el = $( '.bdb-title-input', root ); if ( el ) { p.title = el.value; } } );
			[ 'body', 'hashtags', 'first_comment', 'lang', 'format', 'pillar', 'cta_type', 'doc_title' ].forEach( function ( k ) {
				var el = f( k );
				if ( el ) { if ( k === 'doc_title' ) { p.meta.doc_title = el.value; } else { p[ k ] = el.value; } }
			} );
			if ( f( 'poll_question' ) ) {
				p.meta.poll = {
					question: f( 'poll_question' ).value,
					options: $$( '[name=poll_option]', root ).map( function ( i ) { return i.value; } ).filter( function ( v ) { return v.trim(); } ),
					duration: f( 'poll_duration' ).value
				};
			}
		}
		function payload() {
			var p = post();
			var d = { title: p.title, body: p.body, hashtags: p.hashtags, first_comment: p.first_comment, lang: p.lang, format: p.format, pillar: p.pillar, cta_type: p.cta_type, slides: p.slides, poll: p.meta.poll || {}, doc_title: p.meta.doc_title || '' };
			var w = f( 'when' );
			if ( w ) { d.when = w.value; }
			return d;
		}
		function save( quiet ) {
			if ( locked ) { return Promise.resolve( app ); }
			collect();
			clearTimeout( timer );
			var st = $( '[data-bdb-save-state]', root );
			st.textContent = 'در حال ذخیره…';
			saving = api( 'save/' + post().id, payload() ).then( function ( d ) {
				dirty = false;
				app.post = d.post; app.quality = d.quality;
				st.textContent = 'ذخیره شد';
				refreshMeta();
				if ( ! quiet ) { toast( 'ذخیره شد' ); }
				return app;
			} ).catch( function ( err ) { st.textContent = 'ذخیره نشد'; toast( err.message, true ); throw err; } );
			return saving;
		}
		function touch() {
			if ( locked ) { return; }
			dirty = true;
			$( '[data-bdb-save-state]', root ).textContent = 'تغییرات ذخیره نشده';
			clearTimeout( timer );
			timer = setTimeout( function () { save( true ).catch( function () {} ); }, 2500 );
		}
		window.addEventListener( 'beforeunload', function ( e ) { if ( dirty ) { e.preventDefault(); e.returnValue = ''; } } );

		function refreshMeta() {
			var p = post();
			var chip = $( '[data-bdb-status-chip]', root );
			if ( chip ) { chip.textContent = { draft: 'پیش‌نویس', ready: 'آماده انتشار', scheduled: 'زمان‌بندی‌شده', publishing: 'در حال انتشار', published: 'منتشر شد', failed: 'ناموفق' }[ p.status ] || p.status; }
			var wl = $( '[data-bdb-when-label]', root );
			if ( wl ) { wl.textContent = p.when_label || ''; }
			var w = f( 'when' );
			if ( w && p.when_local && document.activeElement !== w ) { w.value = p.when_local; }
			renderQuality();
		}

		// Panels by format.
		function formatPanels() {
			var fmt = post().format;
			$$( '[data-bdb-format-panel]', root ).forEach( function ( el ) {
				el.hidden = el.getAttribute( 'data-bdb-format-panel' ).split( ' ' ).indexOf( fmt ) < 0;
			} );
			if ( ( fmt === 'carousel' || fmt === 'image' ) && ! post().slides.length ) { seedSlides(); }
		}
		function blank( type ) { return { type: type, kicker: '', title: '', body: '', items: [], stat_value: '', stat_label: '', left_title: '', left_items: [], right_title: '', right_items: [] }; }
		function seedSlides() {
			var p = post(), first = ( p.body || '' ).split( '\n' )[ 0 ];
			var a = blank( 'cover' ); a.title = first || p.title;
			if ( p.format === 'image' ) { a.type = 'quote'; a.body = first; a.title = ''; p.slides = [ a ]; return; }
			var z = blank( 'cta' ); z.title = p.lang === 'fa' ? 'مفید بود؟' : 'Found this useful?';
			z.body = p.lang === 'fa' ? 'برای سئوی کاربردی دنبال کنید و برای همکارتان بفرستید.' : 'Follow for practical SEO strategy and share it with your team.';
			p.slides = [ a, blank( 'point' ), z ];
			drawSlides();
		}

		// Slides editor.
		var box = $( '[data-bdb-slides]', root );
		function drawSlides() {
			if ( ! box ) { return; }
			var p = post();
			var cnt = $( '[data-bdb-slide-count]', root );
			if ( cnt ) { cnt.textContent = fa( p.slides.length ) + ' اسلاید'; }
			box.innerHTML = p.slides.map( function ( s, i ) {
				var fields = ( FIELDS[ s.type ] || FIELDS.point ).map( function ( k ) {
					var isList = /items$/.test( k ), v = isList ? ( s[ k ] || [] ).join( '\n' ) : ( s[ k ] || '' );
					var long = isList || k === 'body';
					return '<label class="' + ( long ? 'is-wide' : '' ) + '">' + LABELS[ k ] + ( long
						? '<textarea data-k="' + k + '" rows="' + ( isList ? 4 : 2 ) + '" dir="auto"' + ( locked ? ' disabled' : '' ) + '>' + esc( v ) + '</textarea>'
						: '<input type="text" data-k="' + k + '" dir="auto" value="' + esc( v ) + '"' + ( locked ? ' disabled' : '' ) + '>' ) + '</label>';
				} ).join( '' );
				var types = Object.keys( TYPES ).map( function ( t ) { return '<option value="' + t + '"' + ( t === s.type ? ' selected' : '' ) + '>' + TYPES[ t ] + '</option>'; } ).join( '' );
				return '<div class="bdb-slide-card' + ( i === cur ? ' is-current' : '' ) + '" data-i="' + i + '">'
					+ '<div class="bdb-slide-card-h"><b>' + fa( i + 1 ) + '</b><select data-type' + ( locked ? ' disabled' : '' ) + '>' + types + '</select><em dir="auto">' + esc( s.title || s.stat_value || s.body || '' ) + '</em>'
					+ ( locked ? '' : '<span class="bdb-slide-acts"><button type="button" class="bdb-icon-btn" data-move="-1" title="بالا">▲</button><button type="button" class="bdb-icon-btn" data-move="1" title="پایین">▼</button><button type="button" class="bdb-icon-btn is-del" data-del title="حذف">✕</button></span>' )
					+ '</div><div class="bdb-slide-fields">' + fields + '</div></div>';
			} ).join( '' );
		}
		if ( box ) {
			box.addEventListener( 'input', function ( e ) {
				var card = e.target.closest( '[data-i]' ), k = e.target.getAttribute( 'data-k' );
				if ( ! card || ! k ) { return; }
				var s = post().slides[ +card.getAttribute( 'data-i' ) ];
				s[ k ] = /items$/.test( k ) ? e.target.value.split( '\n' ).map( function ( x ) { return x.trim(); } ).filter( Boolean ) : e.target.value;
				cur = +card.getAttribute( 'data-i' );
				previewSoon();
				touch();
			} );
			box.addEventListener( 'change', function ( e ) {
				if ( ! e.target.matches( '[data-type]' ) ) { return; }
				var i = +e.target.closest( '[data-i]' ).getAttribute( 'data-i' );
				post().slides[ i ].type = e.target.value;
				cur = i;
				drawSlides(); preview(); touch();
			} );
			box.addEventListener( 'click', function ( e ) {
				var card = e.target.closest( '[data-i]' );
				if ( ! card ) { return; }
				var i = +card.getAttribute( 'data-i' ), sl = post().slides;
				var mv = e.target.closest( '[data-move]' );
				if ( mv ) {
					var j = i + +mv.getAttribute( 'data-move' );
					if ( j >= 0 && j < sl.length ) { var t = sl[ i ]; sl[ i ] = sl[ j ]; sl[ j ] = t; cur = j; drawSlides(); preview(); touch(); }
					return;
				}
				if ( e.target.closest( '[data-del]' ) ) {
					if ( sl.length > 1 ) { sl.splice( i, 1 ); cur = Math.max( 0, Math.min( cur, sl.length - 1 ) ); drawSlides(); preview(); touch(); }
					return;
				}
				if ( cur !== i ) {
					cur = i;
					$$( '.bdb-slide-card', box ).forEach( function ( c ) { c.classList.toggle( 'is-current', +c.getAttribute( 'data-i' ) === i ); } );
					preview();
				}
			} );
		}
		var addBtn = $( '[data-bdb-slide-add]', root );
		if ( addBtn ) {
			addBtn.addEventListener( 'click', function () {
				var sl = post().slides, at = Math.max( 0, sl.length - 1 );
				sl.splice( sl.length && sl[ sl.length - 1 ].type === 'cta' ? at : sl.length, 0, blank( 'point' ) );
				cur = sl.length && sl[ sl.length - 1 ].type === 'cta' ? sl.length - 2 : sl.length - 1;
				drawSlides(); preview(); touch();
			} );
		}

		// Preview.
		var pText = $( '[data-bdb-preview-text]', root ), pMedia = $( '[data-bdb-preview-media]', root );
		var pCanvas = null, pvTimer = null;
		function previewSoon() { clearTimeout( pvTimer ); pvTimer = setTimeout( preview, 160 ); }
		function preview() {
			var p = post(), text = commentary();
			var lines = text.split( '\n' ), cut = text;
			var folded = false;
			if ( ! expanded && ( lines.length > 3 || text.length > 210 ) ) {
				cut = lines.slice( 0, 3 ).join( '\n' );
				if ( cut.length > 210 ) { cut = cut.slice( 0, 210 ); }
				folded = true;
			}
			pText.innerHTML = esc( cut ).replace( /(^|\s)(#[\w؀-ۿ_]+)/g, '$1<span class="tag">$2</span>' ) + ( folded ? '<button type="button" class="more" data-more>… ' + ( p.lang === 'fa' ? 'بیشتر' : 'more' ) + '</button>' : '' );
			pText.setAttribute( 'dir', p.lang === 'fa' ? 'rtl' : 'ltr' );
			var cnt = $( '[data-bdb-count]', root );
			if ( cnt ) {
				var first = ( p.body || '' ).split( '\n' ).slice( 0, 2 ).join( ' ' ).length;
				cnt.textContent = fa( text.length ) + ' کاراکتر · دو خط اول: ' + fa( first );
				cnt.className = text.length > 3000 ? 'is-bad' : '';
			}
			if ( p.format === 'poll' ) {
				var poll = p.meta.poll || {};
				pMedia.innerHTML = '<div class="bdb-poll"><b dir="auto">' + esc( poll.question || '' ) + '</b>' + ( poll.options || [] ).map( function ( o ) { return '<span dir="auto">' + esc( o ) + '</span>'; } ).join( '' ) + '<small>' + ( p.lang === 'fa' ? 'نظرسنجی' : 'Poll' ) + '</small></div>';
				return;
			}
			if ( ( p.format !== 'carousel' && p.format !== 'image' ) || ! p.slides.length || ! S() ) { pMedia.innerHTML = ''; return; }
			if ( ! pCanvas || ! pMedia.contains( pCanvas ) ) {
				pMedia.innerHTML = '<div class="bdb-carousel"><canvas></canvas>' + ( p.format === 'carousel' ? '<div class="bdb-carousel-bar"><button type="button" data-nav="-1">‹</button><span data-pos></span><button type="button" data-nav="1">›</button></div>' : '' ) + '</div>';
				pCanvas = $( 'canvas', pMedia );
			}
			var n = p.format === 'image' ? 1 : p.slides.length;
			cur = Math.min( cur, n - 1 );
			S().ready( app.render ).then( function () {
				S().render( p.slides[ cur ], cur, n, p.lang, pCanvas );
				var pos = $( '[data-pos]', pMedia );
				if ( pos ) { pos.textContent = ( p.meta.doc_title ? p.meta.doc_title + ' · ' : '' ) + fa( cur + 1 ) + ' / ' + fa( n ); }
			} );
		}
		root.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-more]' ) ) { expanded = true; preview(); }
			var nv = e.target.closest( '[data-nav]' );
			if ( nv ) {
				var n = post().slides.length;
				cur = ( cur + +nv.getAttribute( 'data-nav' ) + n ) % n;
				$$( '.bdb-slide-card', root ).forEach( function ( c ) { c.classList.toggle( 'is-current', +c.getAttribute( 'data-i' ) === cur ); } );
				preview();
			}
		} );

		// Quality.
		function renderQuality() {
			var q = app.quality, box = $( '[data-bdb-quality]', root );
			if ( ! box || ! q ) { return; }
			var icon = { ok: '✓', warn: '!', error: '✕' };
			box.innerHTML = '<div class="bdb-q-head"><span class="bdb-ring" style="--v:' + q.score + '"><b>' + fa( q.score ) + '</b></span><div><b>بررسی کیفیت</b><small>' + ( q.blocking ? 'خطاها را رفع کنید؛ انتشار متوقف است.' : 'آماده انتشار' ) + '</small></div></div><ul class="bdb-q-list">'
				+ q.checks.map( function ( c ) { return '<li class="is-' + c.level + '"><i>' + icon[ c.level ] + '</i><span><b>' + esc( c.label ) + '</b>' + ( c.msg ? '<small>' + esc( c.msg ) + '</small>' : '' ) + '</span></li>'; } ).join( '' ) + '</ul>';
		}

		// Hooks.
		function drawHooks( list ) {
			var box = $( '[data-bdb-hooks]', root );
			if ( ! box ) { return; }
			list = list || post().meta.alt_hooks || [];
			box.innerHTML = list.length ? '<p class="bdb-label">قلاب‌های جایگزین <small>(کلیک = جایگزینی دو خط اول)</small></p>' + list.map( function ( h ) { return '<button type="button" class="bdb-hook" dir="auto" data-hook="' + esc( h ) + '"' + ( locked ? ' disabled' : '' ) + '>' + esc( h ) + '</button>'; } ).join( '' ) : '';
		}
		root.addEventListener( 'click', function ( e ) {
			var h = e.target.closest( '[data-hook]' );
			if ( ! h || locked ) { return; }
			var body = f( 'body' ), parts = body.value.split( '\n' );
			var rest = parts.slice( parts[ 1 ] && parts[ 1 ].trim() ? 2 : 1 );
			body.value = h.getAttribute( 'data-hook' ).trim() + '\n' + rest.join( '\n' );
			collect(); preview(); touch();
		} );

		// Inputs.
		root.addEventListener( 'input', function ( e ) {
			if ( e.target.closest( '[data-bdb-slides]' ) || e.target.closest( '[data-bdb-metrics]' ) ) { return; }
			collect(); previewSoon(); touch();
		} );
		root.addEventListener( 'change', function ( e ) {
			if ( e.target.closest( '[data-bdb-slides]' ) || e.target.closest( '[data-bdb-metrics]' ) ) { return; }
			collect();
			if ( e.target.name === 'format' ) { formatPanels(); }
			if ( e.target.name === 'lang' ) { f( 'body' ).setAttribute( 'dir', post().lang === 'fa' ? 'rtl' : 'ltr' ); }
			preview(); touch();
		} );

		// Actions.
		function act( sel, fn ) {
			var b = $( sel, root );
			if ( b ) {
				b.addEventListener( 'click', function () {
					busy( b, true );
					Promise.resolve().then( function () { return fn( b ); } ).catch( function ( err ) { if ( err ) { toast( err.message, true ); } } ).then( function () { busy( b, false ); } );
				} );
			}
		}
		function needsFile() {
			var p = post();
			return ( p.format === 'carousel' || p.format === 'image' ) && ( app.quality.checks || [] ).some( function ( c ) { return c.level === 'error' && /فایل/.test( c.label ); } );
		}
		function withFile() {
			return save( true ).then( function () {
				if ( ! needsFile() ) { return app; }
				toast( 'ساخت فایل اسلایدها…' );
				return buildAsset( app ).then( function ( d ) { app.post = d.post; app.quality = d.quality; refreshMeta(); return app; } );
			} );
		}
		act( '[data-bdb-save]', function () { return save( false ); } );
		act( '[data-bdb-render]', function () {
			return save( true ).then( function () { return buildAsset( app ); } ).then( function ( d ) {
				app.post = d.post; app.quality = d.quality; refreshMeta();
				toast( post().format === 'image' ? 'تصویر ساخته شد' : 'PDF اسلایدها ساخته شد' );
			} );
		} );
		act( '[data-bdb-approve-this]', function () {
			return withFile().then( function () {
				return api( 'approve/' + post().id, { when: f( 'when' ) && f( 'when' ).value ? f( 'when' ).value : 'next' } );
			} ).then( function ( d ) {
				app.post = d.post; app.quality = d.quality; refreshMeta();
				toast( d.msg );
				setTimeout( function () { location.reload(); }, 1200 );
			} );
		} );
		act( '[data-bdb-publish-now]', function () {
			if ( ! confirm( 'این پست همین حالا روی پروفایل لینکدین منتشر شود؟' ) ) { return; }
			return withFile().then( function () { return api( 'publish/' + post().id, {} ); } ).then( function ( d ) {
				toast( d.msg );
				setTimeout( function () { location.reload(); }, 1400 );
			} );
		} );
		act( '[data-bdb-next-slot]', function () {
			var w = f( 'when' );
			if ( w ) { w.value = ''; }
			collect();
			var d = payload(); d.when = 'next';
			return api( 'save/' + post().id, d ).then( function ( r ) { app.post = r.post; app.quality = r.quality; dirty = false; refreshMeta(); toast( r.post.when_label ); } );
		} );
		act( '[data-bdb-rewrite]', function () {
			var extra = prompt( 'دستور برای بازنویسی کامل (اختیاری)، مثلاً: داستانی‌تر، با تمرکز روی مدیران فروشگاه:', '' );
			if ( extra === null ) { return; }
			toast( 'در حال بازنویسی… (۳۰ تا ۹۰ ثانیه)' );
			return save( true ).then( function () { return api( 'rewrite/' + post().id, { extra: extra } ); } ).then( function () { dirty = false; location.reload(); } );
		} );
		$$( '[data-bdb-polish]', root ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				busy( b, true );
				save( true ).then( function () { return api( 'polish/' + post().id, { how: b.getAttribute( 'data-bdb-polish' ) } ); } ).then( function ( d ) {
					var old = f( 'body' ).value;
					f( 'body' ).value = d.body;
					collect(); preview(); touch();
					drawHooks( d.hooks );
					toast( 'متن جدید جایگزین شد (برای برگشت Ctrl+Z یا دکمه «قبلی»)' );
					showUndo( old );
				} ).catch( function ( err ) { toast( err.message, true ); } ).then( function () { busy( b, false ); } );
			} );
		} );
		function showUndo( old ) {
			var tools = $( '.bdb-ai-tools', root ), u = $( '[data-undo]', tools );
			if ( ! u ) { u = document.createElement( 'button' ); u.type = 'button'; u.className = 'bdb-btn is-small is-ghost'; u.setAttribute( 'data-undo', '' ); u.textContent = '↶ قبلی'; tools.appendChild( u ); }
			u.onclick = function () { f( 'body' ).value = old; collect(); preview(); touch(); u.remove(); };
		}
		act( '[data-bdb-counterpart]', function () {
			toast( 'در حال نوشتن نسخه زبان دیگر… (۳۰ تا ۹۰ ثانیه)' );
			return save( true ).then( function () { return api( 'counterpart/' + post().id, {} ); } ).then( function ( d ) { go( d.url ); } );
		} );
		act( '[data-bdb-duplicate]', function () {
			return api( 'duplicate/' + post().id, {} ).then( function ( d ) { go( d.url ); } );
		} );
		act( '[data-bdb-delete]', function () {
			if ( ! confirm( 'این پست حذف شود؟' ) ) { return; }
			return api( 'delete/' + post().id, {} ).then( function () { dirty = false; go( M.urls.queue ); } );
		} );
		act( '[data-bdb-copy-text]', function () {
			collect();
			return navigator.clipboard.writeText( commentary() ).then( function () { toast( 'متن پست کپی شد' ); } );
		} );
		$$( '[data-bdb-download]', root ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				collect();
				var p = post(), kind = b.getAttribute( 'data-bdb-download' );
				var slides = p.format === 'image' ? p.slides.slice( 0, 1 ) : p.slides;
				if ( ! slides.length ) { toast( 'اسلایدی نیست.', true ); return; }
				S().ready( app.render ).then( function () { return S().renderAll( slides, p.lang ); } ).then( function ( cv ) {
					cv = S().safe( cv, slides, p.lang );
					var name = slug( p.title );
					if ( kind === 'pdf' ) { download( S().pdf( cv, p.title ), name + '.pdf' ); }
					if ( kind === 'pptx' ) { download( S().pptx( cv, p.title ), name + '.pptx' ); }
					if ( kind === 'png' ) { S().png( cv[ Math.min( cur, cv.length - 1 ) ] ).then( function ( blob ) { download( blob, name + '-' + ( cur + 1 ) + '.png' ); } ); }
				} ).catch( function ( err ) { toast( err.message, true ); } );
			} );
		} );

		// Manual metrics.
		var mf = $( '[data-bdb-metrics]', root );
		if ( mf ) {
			mf.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var d = {};
				$$( 'input', mf ).forEach( function ( i ) { d[ i.name ] = i.value; } );
				api( 'metrics/' + mf.getAttribute( 'data-bdb-metrics' ), d ).then( function ( r ) { toast( r.msg ); } ).catch( function ( err ) { toast( err.message, true ); } );
			} );
		}

		// Ctrl/Cmd+S.
		document.addEventListener( 'keydown', function ( e ) {
			if ( ( e.ctrlKey || e.metaKey ) && e.key === 's' ) { e.preventDefault(); save( false ).catch( function () {} ); }
		} );

		formatPanels();
		drawSlides();
		drawHooks();
		renderQuality();
		preview();
	}

	// ---- Analytics import ----
	var imp = $( '[data-bdb-import]' );
	if ( imp ) {
		imp.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var btn = $( 'button', imp ), fd = new FormData( imp );
			busy( btn, true );
			api( 'import', fd ).then( function ( d ) { toast( d.msg ); setTimeout( function () { location.reload(); }, 1200 ); } ).catch( function ( err ) { busy( btn, false ); toast( err.message, true ); } );
		} );
	}

	// ---- Settings tests ----
	$$( '[data-bdb-test]' ).forEach( function ( b ) {
		b.addEventListener( 'click', function () {
			var what = b.getAttribute( 'data-bdb-test' ), out = $( '[data-bdb-test-out="' + what + '"]' );
			busy( b, true );
			out.hidden = false;
			out.innerHTML = '<p class="bdb-muted">در حال بررسی…</p>';
			api( 'test/' + what, {} ).then( function ( d ) {
				out.innerHTML = '<ul class="bdb-q-list">' + d.steps.map( function ( s ) { return '<li class="is-' + ( s.ok ? 'ok' : 'error' ) + '"><i>' + ( s.ok ? '✓' : '✕' ) + '</i><span><b>' + esc( s.name ) + '</b><small>' + esc( s.msg ) + '</small></span></li>'; } ).join( '' ) + '</ul>';
			} ).catch( function ( err ) { out.innerHTML = '<p class="bdb-err-text">' + esc( err.message ) + '</p>'; } ).then( function () { busy( b, false ); } );
		} );
	} );

	// ---- Brand: pillars rows + slide preview ----
	var pillars = $( '[data-bdb-pillars]' );
	if ( pillars ) {
		document.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-bdb-pillar-add]' ) ) {
				var row = $( '.bdb-pillar-row', pillars ).cloneNode( true );
				$$( 'input', row ).forEach( function ( i ) { i.value = i.type === 'number' ? '10' : ''; } );
				pillars.appendChild( row );
				$( 'input[type=text]', row ).focus();
			}
			var del = e.target.closest( '[data-bdb-row-del]' );
			if ( del && $$( '.bdb-pillar-row', pillars ).length > 1 ) { del.closest( '.bdb-pillar-row' ).remove(); }
		} );
	}
	var bp = $( '[data-bdb-brand-preview]' );
	if ( bp && S() ) {
		var paintBrand = function () {
			var form = bp.closest( 'form' ), c = Object.assign( {}, M.render );
			c.color = form.brand_color.value; c.color2 = form.brand_color2.value; c.dark = form.brand_dark.value;
			c.name = form.name.value; c.name_fa = form.name_fa.value; c.headline = form.headline.value; c.handle = form.handle.value;
			S().ready( c ).then( function () {
				S().render( { type: 'cover', kicker: 'SEO STRATEGY', title: 'The 90-day SEO plan I would run for a new store', body: 'Research → structure → money pages → content engine', items: [] }, 0, 8, 'en', bp );
			} );
		};
		$$( 'input[type=color], input[name=name], input[name=headline], input[name=handle]', bp.closest( 'form' ) ).forEach( function ( i ) { i.addEventListener( 'input', paintBrand ); } );
		paintBrand();
	}

	// ---- Chart hover: crosshair + tooltip ----
	$$( '[data-bdb-chart]' ).forEach( function ( svg ) {
		var d = JSON.parse( svg.getAttribute( 'data-bdb-chart' ) ), wrap = svg.parentNode;
		var tip = $( '.bdb-tip', wrap ), cross = $( '.cross', svg ), n = d.labels.length;
		if ( n < 2 ) { return; }
		svg.addEventListener( 'mousemove', function ( e ) {
			var r = svg.getBoundingClientRect(), vx = ( e.clientX - r.left ) / r.width * d.w;
			var i = Math.round( ( vx - d.pad.l ) / ( d.w - d.pad.l - d.pad.r ) * ( n - 1 ) );
			i = Math.max( 0, Math.min( n - 1, i ) );
			var x = d.pad.l + ( d.w - d.pad.l - d.pad.r ) * i / ( n - 1 );
			cross.setAttribute( 'x1', x ); cross.setAttribute( 'x2', x ); cross.removeAttribute( 'hidden' );
			tip.hidden = false;
			tip.innerHTML = '<b>' + esc( d.labels[ i ] ) + '</b>' + d.series.map( function ( s ) { return '<span>' + esc( s.name ) + ': ' + fa( Number( s.values[ i ] || 0 ).toLocaleString( 'en-US' ) ) + '</span>'; } ).join( '' );
			var px = x / d.w * r.width;
			tip.style.left = Math.min( r.width - 140, Math.max( 0, px - 70 ) ) + 'px';
		} );
		svg.addEventListener( 'mouseleave', function () { tip.hidden = true; cross.setAttribute( 'hidden', '' ); } );
	} );
} )();
