<?php
/**
 * El centro de la autenticación: qué puertas hay y qué se pide en cada una.
 *
 * Un sitio no elige «una» forma de entrar: elige un conjunto. Puede tener
 * contraseña y enlace por correo a la vez, puede sumarle redes sociales, y
 * puede pedir un segundo factor en todas, en algunas o en ninguna. Esas
 * combinaciones no se pueden resolver con una cadena de `if`: hace falta un
 * lugar donde estén enumeradas y un solo camino por el que pase todo el mundo.
 *
 * Ese camino es `upfw_complete_login()`. La entren como la entren —contraseña,
 * enlace, red social— todos terminan ahí, y ahí se decide si la sesión se abre
 * o si primero hay que probar algo más. Sin eso, agregar un segundo factor
 * significaría acordarse de agregarlo en cada puerta, y la que se olvide queda
 * abierta.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

/** Cuánto vive un intento de segundo factor a medio terminar. */
const UPFW_2FA_WINDOW = 10 * MINUTE_IN_SECONDS;

/* ── Los segundos factores disponibles ─────────────────────────────── */

/*
 * Una passkey no está en esta lista, y no es un olvido: no es un segundo
 * factor sino una forma de entrar que ya lleva los dos adentro —algo que se
 * tiene, el dispositivo, y algo que se es o se sabe, la huella o el PIN—.
 * Ponerla acá significaría pedir tres cosas a quien ya dio dos.
 */

/**
 * Los métodos de segundo factor que el plugin sabe manejar.
 *
 * Es un registro y no una lista fija: un add-on agrega el suyo sin tocar esto,
 * igual que las secciones del área de cuenta.
 *
 * Cada uno declara:
 *   label     Cómo se llama para la gente.
 *   help      Qué es, en una línea.
 *   ready     Función que dice si ESA persona ya lo tiene configurado.
 *   send      Opcional: qué hacer al empezar el desafío (mandar el correo).
 *   verify    Función que valida lo que escribió la persona.
 *   position  Orden de preferencia cuando hay más de uno.
 *
 * @return array<string, array<string, mixed>>
 */
function upfw_2fa_methods(): array {
	$methods = array(
		'email' => array(
			// Por dónde llega el segundo paso. Es lo que permite decidir si
			// suma algo cuando alguien ya entró por ese mismo canal.
			'channel'  => 'email',
			'label'    => __( 'A code by email', 'users-plus-for-wordpress' ),
			'help'     => __( 'We send a six-digit code to the address on the account. Nothing to install.', 'users-plus-for-wordpress' ),
			'ready'    => static fn( int $user_id ): bool => true,
			'send'     => 'upfw_2fa_email_send',
			'verify'   => 'upfw_2fa_email_verify',
			'position' => 20,
		),
		'totp'  => array(
			'channel'  => 'device',
			'label'    => __( 'An authenticator app', 'users-plus-for-wordpress' ),
			'help'     => __( 'The six-digit code that changes every thirty seconds, from Google Authenticator, 1Password, Aegis or whichever one you use.', 'users-plus-for-wordpress' ),
			'ready'    => 'upfw_totp_ready',
			'verify'   => 'upfw_totp_verify',
			'position' => 10,
		),
	);

	/**
	 * Filtra los métodos de segundo factor.
	 *
	 * @param array<string, array<string, mixed>> $methods
	 */
	$methods = (array) apply_filters( 'upfw_2fa_methods', $methods );

	// Los que el sitio apagó no existen para nadie.
	$enabled = (array) upfw_option( 'upfw_2fa_methods' );

	$methods = array_filter(
		$methods,
		static fn( string $id ): bool => in_array( $id, $enabled, true ),
		ARRAY_FILTER_USE_KEY
	);

	uasort( $methods, static fn( array $a, array $b ): int => $a['position'] <=> $b['position'] );

	return $methods;
}

/**
 * Los métodos que ESTA persona tiene listos para usar ahora.
 *
 * @return array<string, array<string, mixed>>
 */
