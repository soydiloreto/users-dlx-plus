<?php
/**
 * El modo "sólo correo": cerrar la puerta de la contraseña.
 *
 * Cómo entra la gente lo elige el sitio, y son tres respuestas posibles: sólo
 * el enlace por correo, sólo usuario y contraseña, o las dos cosas. Este
 * archivo se ocupa nada más que de la primera, que es la única que necesita
 * cerrar algo: diseñar una pantalla sin contraseña no alcanza, porque
 * `/wp-login.php` sigue abierto con su formulario y con el registro nativo.
 * Con cualquiera de las otras dos, acá no pasa nada.
 *
 * Queda una salida de emergencia deliberada: `/wp-login.php?users-plus-admin=1`
 * muestra el formulario nativo. No es un secreto —la seguridad la sigue dando
 * la contraseña— pero evita quedarse afuera del sitio si el correo o el
 * proveedor social fallan.
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;

/**
 * Acciones de wp-login.php que TIENEN que seguir funcionando.
 *
 * `logout` y `postpass` son flujos normales; `rp` / `resetpass` son el final de
 * un reseteo que sólo puede iniciar un admin desde el escritorio; y
 * `confirmaction` es el que confirma las solicitudes de datos personales del
 * RGPD, que WordPress manda por correo y no tiene otra URL.
 */
const USERS_PLUS_LOGIN_ALLOWED = array( 'logout', 'postpass', 'rp', 'resetpass', 'confirmaction' );

/**
 * ¿Hay que sacar este request de wp-login.php?
 *
 * Función pura a propósito: decide sólo a partir de la acción y la query, sin
 * tocar globals ni la base, así se puede testear sin levantar WordPress.
 *
 * @param string               $accion Valor de `action` (cadena vacía = login).
 * @param array<string, mixed> $query  Equivalente a $_GET.
 */
function users_plus_should_redirect( string $accion, array $query = array() ): bool {
	// Salida de emergencia para administradores.
	if ( isset( $query['users-plus-admin'] ) ) {
		return false;
	}

	// El login intersticial es el modal que aparece dentro del escritorio
	// cuando vence la sesión. Sacarlo de ahí rompería la pantalla que lo abrió.
	if ( isset( $query['interim-login'] ) ) {
		return false;
	}

	$accion = '' === $accion ? 'login' : $accion;

	if ( in_array( $accion, USERS_PLUS_LOGIN_ALLOWED, true ) ) {
		return false;
	}

	// login, register, lostpassword y cualquier acción desconocida van a la
	// página de acceso del sitio.
	return true;
}

/** Manda wp-login.php a la página de acceso. */
function users_plus_block_wp_login(): void {
	if ( ! users_plus_login_only_link() ) {
		return;
	}

	// Sin otra puerta, wp-login.php es la única que hay: cerrarla dejaría el
	// sitio sin entrada. Se compara contra la URL resuelta y no contra el
	// ajuste, porque un sitio puede darla por filtro en vez de por opción.
	if ( users_plus_login_url() === wp_login_url() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo se lee para decidir el destino.
	$accion = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! users_plus_should_redirect( $accion, $_GET ) ) {
		return;
	}

	// POST al formulario nativo: no redirigir en silencio, cortar.
	if ( 'POST' === sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
		wp_die(
			esc_html__( 'This site signs you in without a password: with your email or with a social account.', 'users-plus' ),
			esc_html__( 'Sign in', 'users-plus' ),
			array( 'response' => 403 )
		);
	}

	wp_safe_redirect( users_plus_login_url() );
	exit;
}
add_action( 'login_init', 'users_plus_block_wp_login' );

/**
 * Apaga el registro nativo de WordPress mientras dure el modo "sólo correo".
 *
 * Sin esto, con `users_can_register` activo, `wp-signup.php` seguiría dando de
 * alta gente con contraseña por correo.
 *
 * @param mixed $value Lo que venía de la option.
 * @return mixed
 */
function users_plus_block_registration( $value ) {
	$forced = (string) users_plus_option( 'users_plus_wp_registration' );

	if ( 'on' === $forced ) {
		return 1;
	}

	if ( 'off' === $forced ) {
		return 0;
	}

	// Sin nada forzado, el modo «sólo enlace» igual lo apaga: dejarlo prendido
	// daría de alta gente con contraseña por una puerta que el sitio cerró.
	return users_plus_login_only_link() ? 0 : $value;
}
add_filter( 'option_users_can_register', 'users_plus_block_registration' );

/* ── El perfil del escritorio ──────────────────────────────────────── */

/**
 * Qué pasa cuando alguien abre `wp-admin/profile.php`.
 *
 * Un sitio que armó su área de cuenta en el frente no quiere que la mitad de
 * los datos se editen en otra pantalla, con otro aspecto y otras reglas —ahí
 * no corren los límites de edición ni los campos obligatorios que se
 * configuraron acá—. Se puede mandar a la gente al área de cuenta, o cerrarle
 * la puerta.
 *
 * Quien administra queda siempre afuera de esto: es la persona que tiene que
 * poder arreglar lo que se rompió, y el perfil del escritorio es donde se
 * arregla.
 */
function users_plus_wp_profile_guard(): void {
	$modo = (string) users_plus_option( 'users_plus_wp_profile' );

	if ( 'allow' === $modo || current_user_can( 'edit_users' ) ) {
		return;
	}

	$pantalla = basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ?? '' ) ) );

	if ( 'profile.php' !== $pantalla ) {
		return;
	}

	if ( 'redirect' === $modo ) {
		$destino = users_plus_account_url( 'details' );

		wp_safe_redirect( '' !== $destino ? $destino : home_url( '/' ) );
		exit;
	}

	wp_die(
		esc_html__( 'Your details are edited from your account on the site, not from here.', 'users-plus' ),
		esc_html__( 'Not from here', 'users-plus' ),
		array(
			'response'  => 403,
			'back_link' => true,
		)
	);
}
add_action( 'admin_init', 'users_plus_wp_profile_guard' );
