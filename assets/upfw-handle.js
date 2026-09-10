/**
 * El nombre público, mientras se escribe.
 *
 * Dos cosas distintas: la dirección que va a quedar —que se calcula acá mismo,
 * sin pedirle nada al servidor, para que no haya un parpadeo por cada tecla— y
 * si está libre, que sólo el servidor sabe. Lo segundo se consulta solo, con un
 * respiro después de la última tecla, y también a pedido desde el enlace.
 */
( function () {
	'use strict';

	var datos  = window.upfwHandle || null;
	var campo  = document.getElementById( 'upfw-handle' );
	var vista  = document.querySelector( '[data-upfw-handle-vista]' );
	var enlace = document.querySelector( '[data-upfw-handle-url]' );

	if ( ! datos || ! campo || ! vista || ! enlace ) {
		return;
	}

	var comprobar = document.querySelector( '[data-upfw-handle-check]' );
	var aviso     = document.querySelector( '[data-upfw-handle-aviso]' );
	var inicial   = campo.value;
	var espera    = null;
	var pedido    = 0;

	function limpiar( texto ) {
		var t = texto.toLowerCase().trim();

		// Los acentos se sacan salvo que el sitio los acepte. `normalize`
		// separa la letra de su tilde y después se tira la tilde.
		if ( ! datos.unicode ) {
			t = t.normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' );
		}

		t = t.replace( /\s+/g, '-' );
		t = datos.unicode ? t.replace( /[^\p{L}\p{N}._-]/gu, '' ) : t.replace( /[^a-z0-9._-]/g, '' );

		return t.replace( /-{2,}/g, '-' ).replace( /^-+|-+$/g, '' );
	}

	function decir( texto, estado ) {
		if ( ! aviso ) {
			return;
		}

		aviso.textContent = texto;
		aviso.className   = '' === texto ? '' : 'upfw-handle__aviso is-' + estado;
	}

	function pintar() {
		var limpio = limpiar( campo.value );
		var url    = datos.base + limpio + '/';

		vista.hidden        = '' === limpio;
		enlace.textContent  = url;
		enlace.href         = url;

		if ( comprobar ) {
			comprobar.href = url;
		}
	}

	function consultar() {
		var limpio = limpiar( campo.value );

		if ( '' === limpio ) {
			decir( '', '' );
			return;
		}

		var mio = ++pedido;
		var cuerpo = new URLSearchParams();

		cuerpo.append( 'action', 'upfw_handle_check' );
		cuerpo.append( 'nonce', datos.nonce );
		cuerpo.append( 'handle', campo.value );

		decir( datos.checking, 'esperando' );

		fetch( datos.ajax, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: cuerpo.toString()
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( r ) {
				// Una respuesta vieja que llega tarde no puede pisar a la nueva.
				if ( mio !== pedido ) {
					return;
				}

				if ( ! r || ! r.success ) {
					decir( datos.error, 'esperando' );
					return;
				}

				decir( r.data.motivo, r.data.free ? 'libre' : 'ocupado' );
			} )
			.catch( function () {
				if ( mio === pedido ) {
					decir( datos.error, 'esperando' );
				}
			} );
	}

	campo.addEventListener( 'input', function () {
		pintar();
		window.clearTimeout( espera );

		// Mientras no se cambió nada no hay nada que avisar: entrar al perfil y
		// que el propio nombre aparezca marcado como ocupado sería absurdo.
		if ( campo.value === inicial ) {
			decir( '', '' );
			return;
		}

		espera = window.setTimeout( consultar, 600 );
	} );

	if ( comprobar ) {
		comprobar.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			window.clearTimeout( espera );
			consultar();
		} );
	}

	pintar();
}() );