function upfw_2fa_available( int $user_id ): array {
	return array_filter(
		upfw_2fa_methods(),
		static fn( array $m ): bool => is_callable( $m['ready'] ) && call_user_func( $m['ready'], $user_id )
	);
}

/* ── La política: a quién se le pide ───────────────────────────────── */

/**
 * ¿Esta persona tiene que pasar por un segundo factor?
 *
 * Tres cosas mandan, en este orden:
 *
 *   1. El modo del sitio. Apagado no se pide nunca; opcional se pide sólo a
 *      quien lo prendió; obligatorio se pide a todo el mundo.
 *   2. Los roles elegidos, si la lista no está vacía. Sirve para pedírselo a
 *      quien administra y no a los 25.000 que sólo miran un curso.
 *   3. Cómo entró. Un enlace de un solo uso mandado al correo ya prueba que
 *      quien entra tiene ese correo; pedirle además un código al mismo correo
 *      es pedir dos veces lo mismo. Por eso es un ajuste aparte y viene
 *      apagado: un sitio que quiera el segundo factor igual, lo prende.
 *
 * @param string $via 'password', 'link' o 'sso'.
 */
function upfw_2fa_required( int $user_id, string $via ): bool {
	$mode = (string) upfw_option( 'upfw_2fa_mode' );

	if ( 'off' === $mode ) {
		return false;
	}

	if ( array() === upfw_2fa_available( $user_id ) ) {
		return false;
	}

	if ( 'link' === $via && ! upfw_2fa_worth_it_on_link( $user_id ) ) {
		return false;
	}

	$roles = (array) upfw_option( 'upfw_2fa_roles' );

	if ( array() !== $roles ) {
		$user = get_userdata( $user_id );

		if ( ! $user instanceof WP_User || array() === array_intersect( $roles, (array) $user->roles ) ) {
			return false;
		}
	}

	if ( 'required' === $mode ) {
		return true;
	}

	// Opcional: sólo a quien lo prendió.
	return (bool) get_user_meta( $user_id, 'upfw_2fa_on', true );
}

/**
 * ¿Vale la pena pedir el segundo paso a quien entró por el enlace de correo?
 *
 * En automático, sí sólo cuando hay un método que NO llegue por correo. Un
 * código mandado al mismo buzón que la persona acaba de abrir para seguir el
 * enlace no prueba nada que el enlace no haya probado ya; una aplicación
 * autenticadora o una llave, sí.
 *
 * El sitio puede forzar las dos respuestas, pero el automático es el que
 * evita tanto la puerta abierta como el trámite que no sirve para nada.
 */
