/* BAVAR theme — header, menu, reveal, subtle parallax. */
( function () {
	'use strict';
	var root = document.documentElement;
	root.classList.add( 'bv-js' );

	var header = document.querySelector( '[data-bv-header]' );
	var toggle = document.querySelector( '[data-bv-toggle]' );

	function onScroll() {
		if ( header ) { header.classList.toggle( 'is-scrolled', window.scrollY > 24 ); }
	}
	window.addEventListener( 'scroll', onScroll, { passive: true } );
	onScroll();

	if ( header && toggle ) {
		var setOpen = function ( open ) {
			header.classList.toggle( 'is-open', open );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			root.classList.toggle( 'bv-locked', open );
		};
		toggle.addEventListener( 'click', function () { setOpen( ! header.classList.contains( 'is-open' ) ); } );
		header.addEventListener( 'click', function ( e ) { if ( e.target.closest( '.bv-header__nav a' ) ) { setOpen( false ); } } );
		document.addEventListener( 'keydown', function ( e ) { if ( 'Escape' === e.key ) { setOpen( false ); } } );
	}

	var items = document.querySelectorAll( '.bv-reveal' );
	if ( 'IntersectionObserver' in window ) {
		var io = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					entry.target.classList.add( 'is-in' );
					io.unobserve( entry.target );
				}
			} );
		}, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' } );
		items.forEach( function ( el ) { io.observe( el ); } );
	} else {
		items.forEach( function ( el ) { el.classList.add( 'is-in' ); } );
	}

	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var parallax = document.querySelectorAll( '[data-bv-parallax]' );
	if ( parallax.length && ! reduce ) {
		var ticking = false;
		var update = function () {
			parallax.forEach( function ( img ) {
				var box = img.parentElement.getBoundingClientRect();
				var progress = ( box.top + box.height / 2 - window.innerHeight / 2 ) / window.innerHeight;
				img.style.transform = 'translate3d(0,' + ( progress * -6 - 6 ).toFixed( 2 ) + '%,0)';
			} );
			ticking = false;
		};
		window.addEventListener( 'scroll', function () {
			if ( ! ticking ) { ticking = true; window.requestAnimationFrame( update ); }
		}, { passive: true } );
		update();
	}
} )();
