/**
 * La ficha de un campo muestra sólo lo que ese tipo necesita.
 *
 * Un campo de fecha no tiene opciones y uno de país no se configura: la lista
 * la trae el plugin. Ofrecer cajas que no hacen nada es pedirle a quien
 * configura que adivine cuáles importan.
 *
 * Sin JavaScript se ven todas, que es lo peor que puede pasar: el formulario
 * sigue funcionando y cada fila dice para qué tipo es.
 */
( function () {
	'use strict';

	var tipo = document.getElementById( 'users-plus-type' );

	if ( ! tipo ) {
		return;
	}

	var filas = document.querySelectorAll( '.users-plus-si-tipo' );

	function revisar() {
		var elegido = tipo.value;

		filas.forEach( function ( fila ) {
			var para = ( fila.getAttribute( 'data-tipo' ) || '' ).split( ' ' );

			fila.hidden = -1 === para.indexOf( elegido );
		} );
	}

	tipo.addEventListener( 'change', revisar );
	revisar();
}() );

/**
 * La prueba en vivo se abre en una ventana chica, no en una pestaña.
 *
 * El enlace ya funciona sin esto —tiene target—, así que acá sólo se le pone
 * el tamaño. Si el proveedor bloquea la ventana emergente, se deja seguir el
 * clic y la prueba se abre igual en una pestaña.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( evento ) {
		var enlace = evento.target.closest( '[data-users-plus-popup]' );

		if ( ! enlace ) {
			return;
		}

		var medidas = ( enlace.getAttribute( 'data-users-plus-popup' ) || '' ).split( 'x' );
		var ancho   = parseInt( medidas[ 0 ], 10 ) || 600;
		var alto    = parseInt( medidas[ 1 ], 10 ) || 740;

		var ventana = window.open(
			enlace.href,
			enlace.target || 'users-plus-popup',
			'width=' + ancho + ',height=' + alto + ',scrollbars=yes,resizable=yes'
		);

		if ( ventana ) {
			evento.preventDefault();
			ventana.focus();
		}
	} );
}() );

/**
 * La vista previa de los botones sigue a los selectores mientras se eligen.
 *
 * Sólo cambia clases sobre el marcado de verdad: no hay una copia del diseño
 * acá adentro que se pueda desincronizar del sitio. El texto no se previsualiza
 * en vivo —hay que guardar para verlo— porque el nombre de cada red lo pone el
 * servidor y armarlo de nuevo acá sería justamente esa copia.
 */
( function () {
	'use strict';

	var lienzo = document.querySelector( '.users-plus-botones__lienzo .users-plus-socials' );

	if ( ! lienzo ) {
		return;
	}

	var mapa = {
		skin:  [ 'brand', 'light', 'dark' ],
		shape: [ 'rounded', 'pill', 'square' ],
		show:  [ 'icon-text', 'icon' ],
		cols:  [ 'cols-0', 'cols-1', 'cols-2' ]
	};

	function aplicar( grupo, valor ) {
		var clases = mapa[ grupo ];
		var nueva  = 'cols' === grupo ? 'cols-' + valor : valor;

		clases.forEach( function ( clase ) {
			lienzo.classList.toggle( 'users-plus-socials--' + clase, clase === nueva );
		} );
	}

	document.querySelectorAll( '[data-users-plus-vista]' ).forEach( function ( campo ) {
		campo.addEventListener( 'change', function () {
			aplicar( campo.getAttribute( 'data-users-plus-vista' ), campo.value );
		} );
	} );

	// El fondo del lienzo. Un acabado oscuro sobre blanco se ve bárbaro y
	// desaparece sobre el fondo oscuro del sitio; hay que poder mirar los dos.
	var lienzoCaja = document.querySelector( '.users-plus-botones__lienzo' );

	document.querySelectorAll( '[data-users-plus-fondo]' ).forEach( function ( boton ) {
		boton.addEventListener( 'click', function () {
			var oscuro = 'oscuro' === boton.getAttribute( 'data-users-plus-fondo' );

			lienzoCaja.classList.toggle( 'users-plus-botones__lienzo--oscuro', oscuro );

			document.querySelectorAll( '[data-users-plus-fondo]' ).forEach( function ( otro ) {
				otro.setAttribute( 'aria-pressed', String( otro === boton ) );
			} );
		} );
	} );
}() );

/**
 * Arrastrar para ordenar las secciones de la cuenta.
 *
 * El orden viaja en los <input hidden> de cada renglón, así que moverlos en el
 * DOM ya deja el formulario listo: al soltar se manda solo y la página vuelve
 * con el orden nuevo. Sin JavaScript no hay arrastre y queda el botón de
 * guardar, que es lo que hace que esto también se pueda usar con el teclado.
 *
 * Es HTML5 nativo y no jQuery UI: son treinta líneas y no arrastra 90 KB de
 * dependencia para mover cinco renglones.
 */
( function () {
	'use strict';

	var lista = document.querySelector( '[data-users-plus-sortable]' );

	if ( ! lista ) {
		return;
	}

	var arrastrado = null;

	lista.querySelectorAll( 'li' ).forEach( function ( fila ) {
		fila.draggable = true;

		fila.addEventListener( 'dragstart', function ( e ) {
			arrastrado = fila;
			fila.classList.add( 'is-dragging' );
			e.dataTransfer.effectAllowed = 'move';
			// Firefox no arranca el arrastre sin esto.
			e.dataTransfer.setData( 'text/plain', '' );
		} );

		fila.addEventListener( 'dragend', function () {
			fila.classList.remove( 'is-dragging' );

			if ( arrastrado ) {
				arrastrado = null;
				lista.closest( 'form' ).submit();
			}
		} );

		fila.addEventListener( 'dragover', function ( e ) {
			if ( ! arrastrado || arrastrado === fila ) {
				return;
			}

			e.preventDefault();

			var caja   = fila.getBoundingClientRect();
			var arriba = e.clientY < caja.top + caja.height / 2;

			lista.insertBefore( arrastrado, arriba ? fila : fila.nextSibling );
		} );
	} );
}() );
