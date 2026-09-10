<?php
/**
 * Lo que la persona puede hacer con su propia seguridad.
 *
 * Prender el segundo factor, dar de alta su aplicación autenticadora, anotarse
 * los códigos de respaldo y cerrar sesiones. Todo desde el frente, en la
 * página que el sitio haya elegido, sin pasar por el escritorio de WordPress
 * —que la mayoría de la gente de un sitio así nunca ve—.
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;

/** ¿El sitio ofrece segundo factor a esta persona? */
function users_plus_2fa_offered( int $user_id ): bool {
	if ( 'off' === (string) users_plus_option( 'users_plus_2fa_mode' ) || array() === users_plus_2fa_methods() ) {
		return false;
	}

	$roles = (array) users_plus_option( 'users_plus_2fa_roles' );

	if ( array() === $roles ) {
		return true;
	}

	$user = get_userdata( $user_id );

	return $user instanceof WP_User && array() !== array_intersect( $roles, (array) $user->roles );
}

/** ¿Lo tiene prendido? Con el modo obligatorio, siempre. */
function users_plus_2fa_on( int $user_id ): bool {
	if ( ! users_plus_2fa_offered( $user_id ) ) {
		return false;
	}

	return 'required' === (string) users_plus_option( 'users_plus_2fa_mode' )
		|| (bool) get_user_meta( $user_id, 'users_plus_2fa_on', true );
}

/** ¿Puede apagarlo, o el sitio lo exige? */
function users_plus_2fa_can_turn_off( int $user_id ): bool {
	return 'required' !== (string) users_plus_option( 'users_plus_2fa_mode' );
}

/** Guarda lo que la persona hizo en su pantalla de seguridad. */
function users_plus_security_submit(): void {
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( users_plus_login_url() );
		exit;
	}

	check_admin_referer( 'users_plus_security' );

	$user_id = get_current_user_id();
	$destino = users_plus_account_url( 'security' );
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
	$accion = sanitize_key( wp_unslash( $_POST['users_plus_security'] ?? '' ) );

	switch ( $accion ) {
		case 'on':
			if ( array() === users_plus_2fa_available( $user_id ) ) {
				wp_safe_redirect( add_query_arg( 'users-plus', 'nomethod', $destino ) );
				exit;
			}

			update_user_meta( $user_id, 'users_plus_2fa_on', 1 );
			users_plus_notify_security( $user_id, __( 'Two-step verification was turned on.', 'users-plus' ) );

			// Los códigos de respaldo se generan al prender, no después: el
			// momento de anotarlos es antes de necesitarlos.
			if ( 0 === users_plus_backup_left( $user_id ) ) {
				set_transient( 'users_plus_backup_' . $user_id, users_plus_backup_generate( $user_id ), 15 * MINUTE_IN_SECONDS );
			}

			wp_safe_redirect( add_query_arg( 'users-plus', 'on', $destino ) );
			exit;

		case 'off':
			if ( ! users_plus_2fa_can_turn_off( $user_id ) ) {
				wp_safe_redirect( add_query_arg( 'users-plus', 'required', $destino ) );
				exit;
			}

			delete_user_meta( $user_id, 'users_plus_2fa_on' );
			users_plus_notify_security( $user_id, __( 'Two-step verification was turned off.', 'users-plus' ) );
			wp_safe_redirect( add_query_arg( 'users-plus', 'off', $destino ) );
			exit;

		case 'totp':
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
			$code = sanitize_text_field( wp_unslash( $_POST['users_plus_code'] ?? '' ) );

			if ( ! users_plus_totp_activate( $user_id, $code ) ) {
				wp_safe_redirect( add_query_arg( 'users-plus', 'badcode', $destino ) );
				exit;
			}

			update_user_meta( $user_id, 'users_plus_2fa_on', 1 );
			users_plus_notify_security( $user_id, __( 'An authenticator app was set up.', 'users-plus' ) );

			if ( 0 === users_plus_backup_left( $user_id ) ) {
				set_transient( 'users_plus_backup_' . $user_id, users_plus_backup_generate( $user_id ), 15 * MINUTE_IN_SECONDS );
			}

			wp_safe_redirect( add_query_arg( 'users-plus', 'totp', $destino ) );
			exit;

		case 'totp_off':
			users_plus_totp_forget( $user_id );
			users_plus_notify_security( $user_id, __( 'The authenticator app was removed.', 'users-plus' ) );
			wp_safe_redirect( add_query_arg( 'users-plus', 'totpoff', $destino ) );
			exit;

		case 'backup':
			set_transient( 'users_plus_backup_' . $user_id, users_plus_backup_generate( $user_id ), 15 * MINUTE_IN_SECONDS );
			wp_safe_redirect( add_query_arg( 'users-plus', 'backup', $destino ) );
			exit;
	}

	wp_safe_redirect( $destino );
	exit;
}
add_action( 'admin_post_users_plus_security', 'users_plus_security_submit' );

/**
 * Los códigos recién generados, si todavía se pueden mostrar.
 *
 * Viven en un transient de quince minutos y no en la user meta: se guardan
 * hasheados, así que ésta es la única ventana para verlos, y esa ventana tiene
 * que cerrarse sola.
 *
 * @return array<int, string>
 */
function users_plus_backup_fresh( int $user_id ): array {
	$codes = get_transient( 'users_plus_backup_' . $user_id );

	// Sin transient, get_transient() devuelve false, y (array) false es un
	// array con un elemento vacío adentro: la lista salía con un renglón en
	// blanco. Se compara antes de castear.
	if ( ! is_array( $codes ) || array() === $codes ) {
		return array();
	}

	// Y se borra al leerlos. La caja dice «se muestran sólo esta vez», y lo
	// decía mientras seguían apareciendo en cada recarga durante quince
	// minutos: o es cierto o no hay que decirlo.
	delete_transient( 'users_plus_backup_' . $user_id );

	return $codes;
}
