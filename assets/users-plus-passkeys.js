/**
 * Passkeys: el lado del navegador.
 *
 * Es poco código porque casi todo lo hace el navegador. Acá sólo se traducen
 * las cadenas que manda el servidor a los ArrayBuffer que espera la API, y al
 * revés cuando vuelve la respuesta.
 *
 * El alta usa `getPublicKey()`, que devuelve la clave ya en formato DER: así
 * el servidor no tiene que interpretar el objeto de atestación, que es la
 * parte más frágil de WebAuthn.
 */
( function () {
	'use strict';

	var datos = window.usersPlusPasskeys || null;

	if ( ! datos || ! window.PublicKeyCredential ) {
		return;
	}

	function deB64url( texto ) {
		var normal = texto.replace( /-/g, '+' ).replace( /_/g, '/' );
		var bytes  = atob( normal );
		var buffer = new Uint8Array( bytes.length );

		for ( var i = 0; i < bytes.length; i++ ) {
			buffer[ i ] = bytes.charCodeAt( i );
		}

		return buffer.buffer;
	}

	function aB64url( buffer ) {
		var bytes = new Uint8Array( buffer );
		var texto = '';

		for ( var i = 0; i < bytes.length; i++ ) {
			texto += String.fromCharCode( bytes[ i ] );
		}

		return btoa( texto ).replace( /\+/g, '-' ).replace( /\//g, '_' ).replace( /=+$/, '' );
	}

	function pedir( cuerpo ) {
		cuerpo.action = 'users_plus_passkeys';
		cuerpo.nonce  = datos.nonce;

		return fetch( datos.ajax, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams( cuerpo ).toString()
		} ).then( function ( r ) { return r.json(); } );
	}

	function avisar( caja, texto, error ) {
		if ( ! caja ) {
			window.alert( texto );
			return;
		}

		caja.textContent = texto;
		caja.className = 'users-plus-notice users-plus-notice--' + ( error ? 'error' : 'ok' );
		caja.hidden = false;
	}

	/* ── Alta ─────────────────────────────────────────────────────── */

	function registrar( boton ) {
		var caja = document.querySelector( '[data-users-plus-passkey-aviso]' );

		boton.disabled = true;

		pedir( { step: 'register-options' } ).then( function ( r ) {
			if ( ! r.success ) {
				throw new Error( r.data.message );
			}

			var o = r.data;
			var opciones = {
				challenge: deB64url( o.challenge ),
				rp: o.rp,
				user: {
					id: deB64url( o.user.id ),
					name: o.user.name,
					displayName: o.user.displayName
				},
				// -7 es ECDSA P-256 y -257 es RSA. Son las dos que verifica el
				// servidor; ofrecer otras sería ofrecer algo que va a fallar.
				pubKeyCredParams: [
					{ type: 'public-key', alg: -7 },
					{ type: 'public-key', alg: -257 }
				],
				excludeCredentials: ( o.excludeCredentials || [] ).map( function ( c ) {
					return { id: deB64url( c.id ), type: 'public-key' };
				} ),
				authenticatorSelection: {
					userVerification: o.userVerification,
					residentKey: o.residentKey
				},
				timeout: 120000
			};

			if ( o.authenticatorAttachment ) {
				opciones.authenticatorSelection.authenticatorAttachment = o.authenticatorAttachment;
			}

			return navigator.credentials.create( { publicKey: opciones } );
		} ).then( function ( cred ) {
			var clave = cred.response.getPublicKey ? cred.response.getPublicKey() : null;

			if ( ! clave ) {
				throw new Error( datos.textos.viejo );
			}

			// El nombre que escribió la persona. Si lo dejó vacío el
			// servidor propone el dispositivo, así que acá no se inventa nada.
			var nombre = document.querySelector( '[data-users-plus-passkey-label]' );

			return pedir( {
				step: 'register',
				id: cred.id,
				label: nombre ? nombre.value : '',
				publicKey: aB64url( clave ),
				algorithm: cred.response.getPublicKeyAlgorithm(),
				clientDataJSON: new TextDecoder().decode( cred.response.clientDataJSON )
			} );
		} ).then( function ( r ) {
			if ( ! r.success ) {
				throw new Error( r.data.message );
			}

			window.location.reload();
		} ).catch( function ( e ) {
			boton.disabled = false;
			avisar( caja, e.message || datos.textos.error, true );
		} );
	}

	/* ── Ingreso ──────────────────────────────────────────────────── */

	function entrar( boton ) {
		var caja = document.querySelector( '[data-users-plus-passkey-aviso]' );

		boton.disabled = true;

		pedir( { step: 'login-options' } ).then( function ( r ) {
			if ( ! r.success ) {
				throw new Error( r.data.message );
			}

			return navigator.credentials.get( {
				publicKey: {
					challenge: deB64url( r.data.challenge ),
					rpId: r.data.rpId,
					userVerification: r.data.userVerification,
					timeout: 120000
				}
			} );
		} ).then( function ( cred ) {
			return pedir( {
				step: 'login',
				id: cred.id,
				clientDataJSON: new TextDecoder().decode( cred.response.clientDataJSON ),
				authenticatorData: aB64url( cred.response.authenticatorData ),
				signature: aB64url( cred.response.signature )
			} );
		} ).then( function ( r ) {
			if ( ! r.success ) {
				throw new Error( r.data.message );
			}

			window.location.href = r.data.redirect;
		} ).catch( function ( e ) {
			boton.disabled = false;
			avisar( caja, e.message || datos.textos.error, true );
		} );
	}

	document.addEventListener( 'click', function ( evento ) {
		var alta = evento.target.closest( '[data-users-plus-passkey="register"]' );

		if ( alta ) {
			evento.preventDefault();
			registrar( alta );
			return;
		}

		var ingreso = evento.target.closest( '[data-users-plus-passkey="login"]' );

		if ( ingreso ) {
			evento.preventDefault();
			entrar( ingreso );
		}
	} );
}() );
