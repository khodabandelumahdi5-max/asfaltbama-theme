/* Cascading make › model › variant selects for [yadak_vehicle_selector]. */
( function () {
	'use strict';

	var cache = {};

	function load( parent ) {
		if ( cache[ parent ] ) {
			return Promise.resolve( cache[ parent ] );
		}
		var url = window.yadakVehicles.endpoint + ( window.yadakVehicles.endpoint.indexOf( '?' ) === -1 ? '?' : '&' ) + 'parent=' + parent;
		return fetch( url, { credentials: 'same-origin' } )
			.then( function ( r ) { return r.ok ? r.json() : []; } )
			.then( function ( items ) { cache[ parent ] = items; return items; } )
			.catch( function () { return []; } );
	}

	function fill( select, items, value ) {
		var placeholder = select.options[ 0 ];
		select.innerHTML = '';
		select.appendChild( placeholder );
		items.forEach( function ( item ) {
			var opt = document.createElement( 'option' );
			opt.value = item.id;
			opt.textContent = item.name;
			select.appendChild( opt );
		} );
		select.disabled = items.length === 0;
		if ( value ) {
			select.value = String( value );
		}
	}

	function reset( selects, from ) {
		for ( var i = from; i < selects.length; i++ ) {
			fill( selects[ i ], [] );
		}
	}

	function setup( form ) {
		var selects = Array.prototype.slice.call( form.querySelectorAll( 'select[data-level]' ) );
		var preset = [];
		try {
			preset = JSON.parse( form.getAttribute( 'data-selected' ) || '[]' );
		} catch ( e ) {}

		selects.forEach( function ( select, level ) {
			select.addEventListener( 'change', function () {
				reset( selects, level + 1 );
				var next = selects[ level + 1 ];
				if ( select.value && next ) {
					load( select.value ).then( function ( items ) {
						fill( next, items );
					} );
				}
			} );
		} );

		// Restore the remembered vehicle.
		if ( preset.length ) {
			selects[ 0 ].value = String( preset[ 0 ] );
			var chain = Promise.resolve();
			preset.forEach( function ( id, level ) {
				var next = selects[ level + 1 ];
				if ( ! next ) {
					return;
				}
				chain = chain.then( function () {
					return load( id ).then( function ( items ) {
						fill( next, items, preset[ level + 1 ] );
					} );
				} );
			} );
		}
	}

	function init() {
		Array.prototype.forEach.call( document.querySelectorAll( 'form.yadak-vs' ), setup );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
