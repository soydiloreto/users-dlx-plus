<?php
/**
 * Los logos de cada red, en SVG.
 *
 * Van inline y no como archivos: son doce dibujos de menos de un kilobyte, y
 * así heredan el tamaño y el color del botón sin una petición más ni un sprite
 * que mantener.
 *
 * Cada marca es de su dueño. Se usan para lo único que sus guías permiten sin
 * pedir permiso: identificar el botón con el que se entra a ese servicio. Por
 * eso los que tienen color de marca lo conservan —el logo de Google no se
 * pinta de otro color— y los que son una silueta usan `currentColor`, que es
 * como esas mismas guías los admiten sobre fondos de color.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

/**
 * ¿El logo de esta red tiene color propio?
 *
 * Los de color no se recolorean nunca: se dibujan igual sobre un botón blanco
 * que sobre uno oscuro. Los de silueta toman el color del texto del botón.
 */
function upfw_sso_icon_is_colored( string $id ): bool {
	return in_array( $id, array( 'google', 'microsoft' ), true );
}

/**
 * El SVG de una red, listo para imprimir.
 *
 * @param string $id Identificador del proveedor.
 * @return string SVG, o cadena vacía si esa red no tiene logo.
 */
function upfw_sso_icon( string $id ): string {
	$paths = upfw_sso_icon_paths();

	if ( ! isset( $paths[ $id ] ) ) {
		return '';
	}

	return sprintf(
		'<svg class="upfw-social__logo" width="20" height="20" viewBox="0 0 24 24" fill="%1$s" aria-hidden="true" focusable="false">%2$s</svg>',
		upfw_sso_icon_is_colored( $id ) ? 'none' : 'currentColor',
		$paths[ $id ]
	);
}

/**
 * El dibujo de cada marca.
 *
 * @return array<string, string>
 */
