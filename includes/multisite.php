<?php
/**
 * Lo que cambia cuando esto corre en una red de sitios.
 *
 * En un WordPress multisitio las cuentas son de la red y los permisos son de
 * cada sitio: alguien puede existir y no ser miembro de acá. Un plugin de
 * usuarios que ignora eso crea cuentas que entran y no pueden hacer nada, o
 * deja afuera a gente que ya existe en el sitio de al lado.
 *
 * Los ajustes, en cambio, quedan por sitio a propósito: cada sitio de una red
 * suele tener su propia página de cuenta, sus propios campos y su propio
 * diseño. Un ajuste de red obligaría a que todos los sitios pidan lo mismo.
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;

/**
 * Suma a alguien a este sitio si todavía no es miembro.
 *
 * Se usa el mismo rol que para una cuenta nueva: si el sitio decidió que quien
 * se registra es suscriptor, quien llega desde otro sitio de la red también.
 */
function users_dlx_plus_join_site( int $user_id ): void {
	if ( ! is_multisite() || $user_id <= 0 ) {
		return;
	}

	if ( is_user_member_of_blog( $user_id, get_current_blog_id() ) ) {
		return;
	}

	// Sin registro abierto, existir en la red no alcanza para entrar acá: es
	// la misma regla que para un correo nuevo, y la decide el mismo ajuste.
	if ( ! users_dlx_plus_option( 'users_dlx_plus_login_register' ) ) {
		return;
	}

	add_user_to_blog( get_current_blog_id(), $user_id, (string) users_dlx_plus_option( 'users_dlx_plus_login_role' ) );
}

/**
 * Los campos de arranque, para los sitios de una red.
 *
 * El hook de activación corre una sola vez cuando el plugin se activa en toda
 * la red, así que un sitio nuevo nacería sin ningún campo. Se siembran la
 * primera vez que alguien los pide, y sólo si la option no existe: una lista
 * vacía a propósito se respeta.
 */
function users_dlx_plus_seed_fields(): void {
	if ( false === get_option( 'users_dlx_plus_fields', false ) ) {
		update_option( 'users_dlx_plus_fields', users_dlx_plus_default_fields() );

		return;
	}

	users_dlx_plus_seed_native_fields();
}
add_action( 'wp_initialize_site', 'users_dlx_plus_seed_fields' );
add_action( 'admin_init', 'users_dlx_plus_seed_fields' );

/**
 * Los campos de WordPress, en un sitio que ya tenía la lista armada.
 *
 * Se agregan al principio y sólo los que falten. Un sitio que ya venía
 * pintando el nombre por su cuenta va a ver dos: eso es correcto y se arregla
 * apagando el suyo, no escondiendo el que WordPress ya tenía.
 */
function users_dlx_plus_seed_native_fields(): void {
	$fields = (array) get_option( 'users_dlx_plus_fields', array() );
	$claves = array_column( $fields, 'key' );
	$faltan = array();

	foreach ( users_dlx_plus_default_fields() as $field ) {
		if ( users_dlx_plus_field_is_native( $field['key'] ) && ! in_array( $field['key'], $claves, true ) ) {
			$faltan[] = $field;
		}
	}

	if ( array() === $faltan ) {
		return;
	}

	update_option( 'users_dlx_plus_fields', array_merge( $faltan, $fields ) );
}
