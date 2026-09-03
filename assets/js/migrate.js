/**
 * Backup & Migrate admin — Push/Sync panel behaviour.
 *
 * - DB/files scope radios reveal their "selected" pickers.
 * - Push archive-source radios reveal the matching inputs.
 * - Long-running (push/sync) forms disable their submit button while they run
 *   and surface a "do not close this tab" note; the result comes back as a
 *   server-rendered admin notice after the redirect.
 *
 * @package EMCP_Tools
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function bindScopeToggle( radios, panelId ) {
		if ( ! radios.length ) {
			return;
		}
		var name = radios[ 0 ]?.name || '';
		var panel = document.getElementById( panelId );
		function refresh() {
			if ( ! panel ) {
				return;
			}
			var checked = document.querySelector( 'input[name="' + name + '"]:checked' );
			panel.style.display = checked && 'selected' === checked.value ? 'block' : 'none';
		}
		radios.forEach( function ( radio ) {
			radio.addEventListener( 'change', refresh );
		} );
		refresh();
	}

	ready( function () {
		// Sync scope pickers.
		bindScopeToggle( Array.prototype.slice.call( document.querySelectorAll( 'input[name="db_mode"]' ) ), 'emcp_db_tables' );
		bindScopeToggle( Array.prototype.slice.call( document.querySelectorAll( 'input[name="files_mode"]' ) ), 'emcp_file_roots' );

		// "Select all" table checkbox.
		var selectAll = document.querySelector( '.emcp-tables-all' );
		if ( selectAll ) {
			selectAll.addEventListener( 'change', function () {
				var boxes = document.querySelectorAll( '#emcp_db_tables input[name="tables[]"]' );
				boxes.forEach( function ( box ) {
					box.checked = selectAll.checked;
				} );
			} );
		}

		// Push archive source radios.
		function bindArchiveSource() {
			var radios = document.querySelectorAll( 'input[name="archive_source"]' );
			if ( ! radios.length ) {
				return;
			}
			function refresh() {
				var checked = document.querySelector( 'input[name="archive_source"]:checked' );
				var mode = checked ? checked.value : 'build';
				var build = document.querySelectorAll( '.emcp-push-build' );
				var existing = document.querySelectorAll( '.emcp-push-existing' );
				build.forEach( function ( el ) {
					el.style.display = 'existing' === mode ? 'none' : 'block';
				} );
				existing.forEach( function ( el ) {
					el.style.display = 'existing' === mode ? 'block' : 'none';
				} );
			}
			radios.forEach( function ( radio ) {
				radio.addEventListener( 'change', refresh );
			} );
			refresh();
		}
		bindArchiveSource();

		// Long-running forms: disable the submit button + show the working note.
		document.querySelectorAll( 'form.emcp-migrate-long' ).forEach( function ( form ) {
			form.addEventListener( 'submit', function () {
				var btn = form.querySelector( 'button[type="submit"]' );
				if ( btn ) {
					btn.disabled = true;
					btn.textContent = btn.dataset.working || 'Working…';
				}
				var note = form.querySelector( '.emcp-working-note' );
				if ( note ) {
					note.style.display = 'block';
				}
			} );
		} );
	} );
} )();
