/* BAVAR Core — entry form, section forms, page-view tracking. */
( function () {
	'use strict';

	var cfg = window.BAVAR || {};
	var store = {
		get: function ( key ) {
			try { return JSON.parse( window.localStorage.getItem( key ) ); } catch ( e ) { return null; }
		},
		set: function ( key, value ) {
			try { window.localStorage.setItem( key, JSON.stringify( value ) ); } catch ( e ) {}
		}
	};

	var person = cfg.person || store.get( 'bavar_person' );
	if ( person && ! person.phone ) { person = null; }
	var hasCookie = document.cookie.indexOf( 'bavar_ok=1' ) !== -1;
	var known = !! person || hasCookie;
	var lastFocus = null;

	function $( sel, root ) { return ( root || document ).querySelector( sel ); }
	function $$( sel, root ) { return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) ); }

	function post( data ) {
		return fetch( cfg.ajax, { method: 'POST', body: data, credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } );
	}

	function fillPerson( form ) {
		if ( ! person ) { return; }
		[ 'first_name', 'last_name', 'phone', 'job' ].forEach( function ( k ) {
			var input = form.querySelector( '[name="' + k + '"]' );
			if ( input && ! input.value && person[ k ] ) { input.value = person[ k ]; }
		} );
	}

	function openModal( modal, closable ) {
		lastFocus = document.activeElement;
		modal.hidden = false;
		modal.dataset.closable = closable ? '1' : '';
		var close = $( '[data-bv-close]', modal );
		if ( close ) { close.hidden = ! closable; }
		document.documentElement.classList.add( 'bv-locked' );
		window.requestAnimationFrame( function () { modal.classList.add( 'is-open' ); } );
		var first = $$( 'textarea, input:not([type=hidden]):not(.bv-hp)', modal ).filter( function ( el ) {
			return ! el.closest( '[hidden]' ) && ! el.value;
		} )[ 0 ];
		if ( first ) { setTimeout( function () { first.focus( { preventScroll: true } ); }, 250 ); }
	}

	function closeModal( modal ) {
		modal.classList.remove( 'is-open' );
		document.documentElement.classList.remove( 'bv-locked' );
		setTimeout( function () { modal.hidden = true; }, 300 );
		if ( lastFocus && lastFocus.focus ) { lastFocus.focus( { preventScroll: true } ); }
	}

	$$( '.bv-modal' ).forEach( function ( modal ) {
		modal.addEventListener( 'click', function ( e ) {
			if ( modal.dataset.closable && ( e.target === modal || e.target.closest( '[data-bv-close]' ) ) ) {
				closeModal( modal );
			}
		} );
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' !== e.key ) { return; }
		$$( '.bv-modal.is-open' ).forEach( function ( m ) { if ( m.dataset.closable ) { closeModal( m ); } } );
	} );

	/* Section form (modal) */
	var qModal = $( '#bv-questions' );

	function openPath( key, productId, required ) {
		var path = cfg.paths && cfg.paths[ key ];
		if ( ! path || ! qModal ) { return false; }
		var form = $( 'form', qModal );
		form.reset();
		form.path.value = key;
		form.product_id.value = productId || '';
		form.fields.value = path.fields.join( ',' );
		form.dataset.redirect = required ? '' : '1';
		$( '[data-bv-q-title]', qModal ).textContent = path.title;

		var box = $( '[data-bv-questions]', qModal );
		box.innerHTML = '';
		path.questions.forEach( function ( q, i ) {
			var label = document.createElement( 'label' );
			label.className = 'bv-field bv-field--q';
			var span = document.createElement( 'span' );
			var b = document.createElement( 'b' );
			b.textContent = q;
			span.appendChild( b );
			var ta = document.createElement( 'textarea' );
			ta.name = 'a' + ( i + 1 );
			ta.rows = 2;
			ta.required = true;
			label.appendChild( span );
			label.appendChild( ta );
			box.appendChild( label );
		} );

		$$( '[data-bv-field]', qModal ).forEach( function ( el ) {
			var on = path.fields.indexOf( el.getAttribute( 'data-bv-field' ) ) !== -1;
			el.hidden = ! on;
			$( 'input', el ).required = on;
		} );
		fillPerson( form );
		showError( form, '' );
		openModal( qModal, ! required );
		return true;
	}

	document.addEventListener( 'click', function ( e ) {
		var link = e.target.closest( '[data-bv-path]' );
		if ( ! link ) { return; }
		var key = link.getAttribute( 'data-bv-path' );
		var path = cfg.paths && cfg.paths[ key ];
		// Known visitors go straight in unless the section has its own questions.
		if ( ! path || ( known && ! path.questions.length ) ) { return; }
		if ( openPath( key, link.getAttribute( 'data-bv-product' ), false ) ) { e.preventDefault(); }
	} );

	/* Entry form, or the section form on course / Ashiane Simorgh pages */
	var gate = $( '#bv-gate' );
	var view = cfg.view;
	if ( ! known && ! cfg.admin ) {
		if ( view && view.require && cfg.paths[ view.path ] ) {
			openPath( view.path, view.product_id, true );
		} else if ( gate && cfg.gate && 'off' !== cfg.gate ) {
			openModal( gate, 'dismissible' === cfg.gate );
		}
	}

	/* Page-view tracking */
	if ( view && ( view.path || view.product_id ) ) {
		var t = new FormData();
		t.append( 'action', 'bavar_track' );
		t.append( 'path', view.path || '' );
		t.append( 'product_id', view.product_id || 0 );
		post( t ).catch( function () {} );
	}

	/* Submitting */
	function showError( form, msg ) {
		var el = $( '.bv-form__error', form );
		if ( ! el ) { return; }
		el.textContent = msg;
		el.hidden = ! msg;
	}

	$$( '[data-bv-form]' ).forEach( function ( form ) {
		fillPerson( form );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var kind = form.getAttribute( 'data-bv-form' );
			var missing = $$( '[required]', form ).filter( function ( el ) {
				return ! el.closest( '[hidden]' ) && ! el.value.trim();
			} );
			if ( missing.length ) {
				showError( form, 'لطفاً همه‌ی موارد را کامل کنید.' );
				missing[ 0 ].focus();
				return;
			}
			var button = $( 'button[type=submit]', form );
			var data = new FormData( form );
			data.append( 'action', 'gate' === kind ? 'bavar_lead' : 'bavar_path' );
			button.disabled = true;
			showError( form, '' );

			post( data )
				.then( function ( res ) {
					if ( ! res || ! res.success ) {
						throw new Error( res && res.data && res.data.message ? res.data.message : 'خطا در ثبت اطلاعات.' );
					}
					person = res.data.lead;
					known = true;
					store.set( 'bavar_person', person );
					$$( '[data-bv-form]' ).forEach( fillPerson );

					var modal = form.closest( '.bv-modal' );
					if ( 'gate' === kind || ( modal && ! form.dataset.redirect ) ) {
						closeModal( modal );
						return;
					}
					window.location.href = res.data.redirect + ( form.hasAttribute( 'data-bv-inline' ) ? '#bv-request' : '' );
				} )
				.catch( function ( err ) {
					showError( form, err.message || 'خطا در ارتباط. دوباره تلاش کنید.' );
				} )
				.then( function () {
					button.disabled = false;
				} );
		} );
	} );
} )();
