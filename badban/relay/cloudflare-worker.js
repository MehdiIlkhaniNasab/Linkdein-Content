/**
 * Badban — LinkedIn relay (Cloudflare Worker).
 *
 * LinkedIn is blocked in Iran. When the WordPress server cannot use a proxy,
 * Badban sends every LinkedIn request to this Worker instead:
 *
 *     {RELAY_URL}/{host}/{path}?{query}      +  header X-Badban-Relay: {RELAY_SECRET}
 *
 * and the Worker forwards it to https://{host}/{path}?{query} from abroad.
 * Only LinkedIn hosts are allowed, and only with the right secret.
 *
 * Setup:
 *   1. Cloudflare dashboard › Workers & Pages › Create › Worker › paste this file › Deploy.
 *   2. Settings › Variables and Secrets › add a Secret named RELAY_SECRET (a long random string).
 *   3. Settings › Domains & Routes › add a Custom Domain on your own domain,
 *      e.g. li-relay.example.com (workers.dev addresses are often unreachable from Iran).
 *   4. Badban › Settings › «آدرس رله» = https://li-relay.example.com and «کلید رله» = the same secret.
 */
const ALLOWED = /(^|\.)linkedin\.com$|(^|\.)licdn\.com$/i;

export default {
	async fetch( request, env ) {
		if ( ! env.RELAY_SECRET || request.headers.get( 'X-Badban-Relay' ) !== env.RELAY_SECRET ) {
			return new Response( 'Forbidden', { status: 403 } );
		}
		const url = new URL( request.url );
		const parts = url.pathname.replace( /^\/+/, '' ).split( '/' );
		const host = parts.shift();
		if ( ! host || ! ALLOWED.test( host ) ) {
			return new Response( 'Host not allowed', { status: 400 } );
		}
		const target = 'https://' + host + '/' + parts.join( '/' ) + url.search;
		const headers = new Headers( request.headers );
		[ 'X-Badban-Relay', 'Host', 'CF-Connecting-IP', 'CF-IPCountry', 'CF-Ray', 'CF-Visitor', 'X-Forwarded-For', 'X-Forwarded-Proto', 'X-Real-IP' ].forEach( ( h ) => headers.delete( h ) );
		const init = { method: request.method, headers, redirect: 'manual' };
		if ( ! [ 'GET', 'HEAD' ].includes( request.method ) ) {
			init.body = await request.arrayBuffer();
		}
		const res = await fetch( target, init );
		const out = new Headers( res.headers );
		out.delete( 'Set-Cookie' );
		return new Response( res.body, { status: res.status, headers: out } );
	},
};
