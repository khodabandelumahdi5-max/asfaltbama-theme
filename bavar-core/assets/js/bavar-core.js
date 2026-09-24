/* BAVAR Core — entry gate, three-question modal, request forms. */
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
	var answered = store.get( 'bavar_answered' ) || {};
	var hasCookie = document.cookie.indexOf( 'bavar_ok=1' ) !== -1;
	var lastFocus = null;

	function $( sel, root ) { return ( root || document ).querySelector( sel ); }
	function $$( sel, root ) { return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) ); }

	function fillPerson( form ) {
		if ( ! person ) { return; }
		[ 'name', 'phone', 'job' ].forEach( function ( k ) {
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
		var first = $( 'textarea, input:not([type=hidden]):not(.bv-hp)', modal );
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

	/* Entry gate */
	var gate = $( '#bv-gate' );
	if ( gate && cfg.gate && 'off' !== cfg.gate && ! person && ! hasCookie ) {
		openModal( gate, 'dismissible' === cfg.gate );
	}

	/* Path cards → three questions */
	var qModal = $( '#bv-questions' );
	function openPath( key, productId ) {
		var path = cfg.paths && cfg.paths[ key ];
		if ( ! path || ! qModal ) { return false; }
		var form = $( 'form', qModal );
		form.reset();
		form.path.value = key;
		form.product_id.value = productId || '';
		$( '[data-bv-q-title]', qModal ).textContent = path.title;
		$( '[data-bv-q-subtitle]', qModal ).textContent = path.subtitle;
		path.questions.forEach( function ( q, i ) {
			$( '[data-bv-q="' + ( i + 1 ) + '"]', qModal ).textContent = q;
		} );
		fillPerson( form );
		showError( form, '' );
		openModal( qModal, true );
		return true;
	}

	document.addEventListener( 'click', function ( e ) {
		var link = e.target.closest( '[data-bv-path]' );
		if ( ! link ) { return; }
		var key = link.getAttribute( 'data-bv-path' );
		if ( answered[ key ] ) { return; } // Already answered: follow the link.
		if ( openPath( key, link.getAttribute( 'data-bv-product' ) ) ) { e.preventDefault(); }
	} );

	/* Forms */
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
			var missing = $$( '[required]', form ).filter( function ( el ) { return ! el.value.trim(); } );
			if ( missing.length ) {
				showError( form, 'لطفاً همه‌ی موارد را کامل کنید.' );
				missing[ 0 ].focus();
				return;
			}
			var button = $( 'button[type=submit]', form );
			var data = new FormData( form );
			data.append( 'action', 'gate' === kind ? 'bavar_lead' : 'bavar_path' );
			data.append( 'page', window.location.href );
			button.disabled = true;
			button.classList.add( 'is-loading' );
			showError( form, '' );

			fetch( cfg.ajax, { method: 'POST', body: data, credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( ! res || ! res.success ) {
						throw new Error( res && res.data && res.data.message ? res.data.message : 'خطا در ثبت اطلاعات.' );
					}
					person = res.data.lead;
					store.set( 'bavar_person', person );
					if ( 'gate' === kind ) {
						closeModal( gate );
						$$( '[data-bv-form]' ).forEach( fillPerson );
						return;
					}
					answered[ form.path.value ] = 1;
					store.set( 'bavar_answered', answered );
					window.location.href = res.data.redirect;
				} )
				.catch( function ( err ) {
					showError( form, err.message || 'خطا در ارتباط. دوباره تلاش کنید.' );
				} )
				.then( function () {
					button.disabled = false;
					button.classList.remove( 'is-loading' );
				} );
		} );
	} );
} )();
