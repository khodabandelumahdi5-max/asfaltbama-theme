/* Mobile menu toggle. */
( function () {
	'use strict';
	var toggle = document.querySelector( '.yadak-header__toggle' );
	var nav = document.getElementById( 'yadak-nav' );
	if ( ! toggle || ! nav ) {
		return;
	}
	toggle.addEventListener( 'click', function () {
		var open = nav.classList.toggle( 'is-open' );
		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	} );
} )();

/* Mega menu: toggle buttons (mobile drawer, touch), Escape closes. */
( function () {
	'use strict';
	var items = document.querySelectorAll( '.yadak-nav__list > li.has-mega' );
	if ( ! items.length ) {
		return;
	}
	function close( except ) {
		items.forEach( function ( li ) {
			if ( li !== except ) {
				li.classList.remove( 'is-open' );
				var b = li.querySelector( '.yadak-mega__toggle' );
				if ( b ) {
					b.setAttribute( 'aria-expanded', 'false' );
				}
			}
		} );
	}
	items.forEach( function ( li ) {
		var btn = li.querySelector( '.yadak-mega__toggle' );
		var link = li.querySelector( 'a' );
		btn.addEventListener( 'click', function () {
			var open = li.classList.toggle( 'is-open' );
			btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			close( li );
		} );
		// Touch screens wide enough for the desktop menu: first tap opens the panel.
		link.addEventListener( 'click', function ( e ) {
			if ( window.matchMedia( '(hover: none) and (min-width: 960px)' ).matches && ! li.classList.contains( 'is-open' ) ) {
				e.preventDefault();
				li.classList.add( 'is-open' );
				close( li );
			}
		} );
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) {
			close( null );
			if ( document.activeElement && document.activeElement.closest( '.yadak-mega' ) ) {
				document.activeElement.blur();
			}
		}
	} );
	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.closest( '.yadak-nav' ) ) {
			close( null );
		}
	} );
} )();
