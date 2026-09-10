<?php
/**
 * Comandos de WP-CLI.
 *
 * Existe por un caso concreto: un sitio sin contraseñas donde el correo no
 * sale. Si ahí alguien se queda afuera —la sesión venció, el proveedor social
 * falla— no hay puerta. Con acceso al servidor, este comando imprime el enlace
 * en la terminal en vez de mandarlo.
 *
 *   wp users-dlx-plus login pablo@ejemplo.com
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Imprime un enlace de acceso para un correo.
 *
 * ## OPTIONS
 *
 * <correo>
 * : El correo de la cuenta.
 *
 * [--send]
 * : Además de imprimirlo, mandarlo por correo.
 *
 * ## EXAMPLES
 *
 *     wp users-dlx-plus login pablo@ejemplo.com
 *     wp users-dlx-plus login pablo@ejemplo.com --send
 *
 * @param array<int, string>    $args
 * @param array<string, string> $options
 */
function users_dlx_plus_cli_login( array $args, array $options = array() ): void {
	$email = sanitize_email( $args[0] ?? '' );

	if ( '' === $email || ! is_email( $email ) ) {
		WP_CLI::error( 'Hace falta un correo válido.' );
	}

	$user = get_user_by( 'email', $email );

	if ( ! $user ) {
		WP_CLI::error( sprintf( 'No existe ninguna cuenta con el correo %s.', $email ) );
	}

	$token = users_dlx_plus_token_create( (int) $user->ID );
	$url   = users_dlx_plus_login_link( (int) $user->ID, $token );

	if ( ! empty( $options['send'] ) ) {
		$mandado = users_dlx_plus_login_send( (int) $user->ID, $email, $token );
		WP_CLI::log( $mandado ? 'Correo enviado.' : 'No se pudo enviar el correo.' );
	}

	WP_CLI::log( $url );
	WP_CLI::log( sprintf( 'Vence en %d minutos y sirve una sola vez.', users_dlx_plus_login_expiry() ) );
}

WP_CLI::add_command( 'users-dlx-plus login', 'users_dlx_plus_cli_login' );