function upfw_2fa_worth_it_on_link( int $user_id ): bool {
	$modo = (string) upfw_option( 'upfw_2fa_link' );

	if ( 'always' === $modo ) {
		return true;
	}

	if ( 'never' === $modo ) {
		return false;
	}

	foreach ( upfw_2fa_available( $user_id ) as $method ) {
		if ( 'email' !== ( $method['channel'] ?? 'email' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Las formas de entrar que ofrece el sitio, y si a esta persona le piden el
 * segundo paso en cada una.
 *
 * Existe para que la pantalla de seguridad diga la verdad. «No se te está
 * pidiendo» es falso apenas hay una red social prendida: la excepción del
 * enlace por correo es sólo del enlace por correo, porque ahí el segundo
 * código iría al mismo buzón que la persona acaba de abrir.
 *
 * Las passkeys no están en la lista porque no pasan por acá: una passkey ya
 * son dos factores en un paso, y eso se cuenta en su propia caja.
 *
 * @return array<string, array{label: string, asked: bool}>
 */
function upfw_2fa_ways( int $user_id ): array {
	$ways = array();

	if ( upfw_login_has_link() ) {
		$ways['link'] = __( 'the link we email you', 'users-plus-for-wordpress' );
	}

	if ( upfw_login_has_password() ) {
		$ways['password'] = __( 'your password', 'users-plus-for-wordpress' );
	}

	if ( function_exists( 'upfw_sso_available' ) && array() !== upfw_sso_available() ) {
		$ways['sso'] = __( 'a social account', 'users-plus-for-wordpress' );
	}

	$out = array();

	foreach ( $ways as $via => $label ) {
		$out[ $via ] = array(
			'label' => $label,
			'asked' => upfw_2fa_required( $user_id, $via ),
		);
	}

	return $out;
}

/**
 * Las formas de entrar en las que sí se pide el segundo paso.
 *
 * @return array<int, string>
 */
function upfw_2fa_ways_asked( int $user_id ): array {
	return array_values(
		wp_list_pluck(
			array_filter( upfw_2fa_ways( $user_id ), static fn( array $w ): bool => $w['asked'] ),
			'label'
		)
	);
}

/**
 * ¿Este navegador ya pasó el segundo factor hace poco?
 *
 * La cookie no da acceso: sólo evita repetir el desafío en el mismo navegador
 * durante los días que diga el ajuste. Va firmada con los salts del sitio, así
 * que no se puede fabricar, y lleva el id de quien la pidió.
 */
function upfw_2fa_trusted( int $user_id ): bool {
	$days = (int) upfw_option( 'upfw_2fa_remember_days' );

	if ( $days <= 0 ) {
		return false;
	}

	$cookie = isset( $_COOKIE[ 'upfw_2fa_' . COOKIEHASH ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ 'upfw_2fa_' . COOKIEHASH ] ) ) : '';

	if ( '' === $cookie ) {
		return false;
	}

	[ $stored_id, $expires, $hash ] = array_pad( explode( '|', $cookie, 3 ), 3, '' );

	if ( (int) $stored_id !== $user_id || (int) $expires < time() ) {
		return false;
	}

	return hash_equals( wp_hash( $user_id . '|' . $expires, 'secure_auth' ), $hash );
}

/** Deja marcado este navegador para no volver a preguntar por unos días. */
function upfw_2fa_trust( int $user_id ): void {
	$days = (int) upfw_option( 'upfw_2fa_remember_days' );

	if ( $days <= 0 ) {
		return;
	}

	$expires = time() + $days * DAY_IN_SECONDS;
	$value   = $user_id . '|' . $expires . '|' . wp_hash( $user_id . '|' . $expires, 'secure_auth' );

	setcookie( 'upfw_2fa_' . COOKIEHASH, $value, $expires, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
}

/* ── El único camino de entrada ────────────────────────────────────── */

/**
 * Cierra el ingreso de alguien: o abre la sesión, o pide el segundo factor.
 *
 * @param int    $user_id  Quién entra.
 * @param string $via      Por qué puerta: 'password', 'link' o 'sso'.
 * @param bool   $remember Sesión larga.
 * @param string $redirect Adónde va después.
 */
function upfw_complete_login( int $user_id, string $via, bool $remember = true, string $redirect = '' ): void {
	$redirect = '' !== $redirect ? $redirect : (string) apply_filters( 'upfw_login_redirect', home_url( '/' ), $user_id );

	// La passkey entra directo: ya probó las dos cosas.
	if ( 'passkey' === $via || ! upfw_2fa_required( $user_id, $via ) || upfw_2fa_trusted( $user_id ) ) {
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, $remember );

		/**
		 * Alguien entró, ya con todo lo que hiciera falta.
		 *
		 * @param int    $user_id
		 * @param string $via
		 */
		do_action( 'upfw_logged_in', $user_id, $via );

		wp_safe_redirect( $redirect );
		exit;
	}

	upfw_2fa_challenge( $user_id, $via, $remember, $redirect );
}

/**
 * Guarda el ingreso a medio hacer y manda a la pantalla del segundo factor.
 *
 * Lo pendiente vive en una user meta y no en la sesión: el intento tiene que
 * sobrevivir a que la persona abra la pantalla en otra pestaña, y no puede
 * depender de una cookie de sesión que todavía no existe.
 */
function upfw_2fa_challenge( int $user_id, string $via, bool $remember, string $redirect ): void {
	// Por las dudas: si alguna puerta dejó la cookie puesta, se saca. Una
	// sesión abierta antes del segundo factor es no tener segundo factor.
	wp_clear_auth_cookie();

	$nonce = wp_generate_password( 32, false );

	update_user_meta(
		$user_id,
		'upfw_2fa_pending',
		array(
			'nonce'    => wp_hash( $nonce ),
			'expires'  => time() + UPFW_2FA_WINDOW,
			'via'      => $via,
			'remember' => $remember ? 1 : 0,
			'redirect' => $redirect,
		)
	);

	$methods = upfw_2fa_available( $user_id );

	// Entrando por el enlace de correo, se arranca con un método que no sea
	// otro correo: mandarle un código al buzón que acaba de abrir sería
	// hacerle repetir el mismo paso.
	if ( 'link' === $via ) {
		foreach ( $methods as $id => $m ) {
			if ( 'email' !== ( $m['channel'] ?? 'email' ) ) {
				$methods = array( $id => $m ) + $methods;
				break;
			}
		}
	}

	$method = (string) array_key_first( $methods );

	upfw_2fa_send( $user_id, $method );

	wp_safe_redirect(
		add_query_arg(
			array(
				'upfw_2fa'    => $user_id,
				'upfw_key'    => $nonce,
				'upfw_method' => $method,
			),
			upfw_login_url()
		)
	);
	exit;
}

/** El intento pendiente de alguien, si sigue vivo y el nonce es el suyo. */
function upfw_2fa_pending( int $user_id, string $nonce ): array {
	$pending = (array) get_user_meta( $user_id, 'upfw_2fa_pending', true );

	if ( array() === $pending || (int) ( $pending['expires'] ?? 0 ) < time() ) {
		return array();
	}

	return hash_equals( (string) ( $pending['nonce'] ?? '' ), wp_hash( $nonce ) ) ? $pending : array();
}

/** Dispara lo que ese método necesite para empezar (mandar el correo). */
function upfw_2fa_send( int $user_id, string $method ): void {
	$methods = upfw_2fa_available( $user_id );

	if ( isset( $methods[ $method ]['send'] ) && is_callable( $methods[ $method ]['send'] ) ) {
		call_user_func( $methods[ $method ]['send'], $user_id );
	}
}

/**
 * Valida lo que escribió la persona y, si está bien, la deja entrar.
 *
 * Los códigos de respaldo se prueban siempre, sea cual sea el método elegido:
 * son justamente para cuando el método no está a mano.
 */
function upfw_2fa_verify( int $user_id, string $method, string $code ): bool {
	if ( upfw_backup_use( $user_id, $code ) ) {
		return true;
	}

	$methods = upfw_2fa_available( $user_id );

	if ( ! isset( $methods[ $method ] ) || ! is_callable( $methods[ $method ]['verify'] ) ) {
		return false;
	}

	return (bool) call_user_func( $methods[ $method ]['verify'], $user_id, $code );
}

/**
 * La pantalla del segundo factor y su envío.
 *
 * Vive en `init` como el resto de las puertas del plugin, para que la página
 * de acceso sea una página del sitio y no wp-login.php.
 */
function upfw_2fa_handle(): void {
	// phpcs:disable WordPress.Security.NonceVerification -- el nonce propio ES la credencial.
	if ( ! isset( $_POST['upfw_2fa_user'], $_POST['upfw_2fa_key'] ) ) {
		return;
	}

	$user_id = absint( $_POST['upfw_2fa_user'] );
	$key     = sanitize_text_field( wp_unslash( $_POST['upfw_2fa_key'] ) );
	$method  = sanitize_key( wp_unslash( $_POST['upfw_2fa_method'] ?? '' ) );
	$code    = sanitize_text_field( wp_unslash( $_POST['upfw_2fa_code'] ?? '' ) );
	$trust   = isset( $_POST['upfw_2fa_trust'] );
	$resend  = isset( $_POST['upfw_2fa_resend'] );
	// phpcs:enable

	$pending = upfw_2fa_pending( $user_id, $key );

	if ( array() === $pending ) {
		wp_safe_redirect( add_query_arg( 'upfw', 'expired', upfw_login_url() ) );
		exit;
	}

	$back = add_query_arg(
		array(
			'upfw_2fa'    => $user_id,
			'upfw_key'    => $key,
			'upfw_method' => $method,
		),
		upfw_login_url()
	);

	if ( $resend ) {
		upfw_2fa_send( $user_id, $method );
		wp_safe_redirect( add_query_arg( 'upfw', 'sent', $back ) );
		exit;
	}

	if ( '' === $code || ! upfw_2fa_verify( $user_id, $method, $code ) ) {
		wp_safe_redirect( add_query_arg( 'upfw', 'code', $back ) );
		exit;
	}

	delete_user_meta( $user_id, 'upfw_2fa_pending' );

	if ( $trust ) {
		upfw_2fa_trust( $user_id );
	}

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, ! empty( $pending['remember'] ) );

	do_action( 'upfw_logged_in', $user_id, (string) $pending['via'] );

	wp_safe_redirect( (string) $pending['redirect'] );
	exit;
}
add_action( 'init', 'upfw_2fa_handle', 5 );

/**
 * La contraseña también pasa por acá.
 *
 * WordPress abre la sesión antes de disparar `wp_login`, así que lo que se
 * hace es cerrarla enseguida y mandar al desafío. Es feo y es lo que hay: no
 * hay un hook entre «la contraseña era correcta» y «la cookie está puesta».
 */
function upfw_2fa_after_password( string $login, WP_User $user ): void {
	if ( ! upfw_2fa_required( (int) $user->ID, 'password' ) || upfw_2fa_trusted( (int) $user->ID ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- lo verificó WordPress al autenticar.
	$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
	$redirect = '' !== $redirect ? $redirect : (string) apply_filters( 'upfw_login_redirect', home_url( '/' ), (int) $user->ID );

	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$remember = ! empty( $_POST['rememberme'] );

	upfw_2fa_challenge( (int) $user->ID, 'password', $remember, $redirect );
}
add_action( 'wp_login', 'upfw_2fa_after_password', 10, 2 );

/* ── Códigos de respaldo ───────────────────────────────────────────── */

/**
 * Genera un juego nuevo de códigos de respaldo.
 *
 * Se guardan hasheados, como una contraseña: si alguien se lleva la base, se
 * lleva hashes. Se devuelven en claro una sola vez, que es cuando la persona
 * los tiene que anotar.
 *
 * @return array<int, string>
 */
function upfw_backup_generate( int $user_id, int $many = 8 ): array {
	$plain  = array();
	$hashes = array();

	for ( $i = 0; $i < $many; $i++ ) {
		$code     = strtolower( wp_generate_password( 10, false, false ) );
		$plain[]  = $code;
		$hashes[] = wp_hash_password( $code );
	}

	update_user_meta( $user_id, 'upfw_backup_codes', $hashes );

	return $plain;
}

/** Cuántos códigos de respaldo le quedan sin usar. */
function upfw_backup_left( int $user_id ): int {
	return count( (array) get_user_meta( $user_id, 'upfw_backup_codes', true ) );
}

/**
 * Usa un código de respaldo, si el que escribieron es uno.
 *
 * Se borra al usarlo: un código de un solo uso que se puede usar dos veces no
 * es un código de un solo uso.
 */
function upfw_backup_use( int $user_id, string $code ): bool {
	$code   = strtolower( trim( str_replace( array( ' ', '-' ), '', $code ) ) );
	$hashes = (array) get_user_meta( $user_id, 'upfw_backup_codes', true );

	foreach ( $hashes as $i => $hash ) {
		if ( wp_check_password( $code, (string) $hash, $user_id ) ) {
			unset( $hashes[ $i ] );
			update_user_meta( $user_id, 'upfw_backup_codes', array_values( $hashes ) );

			return true;
		}
	}

	return false;
}
