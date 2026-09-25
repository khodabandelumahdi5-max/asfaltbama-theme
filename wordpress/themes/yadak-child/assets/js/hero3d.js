/*
 * Interactive 3D car for the homepage hero.
 *
 * A stylized SUV built from three.js primitives (no model download): drag to
 * rotate; hovering a part highlights it; clicking headlights, taillights,
 * bumpers, mirrors, grille or roof rack opens that category. The chips under
 * the canvas do the same for keyboard and screen-reader users, and are all
 * that remains if WebGL is unavailable.
 *
 * Config: window.yadak3d = { module: url-of-three.module.js, parts: { key: { url, label } } }
 */
( function () {
	'use strict';

	var stage = document.querySelector( '.yadak-stage' );
	var cfg = window.yadak3d;
	if ( ! stage || ! cfg ) {
		return;
	}
	var canvasWrap = stage.querySelector( '.yadak-stage__canvas' );
	var tip = stage.querySelector( '.yadak-stage__tip' );
	var chips = stage.querySelectorAll( '[data-part]' );
	var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function webglOk() {
		try {
			var c = document.createElement( 'canvas' );
			return !! ( window.WebGLRenderingContext && ( c.getContext( 'webgl2' ) || c.getContext( 'webgl' ) ) );
		} catch ( e ) {
			return false;
		}
	}
	if ( ! webglOk() ) {
		stage.classList.add( 'is-fallback' );
		return;
	}

	function start() {
		import( cfg.module ).then( build ).catch( function () {
			stage.classList.add( 'is-fallback' );
		} );
	}

	// Load three.js only when the hero is on screen and the browser is idle.
	if ( 'IntersectionObserver' in window ) {
		var io = new IntersectionObserver( function ( entries ) {
			if ( entries[ 0 ].isIntersecting ) {
				io.disconnect();
				if ( window.requestIdleCallback ) {
					window.requestIdleCallback( start, { timeout: 1500 } );
				} else {
					setTimeout( start, 200 );
				}
			}
		} );
		io.observe( stage );
	} else {
		start();
	}

	function build( THREE ) {
		var width = canvasWrap.clientWidth;
		var height = canvasWrap.clientHeight;

		var renderer = new THREE.WebGLRenderer( { antialias: true, alpha: true } );
		renderer.setPixelRatio( Math.min( window.devicePixelRatio || 1, 2 ) );
		renderer.setSize( width, height );
		renderer.shadowMap.enabled = true;
		renderer.shadowMap.type = THREE.PCFSoftShadowMap;
		renderer.toneMapping = THREE.ACESFilmicToneMapping;
		renderer.outputColorSpace = THREE.SRGBColorSpace;
		canvasWrap.appendChild( renderer.domElement );
		renderer.domElement.setAttribute( 'aria-hidden', 'true' );

		var scene = new THREE.Scene();
		var camera = new THREE.PerspectiveCamera( 32, width / height, 0.1, 100 );
		camera.position.set( 7.2, 3.1, 7.2 );
		camera.lookAt( 0, 0.75, 0 );

		scene.add( new THREE.HemisphereLight( 0xe8f0ff, 0x2a3346, 2.2 ) );
		var sun = new THREE.DirectionalLight( 0xffffff, 3.2 );
		sun.position.set( 5, 9, 4 );
		sun.castShadow = true;
		sun.shadow.mapSize.set( 1024, 1024 );
		sun.shadow.camera.left = -5;
		sun.shadow.camera.right = 5;
		sun.shadow.camera.top = 5;
		sun.shadow.camera.bottom = -5;
		scene.add( sun );
		var rim = new THREE.DirectionalLight( 0xff9a5a, 1.2 );
		rim.position.set( -6, 3, -5 );
		scene.add( rim );

		// Soft turntable.
		var floor = new THREE.Mesh(
			new THREE.CircleGeometry( 3.6, 64 ),
			new THREE.ShadowMaterial( { opacity: 0.35 } )
		);
		floor.rotation.x = -Math.PI / 2;
		floor.receiveShadow = true;
		scene.add( floor );
		var ring = new THREE.Mesh(
			new THREE.RingGeometry( 3.3, 3.38, 96 ),
			new THREE.MeshBasicMaterial( { color: 0xea580c, transparent: true, opacity: 0.55 } )
		);
		ring.rotation.x = -Math.PI / 2;
		ring.position.y = 0.005;
		scene.add( ring );

		var car = new THREE.Group();
		scene.add( car );

		var paint = new THREE.MeshPhysicalMaterial( { color: 0x4d6f9c, metalness: 0.55, roughness: 0.3, clearcoat: 1, clearcoatRoughness: 0.08 } );
		var trim = new THREE.MeshStandardMaterial( { color: 0x1b1f27, metalness: 0.2, roughness: 0.55 } );
		var glass = new THREE.MeshPhysicalMaterial( { color: 0x0b1320, metalness: 0.1, roughness: 0.05, transparent: true, opacity: 0.88 } );
		var chrome = new THREE.MeshStandardMaterial( { color: 0xc9d2de, metalness: 1, roughness: 0.2 } );
		var tyre = new THREE.MeshStandardMaterial( { color: 0x111317, roughness: 0.9 } );

		var L = 4.3; // length (x)
		var W = 1.86; // width (z)

		function extrude( points, depth, mat, bevel ) {
			var shape = new THREE.Shape();
			shape.moveTo( points[ 0 ][ 0 ], points[ 0 ][ 1 ] );
			for ( var i = 1; i < points.length; i++ ) {
				shape.lineTo( points[ i ][ 0 ], points[ i ][ 1 ] );
			}
			var geo = new THREE.ExtrudeGeometry( shape, { depth: depth, bevelEnabled: true, bevelThickness: bevel, bevelSize: bevel, bevelSegments: 4, steps: 1 } );
			geo.translate( 0, 0, -depth / 2 );
			var mesh = new THREE.Mesh( geo, mat );
			mesh.castShadow = true;
			mesh.receiveShadow = true;
			return mesh;
		}

		// Body side profile (front at +x).
		var body = extrude( [
			[ -2.05, 0.42 ], [ 2.05, 0.42 ], [ 2.12, 0.62 ], [ 2.1, 0.95 ], [ 1.25, 1.12 ],
			[ -1.95, 1.12 ], [ -2.12, 0.98 ], [ -2.12, 0.55 ]
		], W - 0.16, paint, 0.08 );
		car.add( body );

		// Greenhouse (glass + roof).
		var cabin = extrude( [
			[ -1.75, 1.1 ], [ 1.05, 1.1 ], [ 0.45, 1.68 ], [ -1.55, 1.7 ], [ -1.82, 1.2 ]
		], W - 0.36, glass, 0.06 );
		car.add( cabin );
		var roof = new THREE.Mesh( new THREE.BoxGeometry( 1.95, 0.06, W - 0.3 ), paint );
		roof.position.set( -0.55, 1.74, 0 );
		roof.castShadow = true;
		car.add( roof );

		// Wheels.
		[ [ 1.32, 1 ], [ 1.32, -1 ], [ -1.35, 1 ], [ -1.35, -1 ] ].forEach( function ( p ) {
			var wheel = new THREE.Group();
			var t = new THREE.Mesh( new THREE.CylinderGeometry( 0.42, 0.42, 0.3, 40 ), tyre );
			t.rotation.x = Math.PI / 2;
			t.castShadow = true;
			var rimMesh = new THREE.Mesh( new THREE.CylinderGeometry( 0.27, 0.27, 0.32, 10 ), chrome );
			rimMesh.rotation.x = Math.PI / 2;
			wheel.add( t, rimMesh );
			wheel.position.set( p[ 0 ], 0.42, p[ 1 ] * ( W / 2 - 0.1 ) );
			car.add( wheel );
		} );

		// ---------- Clickable parts ----------
		var parts = [];
		function part( key, mesh ) {
			if ( ! cfg.parts[ key ] ) {
				return mesh;
			}
			mesh.userData.part = key;
			mesh.userData.baseEmissive = mesh.material.emissive ? mesh.material.emissive.getHex() : 0;
			mesh.userData.baseIntensity = mesh.material.emissiveIntensity || 0;
			parts.push( mesh );
			return mesh;
		}
		function lightMat( color, intensity ) {
			return new THREE.MeshStandardMaterial( { color: color, emissive: color, emissiveIntensity: intensity, roughness: 0.25 } );
		}

		[ 1, -1 ].forEach( function ( side ) {
			var hl = part( 'headlights', new THREE.Mesh( new THREE.BoxGeometry( 0.12, 0.13, 0.46 ), lightMat( 0xfff4dc, 1.6 ) ) );
			hl.position.set( 2.13, 0.86, side * 0.6 );
			car.add( hl );

			var tl = part( 'taillights', new THREE.Mesh( new THREE.BoxGeometry( 0.1, 0.16, 0.34 ), lightMat( 0xff2a2a, 1.2 ) ) );
			tl.position.set( -2.15, 0.88, side * 0.68 );
			car.add( tl );

			var fog = part( 'fog', new THREE.Mesh( new THREE.CylinderGeometry( 0.07, 0.07, 0.06, 20 ), lightMat( 0xfff8e8, 0.9 ) ) );
			fog.rotation.z = Math.PI / 2;
			fog.position.set( 2.2, 0.5, side * 0.7 );
			car.add( fog );

			var mirror = part( 'mirrors', new THREE.Mesh( new THREE.BoxGeometry( 0.22, 0.14, 0.2 ), paint.clone() ) );
			mirror.position.set( 0.95, 1.18, side * ( W / 2 + 0.06 ) );
			mirror.castShadow = true;
			car.add( mirror );

			var bar = part( 'accessories', new THREE.Mesh( new THREE.BoxGeometry( 1.7, 0.05, 0.05 ), chrome.clone() ) );
			bar.position.set( -0.55, 1.86, side * 0.66 );
			car.add( bar );

			var step = part( 'steps', new THREE.Mesh( new THREE.BoxGeometry( 2.0, 0.05, 0.2 ), trim.clone() ) );
			step.position.set( 0, 0.33, side * ( W / 2 + 0.05 ) );
			car.add( step );
		} );
		[ -0.45, 0.35 ].forEach( function ( x ) {
			var cross = part( 'accessories', new THREE.Mesh( new THREE.BoxGeometry( 0.05, 0.05, 1.4 ), chrome.clone() ) );
			cross.position.set( x - 0.55, 1.88, 0 );
			car.add( cross );
		} );

		var frontBumper = part( 'front_bumper', new THREE.Mesh( new THREE.BoxGeometry( 0.26, 0.3, W - 0.1 ), trim.clone() ) );
		frontBumper.position.set( 2.12, 0.5, 0 );
		frontBumper.castShadow = true;
		car.add( frontBumper );

		var rearBumper = part( 'rear_bumper', new THREE.Mesh( new THREE.BoxGeometry( 0.24, 0.3, W - 0.1 ), trim.clone() ) );
		rearBumper.position.set( -2.14, 0.5, 0 );
		rearBumper.castShadow = true;
		car.add( rearBumper );

		var grille = part( 'grille', new THREE.Mesh( new THREE.BoxGeometry( 0.06, 0.22, 0.62 ), chrome.clone() ) );
		grille.position.set( 2.16, 0.78, 0 );
		car.add( grille );

		car.position.y = 0.02;
		car.rotation.y = -0.5;

		// ---------- Interaction ----------
		var raycaster = new THREE.Raycaster();
		var pointer = new THREE.Vector2();
		var hovered = null;
		var dragging = false;
		var moved = 0;
		var lastX = 0;
		var velocity = 0;
		var autoSpin = ! reduceMotion;
		var idleTimer = null;

		function setHover( key ) {
			parts.forEach( function ( m ) {
				var on = key && m.userData.part === key;
				if ( m.material.emissive ) {
					m.material.emissive.setHex( on ? 0xea580c : m.userData.baseEmissive );
					m.material.emissiveIntensity = on ? 0.9 : m.userData.baseIntensity;
				}
			} );
			chips.forEach( function ( chip ) {
				chip.classList.toggle( 'is-active', chip.getAttribute( 'data-part' ) === key );
			} );
			hovered = key;
			renderer.domElement.style.cursor = key ? 'pointer' : ( dragging ? 'grabbing' : 'grab' );
		}

		function pick( ev ) {
			var rect = renderer.domElement.getBoundingClientRect();
			pointer.x = ( ( ev.clientX - rect.left ) / rect.width ) * 2 - 1;
			pointer.y = -( ( ev.clientY - rect.top ) / rect.height ) * 2 + 1;
			raycaster.setFromCamera( pointer, camera );
			var hit = raycaster.intersectObjects( parts, false )[ 0 ];
			return hit ? hit.object.userData.part : null;
		}

		function showTip( key, ev ) {
			if ( ! tip ) {
				return;
			}
			if ( ! key ) {
				tip.hidden = true;
				return;
			}
			var rect = canvasWrap.getBoundingClientRect();
			tip.textContent = cfg.parts[ key ].label;
			tip.style.left = ( ev.clientX - rect.left ) + 'px';
			tip.style.top = ( ev.clientY - rect.top ) + 'px';
			tip.hidden = false;
		}

		function pauseSpin() {
			autoSpin = false;
			clearTimeout( idleTimer );
			if ( ! reduceMotion ) {
				idleTimer = setTimeout( function () {
					autoSpin = true;
				}, 4000 );
			}
		}

		var el = renderer.domElement;
		el.style.touchAction = 'pan-y';
		el.addEventListener( 'pointerdown', function ( ev ) {
			dragging = true;
			moved = 0;
			lastX = ev.clientX;
			velocity = 0;
			pauseSpin();
			el.setPointerCapture( ev.pointerId );
		} );
		el.addEventListener( 'pointermove', function ( ev ) {
			if ( dragging ) {
				var dx = ev.clientX - lastX;
				lastX = ev.clientX;
				moved += Math.abs( dx );
				velocity = dx * 0.008;
				car.rotation.y += velocity;
				return;
			}
			var key = pick( ev );
			if ( key !== hovered ) {
				setHover( key );
			}
			showTip( key, ev );
		} );
		el.addEventListener( 'pointerup', function ( ev ) {
			dragging = false;
			if ( moved < 6 ) {
				var key = pick( ev );
				if ( key && cfg.parts[ key ].url ) {
					window.location.href = cfg.parts[ key ].url;
				}
			}
		} );
		el.addEventListener( 'pointerleave', function () {
			setHover( null );
			showTip( null );
		} );

		chips.forEach( function ( chip ) {
			var key = chip.getAttribute( 'data-part' );
			chip.addEventListener( 'mouseenter', function () {
				pauseSpin();
				setHover( key );
			} );
			chip.addEventListener( 'focus', function () {
				pauseSpin();
				setHover( key );
			} );
			chip.addEventListener( 'mouseleave', function () {
				setHover( null );
			} );
			chip.addEventListener( 'blur', function () {
				setHover( null );
			} );
		} );

		function resize() {
			width = canvasWrap.clientWidth;
			height = canvasWrap.clientHeight;
			camera.aspect = width / height;
			// Pull back on narrow screens so the whole car fits.
			var d = width < 480 ? 8.2 : ( width < 700 ? 6.8 : 5.9 );
			camera.position.set( d, d * 0.43, d );
			camera.lookAt( 0, 0.8, 0 );
			camera.updateProjectionMatrix();
			renderer.setSize( width, height );
		}
		window.addEventListener( 'resize', resize );
		resize();

		var visible = true;
		if ( 'IntersectionObserver' in window ) {
			new IntersectionObserver( function ( e ) {
				visible = e[ 0 ].isIntersecting;
			} ).observe( stage );
		}

		stage.classList.add( 'is-ready' );
		renderer.setAnimationLoop( function () {
			if ( ! visible ) {
				return;
			}
			if ( ! dragging ) {
				if ( Math.abs( velocity ) > 0.0005 ) {
					car.rotation.y += velocity;
					velocity *= 0.94;
				} else if ( autoSpin ) {
					car.rotation.y += 0.0035;
				}
			}
			renderer.render( scene, camera );
		} );
	}
} )();
