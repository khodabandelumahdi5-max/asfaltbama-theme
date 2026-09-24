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
