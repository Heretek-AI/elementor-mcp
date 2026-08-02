/**
 * Sandbox cloud/marketplace button state machine.
 *
 * Each artifact row renders a `.emcp-sb-cloud` cluster carrying its initial
 * state as JSON in data-state. This script renders the right buttons from that
 * state, refreshes pushed artifacts against the cloud on load, and wires the
 * Save-to-Cloud / Push-update actions.
 *
 * State shape (from EMCP_Tools_Admin::cloud_action_payload):
 *   { kind, id, pushed, changed, slug, status, published, has_pending_update,
 *     publish_url, view_url }
 */
( function () {
	'use strict';
	var ajaxUrl = window.ajaxurl || '/wp-admin/admin-ajax.php';

	function post( action, cluster, extra ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', cluster.getAttribute( 'data-nonce' ) || '' );
		body.append( 'kind', cluster.getAttribute( 'data-kind' ) || '' );
		body.append( 'id', cluster.getAttribute( 'data-id' ) || '' );
		if ( extra ) {
			Object.keys( extra ).forEach( function ( k ) { body.append( k, extra[ k ] ); } );
		}
		return fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) { return r.json(); } );
	}

	// WordPress .button (display:inline-block) overrides the [hidden] attribute,
	// so toggle inline display instead.
	function show( el, on ) { if ( el ) { el.style.display = on ? '' : 'none'; } }

	// Update a button's label without wiping its dashicon.
	function setText( el, text ) {
		if ( ! el ) { return; }
		var t = el.querySelector( '.emcp-sb-txt' );
		if ( t ) { t.textContent = text; } else { el.textContent = text; }
	}

	function render( cluster, s ) {
		cluster.__state = s;
		var save = cluster.querySelector( '.emcp-sb-save' );
		var pub = cluster.querySelector( '.emcp-sb-publish' );
		var view = cluster.querySelector( '.emcp-sb-view' );
		var upd = cluster.querySelector( '.emcp-sb-update' );
		var tag = cluster.querySelector( '.emcp-sb-tag' );

		// Save to Cloud: primary until published. Once published, updates go
		// through "Push update" instead.
		if ( save ) {
			if ( ! s.pushed ) {
				setText( save, save.getAttribute( 'data-t-save' ) || 'Save to Cloud' );
				save.disabled = false;
				show( save, true );
			} else if ( s.published ) {
				show( save, false );
			} else if ( s.changed ) {
				setText( save, save.getAttribute( 'data-t-update' ) || 'Update cloud' );
				save.disabled = false;
				show( save, true );
			} else {
				setText( save, save.getAttribute( 'data-t-saved' ) || 'Saved' );
				save.disabled = true;
				show( save, true );
			}
		}

		// Publish: only before it's on the marketplace.
		if ( pub ) {
			var canPublish = s.pushed && ! s.slug && s.publish_url;
			if ( canPublish ) { pub.href = s.publish_url; }
			show( pub, !! canPublish );
		}

		// View on Marketplace: once it has a listing.
		if ( view ) {
			if ( s.view_url ) { view.href = s.view_url; }
			show( view, !! s.slug );
		}

		// Push update: published + locally changed + not already in review.
		show( upd, !! ( s.published && s.changed && ! s.has_pending_update ) );

		// Status tag.
		if ( tag ) {
			var text = '';
			if ( s.has_pending_update ) { text = tag.getAttribute( 'data-t-inreview' ) || 'Update in review'; }
			else if ( s.slug && s.status === 'pending' ) { text = tag.getAttribute( 'data-t-pending' ) || 'In review'; }
			else if ( s.published && ! s.changed ) { text = tag.getAttribute( 'data-t-uptodate' ) || 'Up to date'; }
			tag.textContent = text;
			show( tag, text !== '' );
		}
	}

	function msg( cluster, text, isErr ) {
		var m = cluster.querySelector( '.emcp-sb-msg' );
		if ( ! m ) { return; }
		m.textContent = text || '';
		m.style.color = isErr ? '#b32d2e' : '#2271b1';
	}

	function handle( res, cluster ) {
		if ( res && res.success && res.data ) {
			render( cluster, res.data );
			msg( cluster, res.data.message || '', false );
		} else {
			msg( cluster, ( res && res.data && res.data.message ) || 'Failed.', true );
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.emcp-sb-save, .emcp-sb-update' );
		if ( ! btn ) { return; }
		var cluster = btn.closest( '.emcp-sb-cloud' );
		if ( ! cluster ) { return; }
		e.preventDefault();
		btn.disabled = true;
		msg( cluster, '…', false );

		if ( btn.classList.contains( 'emcp-sb-update' ) ) {
			var note = window.prompt( 'What changed in this update? (optional)' );
			if ( note === null ) { btn.disabled = false; msg( cluster, '', false ); return; }
			post( 'emcp_tools_push_update', cluster, { changelog: note } ).then( function ( res ) {
				btn.disabled = false; handle( res, cluster );
			} ).catch( function () { btn.disabled = false; msg( cluster, 'Network error.', true ); } );
		} else {
			post( 'emcp_tools_backup_artifact', cluster, null ).then( function ( res ) {
				btn.disabled = false; handle( res, cluster );
			} ).catch( function () { btn.disabled = false; msg( cluster, 'Network error.', true ); } );
		}
	} );

	function init() {
		var clusters = document.querySelectorAll( '.emcp-sb-cloud' );
		Array.prototype.forEach.call( clusters, function ( cluster ) {
			var s;
			try { s = JSON.parse( cluster.getAttribute( 'data-state' ) || '{}' ); } catch ( err ) { s = {}; }
			render( cluster, s );
			// Pushed artifacts may have gained a listing (published on the website)
			// since last time — refresh their state in the background.
			if ( s.pushed ) {
				post( 'emcp_tools_marketplace_state', cluster, null ).then( function ( res ) {
					if ( res && res.success && res.data ) { render( cluster, res.data ); }
				} ).catch( function () {} );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
