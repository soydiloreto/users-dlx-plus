<?php
/**
 * La IP de quien está del otro lado.
 *
 * `REMOTE_ADDR` es la IP de quien abrió la conexión TCP. Detrás de un proxy
 * —nginx, un balanceador, Cloudflare, el front end de Azure App Service— eso
 * es el proxy, no la persona: en un sitio así todas las sesiones quedan
 * guardadas con la misma IP interna y la pantalla de sesiones no sirve para
 * nada.
 *
 * La IP real viene en una cabecera, y las cabeceras las escribe el cliente:
 * confiar en ellas siempre sería dejar que cualquiera diga que es quien
 * quiera. Por eso sólo se les hace caso cuando `REMOTE_ADDR` es una dirección
 * privada o de loopback — o sea, cuando el pedido llegó por un proxy de la
 * propia red. En un servidor expuesto directo a internet, manda REMOTE_ADDR.
 *
 * Azure App Service, además, escribe la IP con el puerto pegado
 * («190.15.219.128:64110»). Se lo saca.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

/**
 * Las cabeceras donde puede venir la IP, en orden de confianza.
 *
 * @return array<int, string>
 */
function upfw_ip_headers(): array {
	/**
	 * Filtra qué cabeceras se miran para averiguar la IP del cliente.
	 *
	 * @param array<int, string> $headers
	 */
	return (array) apply_filters(
		'upfw_ip_headers',
		array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare.
			'HTTP_TRUE_CLIENT_IP',   // Akamai, Cloudflare Enterprise.
			'HTTP_X_REAL_IP',        // nginx.
			'HTTP_X_FORWARDED_FOR',  // El estándar de hecho.
		)
	);
}

/**
 * Limpia un valor de cabecera y devuelve la primera IP válida.
 *
 * X-Forwarded-For es una lista: «cliente, proxy1, proxy2». La primera es la
 * que abrió el pedido. También se le saca el puerto que le pega Azure y los
 * corchetes de una IPv6 con puerto.
 */
function upfw_ip_from( string $value ): string {
	foreach ( explode( ',', $value ) as $candidate ) {
		$candidate = trim( $candidate );

		// IPv6 entre corchetes, con o sin puerto: [::1]:443
		if ( '' !== $candidate && '[' === $candidate[0] ) {
			$candidate = (string) preg_replace( '/^\[([^\]]+)\](:\d+)?$/', '$1', $candidate );
		} elseif ( 1 === substr_count( $candidate, ':' ) ) {
			// Un solo ":" es IPv4 con puerto; dos o más, IPv6 sin corchetes.
			$candidate = (string) strtok( $candidate, ':' );
		}

		if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
			return $candidate;
		}
	}

	return '';
}

/**
 * ¿Esta IP es de la red interna?
 *
 * Si REMOTE_ADDR es privada o de loopback, el pedido llegó por un proxy de la
 * propia infraestructura y sus cabeceras son creíbles.
 */
function upfw_ip_is_internal( string $ip ): bool {
	if ( '' === $ip ) {
		return true;
	}

	return ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
}

/**
 * La IP del cliente, resuelta.
 *
 * @param array<string, mixed>|null $server Para poder testearlo sin servidor.
 */
function upfw_client_ip( ?array $server = null ): string {
	$server = null === $server ? $_SERVER : $server; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$remote = upfw_ip_from( (string) ( $server['REMOTE_ADDR'] ?? '' ) );

	// Expuesto directo a internet: las cabeceras no son de fiar.
	if ( ! upfw_ip_is_internal( $remote ) ) {
		return $remote;
	}

	foreach ( upfw_ip_headers() as $header ) {
		$ip = upfw_ip_from( (string) ( $server[ $header ] ?? '' ) );

		if ( '' !== $ip ) {
			return $ip;
		}
	}

	return $remote;
}

/**
 * La IP resuelta se guarda con la sesión.
 *
 * WordPress guarda `REMOTE_ADDR` tal cual, y detrás de un proxy eso es el
 * proxy. Este filtro es el punto que el propio WordPress deja para agregarle
 * datos a la sesión, así que la nuestra viaja al lado de la suya sin pisarla.
 *
 * @param array<string, mixed> $info
 * @return array<string, mixed>
 */
function upfw_session_ip( array $info ): array {
	$ip = upfw_client_ip();

	if ( '' !== $ip ) {
		$info['upfw_ip'] = $ip;
	}

	return $info;
}
add_filter( 'attach_session_information', 'upfw_session_ip' );

/**
 * La IP que se muestra de una sesión guardada.
 *
 * Prefiere la nuestra; si la sesión es vieja y no la tiene, usa la de
 * WordPress limpiándole el puerto que le pega Azure.
 *
 * @param array<string, mixed> $session
 */
function upfw_session_ip_of( array $session ): string {
	if ( ! empty( $session['upfw_ip'] ) ) {
		return (string) $session['upfw_ip'];
	}

	return upfw_ip_from( (string) ( $session['ip'] ?? '' ) );
}
