<?php
/**
 * ¿Este sitio manda correos?
 *
 * La pregunta parece tonta y es la más importante del plugin: el enlace de
 * acceso, el código del segundo factor y la confirmación para borrar una
 * cuenta son todos correos. Si no salen, el sitio no tiene puerta de entrada
 * y nadie se entera hasta que alguien no puede entrar.
 *
 * La tentación es preguntarle a `has_filter('phpmailer_init')`: si alguien
 * enganchó ahí, hay un servidor configurado. Es mentira —un plugin de correo
 * activo pero mal configurado engancha igual— y es exactamente el error que
 * hacía que esta pantalla dijera «listo» mientras no salía nada.
 *
 * Así que no se adivina: se anota lo que pasó de verdad la última vez que
 * WordPress intentó mandar algo, y se ofrece un botón para probarlo.
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;

/** Anota que un envío salió bien. */
function users_plus_mail_ok(): void {
	update_option(
		'users_plus_mail_last',
		array(
			'ok'    => 1,
			'time'  => time(),
			'error' => '',
		)
	);
}
add_action( 'wp_mail_succeeded', 'users_plus_mail_ok' );

/**
 * Anota que un envío falló, con el motivo.
 *
 * @param WP_Error $error
 */
function users_plus_mail_failed( $error ): void {
	update_option(
		'users_plus_mail_last',
		array(
			'ok'    => 0,
			'time'  => time(),
			'error' => $error->get_error_message(),
		)
	);
}
add_action( 'wp_mail_failed', 'users_plus_mail_failed' );

/**
 * Lo que se sabe del correo saliente.
 *
 * @return array{state: string, time: int, error: string}
 *         state: 'ok', 'fail' o 'unknown'.
 */
function users_plus_mail_status(): array {
	$last = get_option( 'users_plus_mail_last', false );

	if ( ! is_array( $last ) ) {
		return array(
			'state' => 'unknown',
			'time'  => 0,
			'error' => '',
		);
	}

	return array(
		'state' => ! empty( $last['ok'] ) ? 'ok' : 'fail',
		'time'  => (int) ( $last['time'] ?? 0 ),
		'error' => (string) ( $last['error'] ?? '' ),
	);
}

/**
 * ¿Se puede contar con que un correo llegue?
 *
 * «Todavía no se probó» cuenta como que sí: no hay motivo para asustar a
 * nadie con una sospecha, y el primer envío real va a decir la verdad.
 */
function users_plus_mail_works(): bool {
	return 'fail' !== users_plus_mail_status()['state'];
}

/** Manda un correo de prueba a quien lo pidió, desde el admin. */
function users_plus_mail_test(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'users-plus' ) );
	}

	check_admin_referer( 'users_plus_mail_test' );

	$user = wp_get_current_user();

	$ok = wp_mail(
		$user->user_email,
		sprintf(
			/* translators: %s: nombre del sitio */
			__( 'Test from %s', 'users-plus' ),
			wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES )
		),
		__( 'If this arrived, the site can send the sign-in links, the second-step codes and the data requests. If it did not, none of those work either.', 'users-plus' )
	);

	// wp_mail() sólo devuelve si lo entregó al servidor; el hook de arriba ya
	// anotó lo que pasó de verdad. Igual se guarda por si nada disparó.
	if ( ! $ok && 'fail' !== users_plus_mail_status()['state'] ) {
		users_plus_mail_failed( new WP_Error( 'users_plus_mail', __( 'wp_mail() returned false and said nothing else.', 'users-plus' ) ) );
	}

	wp_safe_redirect( users_plus_admin_url( 'users-plus', array( 'users_plus_mail' => $ok ? 'sent' : 'failed' ) ) );
	exit;
}
add_action( 'admin_post_users_plus_mail_test', 'users_plus_mail_test' );
