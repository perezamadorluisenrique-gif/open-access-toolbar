/**
 * Open Access Toolbar: runs the content check in batches and the theme check.
 */
( function () {
	'use strict';

	var data = window.oatbAdmin;

	function post( action, extra ) {
		var body = new URLSearchParams( { action: action, nonce: data.nonce } );
		Object.keys( extra || {} ).forEach( function ( key ) {
			body.append( key, extra[ key ] );
		} );
		return fetch( data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( response ) {
			return response.json();
		} );
	}

	function format( template, done, total ) {
		return template.replace( '%1$d', done ).replace( '%2$d', total );
	}

	var start = document.getElementById( 'oatb-start' );
	var box = document.getElementById( 'oatb-progress' );

	if ( start && box ) {
		var bar = box.querySelector( 'progress' );
		var status = box.querySelector( '[role="status"]' );

		var fail = function () {
			status.textContent = data.i18n.error;
			start.disabled = false;
		};

		var next = function ( offset, total ) {
			post( 'oatb_check_batch', { offset: offset } ).then( function ( json ) {
				if ( ! json || ! json.success ) {
					return fail();
				}
				var r = json.data;
				bar.value = r.total ? Math.round( ( r.done / r.total ) * 100 ) : 100;
				status.textContent = format( data.i18n.progress, r.done, r.total );
				if ( r.finished ) {
					status.textContent = data.i18n.done;
					window.location.reload();
				} else {
					next( r.done, total );
				}
			} ).catch( fail );
		};

		start.addEventListener( 'click', function () {
			start.disabled = true;
			box.hidden = false;
			bar.value = 0;
			post( 'oatb_check_start' ).then( function ( json ) {
				if ( ! json || ! json.success ) {
					return fail();
				}
				status.textContent = format( data.i18n.progress, 0, json.data.total );
				next( 0, json.data.total );
			} ).catch( fail );
		} );
	}

	var site = document.getElementById( 'oatb-site-check' );
	var out = document.getElementById( 'oatb-site-results' );
	if ( site && out ) {
		site.addEventListener( 'click', function () {
			site.disabled = true;
			out.textContent = data.i18n.checking;
			post( 'oatb_site_check' ).then( function ( json ) {
				// The server escapes every value in this HTML.
				out.innerHTML = json && json.data && json.data.html ? json.data.html : '';
				if ( ! out.innerHTML ) {
					out.textContent = data.i18n.error;
				}
				site.disabled = false;
			} ).catch( function () {
				out.textContent = data.i18n.error;
				site.disabled = false;
			} );
		} );
	}
}() );
