/* Product, cart and checkout UX: sticky action bar, quantity stepper, copy part number. */
( function ( $ ) {
	'use strict';

	var t = window.yadakUx || {};
	var bar = document.querySelector( '.yadak-actionbar' );

	/* Quantity stepper (− / +) around WooCommerce quantity inputs. */
	function stepper( root ) {
		$( root ).find( '.quantity' ).each( function () {
			var wrap = this;
			var input = wrap.querySelector( 'input.qty' );
			if ( ! input || input.type === 'hidden' || wrap.querySelector( '.yadak-qty' ) ) {
				return;
			}
			function make( label, delta, text ) {
				var b = document.createElement( 'button' );
				b.type = 'button';
				b.className = 'yadak-qty';
				b.setAttribute( 'aria-label', label );
				b.textContent = text;
				b.addEventListener( 'click', function () {
					var step = parseFloat( input.step ) || 1;
					var min = input.min !== '' ? parseFloat( input.min ) : 0;
					var max = input.max !== '' ? parseFloat( input.max ) : Infinity;
					var v = ( parseFloat( input.value ) || 0 ) + delta * step;
					input.value = Math.max( min, Math.min( max, v ) );
					$( input ).trigger( 'change' );
				} );
				return b;
			}
			wrap.classList.add( 'has-stepper' );
			wrap.insertBefore( make( t.more || '+', 1, '+' ), input );
			wrap.appendChild( make( t.less || '−', -1, '−' ) );
		} );
	}
	stepper( document );
	$( document.body ).on( 'updated_cart_totals updated_wc_div', function () { stepper( document ); } );

	/* Copy buttons for part / OEM numbers in the spec list. */
	document.querySelectorAll( '.yadak-specs div' ).forEach( function ( row ) {
		var dt = row.querySelector( 'dt' );
		var dd = row.querySelector( 'dd' );
		if ( ! dt || ! dd || ! /شماره/.test( dt.textContent ) || ! navigator.clipboard ) {
			return;
		}
		var b = document.createElement( 'button' );
		b.type = 'button';
		b.className = 'yadak-copy';
		b.textContent = t.copy || 'Copy';
		b.addEventListener( 'click', function () {
			navigator.clipboard.writeText( dd.firstChild.textContent.trim() ).then( function () {
				b.textContent = t.copied || '✓';
				setTimeout( function () { b.textContent = t.copy || 'Copy'; }, 1500 );
			} );
		} );
		dd.appendChild( b );
	} );

	if ( ! bar ) {
		return;
	}
	var mode = bar.getAttribute( 'data-mode' );
	var btn = bar.querySelector( '.yadak-actionbar__btn' );
	var price = bar.querySelector( '.yadak-actionbar__price' );

	if ( mode === 'product' ) {
		var form = document.querySelector( 'form.cart' );
		var real = form && form.querySelector( '.single_add_to_cart_button' );
		if ( ! real ) {
			return;
		}
		// Show the bar only while the real button is off screen.
		new IntersectionObserver( function ( entries ) {
			bar.hidden = entries[ 0 ].isIntersecting;
		} ).observe( real );
		btn.addEventListener( 'click', function () {
			if ( form.classList.contains( 'variations_form' ) ) {
				form.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				return;
			}
			real.click();
		} );
	}

	if ( mode === 'checkout' ) {
		btn.addEventListener( 'click', function () {
			var place = document.getElementById( 'place_order' );
			var terms = document.getElementById( 'terms' );
			// Unticked terms box: take the user to it instead of returning a form error at the top.
			if ( terms && ! terms.checked ) {
				var row = terms.closest( '.form-row' ) || terms;
				row.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				row.classList.remove( 'yadak-attention' );
				void row.offsetWidth;
				row.classList.add( 'yadak-attention' );
				terms.focus( { preventScroll: true } );
				return;
			}
			if ( place ) {
				place.click();
			}
		} );
		$( document.body ).on( 'updated_checkout', function () {
			var total = document.querySelector( '#order_review .order-total .woocommerce-Price-amount' );
			if ( total ) {
				price.innerHTML = total.outerHTML;
				var mini = document.querySelector( '.yadak-mini-summary__total' );
				if ( mini ) {
					mini.innerHTML = total.outerHTML;
				}
			}
		} );
	}

	if ( mode === 'cart' ) {
		$( document.body ).on( 'updated_cart_totals', function () {
			var total = document.querySelector( '.cart_totals .order-total .woocommerce-Price-amount' );
			if ( total ) {
				price.innerHTML = total.outerHTML;
			}
		} );
	}
} )( jQuery );