function upfw_sso_icon_paths(): array {
	$paths = array(

		'google'    => '<path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.47a5.53 5.53 0 0 1-2.4 3.58v3h3.86c2.26-2.09 3.56-5.17 3.56-8.82z"/>'
			. '<path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96H1.29v3.09A12 12 0 0 0 12 24z"/>'
			. '<path fill="#FBBC05" d="M5.27 14.29A7.2 7.2 0 0 1 4.89 12c0-.8.14-1.57.38-2.29V6.62H1.29A12 12 0 0 0 0 12c0 1.94.46 3.77 1.29 5.38l3.98-3.09z"/>'
			. '<path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.7 0 3.99 2.47 1.29 6.62l3.98 3.09C6.22 6.86 8.87 4.75 12 4.75z"/>',

		'microsoft' => '<path fill="#F25022" d="M2 2h9.4v9.4H2z"/>'
			. '<path fill="#7FBA00" d="M12.6 2H22v9.4h-9.4z"/>'
			. '<path fill="#00A4EF" d="M2 12.6h9.4V22H2z"/>'
			. '<path fill="#FFB900" d="M12.6 12.6H22V22h-9.4z"/>',

		'linkedin'  => '<path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5M2.5 9.5h5V21h-5zM10 9.5h4.8v1.6h.1c.7-1.2 2.3-2.1 4.1-2.1 4.4 0 5 2.6 5 6.1V21h-5v-5.1c0-1.5 0-3.5-2.1-3.5s-2.4 1.7-2.4 3.4V21h-5z"/>',

		'twitter'   => '<path d="M18.9 1.2h3.7l-8.1 9.2 9.5 12.4h-7.4l-5.8-7.6-6.7 7.6H.4l8.6-9.9L0 1.2h7.6l5.2 6.9zm-1.3 19.4h2L6.5 3.3H4.3z"/>',

		'facebook'  => '<path d="M23 12a11 11 0 1 0-12.7 10.9v-7.7H7.5V12h2.8V9.6c0-2.8 1.6-4.3 4.2-4.3 1.2 0 2.5.2 2.5.2v2.7h-1.4c-1.4 0-1.8.9-1.8 1.7V12h3.1l-.5 3.2h-2.6v7.7A11 11 0 0 0 23 12"/>',

		'github'    => '<path d="M12 .3a12 12 0 0 0-3.8 23.4c.6.1.8-.3.8-.6v-2c-3.3.7-4-1.6-4-1.6-.6-1.4-1.4-1.8-1.4-1.8-1-.7.1-.7.1-.7 1.2.1 1.8 1.2 1.8 1.2 1.1 1.8 2.8 1.3 3.5 1 .1-.8.4-1.3.8-1.6-2.7-.3-5.5-1.3-5.5-5.9 0-1.3.5-2.4 1.2-3.2-.1-.3-.5-1.5.1-3.2 0 0 1-.3 3.3 1.2a11.5 11.5 0 0 1 6 0C17.2 4.9 18.2 5.2 18.2 5.2c.6 1.7.2 2.9.1 3.2.8.8 1.2 1.9 1.2 3.2 0 4.6-2.8 5.6-5.5 5.9.4.4.8 1.1.8 2.2v3.3c0 .3.2.7.8.6A12 12 0 0 0 12 .3"/>',

		'wordpress' => '<path d="M12 0a12 12 0 1 0 0 24 12 12 0 0 0 0-24m0 1.2a10.7 10.7 0 0 1 8.5 4.2h-.4c-1 0-1.8.9-1.8 1.9 0 .9.5 1.6 1 2.5.4.7.9 1.6.9 3 0 .9-.4 2-.8 3.5l-1.1 3.6-3.9-11.6c.6 0 1.2-.1 1.2-.1.6-.1.5-.9-.1-.9 0 0-1.7.1-2.9.1-1.1 0-2.8-.1-2.8-.1-.6 0-.7.9-.1.9 0 0 .5 0 1.1.1l1.7 4.6-2.4 7.1-3.9-11.7c.6 0 1.2-.1 1.2-.1.6-.1.5-.9-.1-.9 0 0-1.7.1-2.9.1h-.7A10.7 10.7 0 0 1 12 1.2M1.2 12c0-1.6.3-3 .9-4.4l5.1 14.1A10.8 10.8 0 0 1 1.2 12M12 22.8c-1 0-2-.2-3-.5l3.2-9.4 3.3 9.1v.2c-1.1.4-2.3.6-3.5.6m9.5-15.9c.8 1.5 1.3 3.3 1.3 5.1 0 4-2.2 7.5-5.4 9.3l3.3-9.5c.6-1.6.8-2.8.8-3.9v-1"/>',

		'yahoo'     => '<path d="M1 5.4h4.6l2.7 6.9 2.7-6.9h4.5L9.7 21.7H5.1l1.9-4.4zm17.6 8.7c1.3 0 2.4 1.1 2.4 2.4s-1.1 2.4-2.4 2.4-2.4-1.1-2.4-2.4 1.1-2.4 2.4-2.4M17.1 2.3H22l-4.4 10.2h-3.4z"/>',

		'twitch'    => '<path d="M4.3 0 1.7 4.7v16.5h5.6V24h3l2.8-2.8h4.5L23.3 16V0zm16.7 15-3.2 3.2h-4.9l-2.8 2.8v-2.8H6.4V1.9H21zM17.5 6v5.8h-1.9V6zm-5.2 0v5.8h-1.9V6z"/>',

		'discord'   => '<path d="M19.3 5.3A16.9 16.9 0 0 0 15.1 4l-.2.4a15.7 15.7 0 0 1 3.7 1.2A12.6 12.6 0 0 0 12 4.4a12.7 12.7 0 0 0-6.6 1.2 15.6 15.6 0 0 1 3.7-1.2L8.9 4a16.9 16.9 0 0 0-4.2 1.3C2 9.2 1.3 13 1.6 16.7A17 17 0 0 0 6.8 19l.9-1.3a11 11 0 0 1-1.7-.8l.4-.3a12.1 12.1 0 0 0 11.2 0l.4.3a11 11 0 0 1-1.7.8l.9 1.3a17 17 0 0 0 5.2-2.3c.4-4.3-.7-8-3.1-11.4M8.5 14.5c-1 0-1.9-.9-1.9-2.1s.8-2.1 1.9-2.1 1.9 1 1.9 2.1-.8 2.1-1.9 2.1m6.9 0c-1 0-1.9-.9-1.9-2.1s.8-2.1 1.9-2.1 1.9 1 1.9 2.1-.8 2.1-1.9 2.1"/>',

		'gitlab'    => '<path d="m23.6004 9.5927-.0337-.0862L20.3.9814a.851.851 0 0 0-.3362-.405.8748.8748 0 0 0-.9997.0539.8748.8748 0 0 0-.29.4399l-2.2055 6.748H7.5375l-2.2057-6.748a.8573.8573 0 0 0-.29-.4412.8748.8748 0 0 0-.9997-.0537.8585.8585 0 0 0-.3361.405L.4332 9.5015l-.0325.0862a6.0657 6.0657 0 0 0 2.0119 7.0105l.0113.0087.03.0213 4.976 3.7264 2.462 1.8633 1.4995 1.1321a1.0085 1.0085 0 0 0 1.2197 0l1.4995-1.1321 2.4619-1.8633 5.006-3.7489.0125-.01a6.0682 6.0682 0 0 0 2.0094-7.003z"/>',

		'amazon'    => '<path d="M14.7 12.4c-.5.4-1.2.4-1.8.4-1 0-1.9-.4-1.9-1.5 0-1.4 1.3-1.9 2.9-1.9h.8v-.5c0-1.1-.1-2-1.4-2-1 0-1.5.7-1.6 1.5l-1.9-.2c.3-2.1 2.2-2.8 3.8-2.8.9 0 2 .2 2.7.9.9.8.8 2 .8 3.2v2.9c0 .9.4 1.2.7 1.7 0 .2 0 .4-.1.5-.4.3-1.1.9-1.4 1.2h-.1c-.5-.4-.6-.6-1-1zm-.1-3.7h-.5c-1.2 0-2.5.3-2.5 1.7 0 .7.4 1.2 1 1.2.5 0 .9-.3 1.2-.8.3-.6.3-1.1.3-1.8zM20.6 18.2C18.3 20 15 21 12.2 21c-3.9 0-7.4-1.5-10.1-3.9-.2-.2 0-.5.2-.3 2.9 1.7 6.5 2.7 10.2 2.7 2.5 0 5.2-.5 7.7-1.6.4-.2.7.2.4.3M21.6 17c-.3-.4-2-.2-2.8-.1-.2 0-.3-.2-.1-.3 1.3-.9 3.5-.7 3.8-.3.3.4-.1 2.6-1.3 3.7-.2.2-.4.1-.3-.1.3-.8.9-2.5.7-2.9"/>',
	);

	/**
	 * Filtra los logos de las redes.
	 *
	 * @param array<string, string> $paths
	 */
	return apply_filters( 'upfw_sso_icon_paths', $paths );
}
