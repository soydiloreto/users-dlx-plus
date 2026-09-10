<?php
/**
 * Los shortcodes: la puerta para que esto entre en cualquier diseño.
 *
 *   [upfw_login]   el formulario de "mandame el enlace" + los botones sociales
 *   [upfw_fields]    los campos de la persona, para editarlos
 *   [upfw_sessions] las sesiones abiertas, con el botón de cerrarlas
 *   [upfw_accounts]  las redes sociales vinculadas
 *
 * Cada uno pinta una plantilla que el sitio puede reemplazar (ver
 * includes/plantillas.php), y el CSS que traen se puede apagar desde los
 * ajustes. Así el que lo instala y no toca nada obtiene algo usable, y el que
 * tiene diseño propio no tiene que pelearle a nada.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

/** El estado que viene por la query, para saber qué mensaje mostrar. */
function upfw_state(): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige qué mensaje se ve.
	return isset( $_GET['upfw'] ) ? sanitize_key( wp_unslash( $_GET['upfw'] ) ) : '';
}

/**
 * El desafío del segundo factor, si hay uno a medio hacer.
 *
 * Va antes del formulario de acceso y lo reemplaza: quien está a mitad de
 * camino no tiene que volver a escribir su correo, tiene que terminar.
 *
 * @return array<string, mixed>|array{}
 */
function upfw_login_challenge(): array {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- el nonce propio ES la credencial.
	if ( ! isset( $_GET['upfw_2fa'], $_GET['upfw_key'] ) ) {
		return array();
	}

	$user_id = absint( $_GET['upfw_2fa'] );
	$key     = sanitize_text_field( wp_unslash( $_GET['upfw_key'] ) );
	$method  = sanitize_key( wp_unslash( $_GET['upfw_method'] ?? '' ) );
	// phpcs:enable

	if ( array() === upfw_2fa_pending( $user_id, $key ) ) {
		return array();
	}

	$methods = upfw_2fa_available( $user_id );

	return array(
		'user_id' => $user_id,
		'key'     => $key,
		'method'  => isset( $methods[ $method ] ) ? $method : (string) array_key_first( $methods ),
		'methods' => $methods,
	);
}

/**
 * Shortcode login.
 *
 * @param array<string, string>|string $atts WordPress manda '' cuando no hay ninguno.
 */
function upfw_shortcode_login( $atts = array() ): string {
	if ( is_user_logged_in() ) {
		return '';
	}

	upfw_enqueue_styles();

	// A mitad de camino: falta el segundo factor y nada más.
	$challenge = upfw_login_challenge();

	if ( array() !== $challenge ) {
		return upfw_render( 'login-2fa', array_merge( $challenge, array( 'state' => upfw_state() ) ) );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';

	return upfw_render(
		'login.php',
		array(
			'state'     => upfw_state(),
			'email'     => $email,
			'providers' => upfw_sso_available(),
			'minutes'   => upfw_login_expiry(),
		)
	);
}
add_shortcode( 'upfw_login', 'upfw_shortcode_login' );

/**
 * Los campos de la persona.
 *
 * `grupo` limita a los campos de un grupo, para que un sitio pueda partir el
 * formulario en dos bloques como hace el nuestro (lo que hace falta arriba, lo
 * opcional abajo).
 *
 * @param array<string, string>|string $atts WordPress manda '' cuando no hay ninguno.
 */
function upfw_shortcode_fields( $atts = array() ): string {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	$atts = shortcode_atts(
		array(
			'group' => '',
			'title' => '',
		),
		(array) $atts,
		'upfw_fields_save'
	);

	upfw_enqueue_styles();

	return upfw_render(
		'fields.php',
		array(
			'user_id' => get_current_user_id(),
			'fields'  => upfw_fields( (string) $atts['group'] ),
			'group'   => (string) $atts['group'],
			'title'   => (string) $atts['title'],
			'state'   => upfw_state(),
		)
	);
}
add_shortcode( 'upfw_fields', 'upfw_shortcode_fields' );

/** Guarda el formulario de [upfw_fields]. */
function upfw_save_fields_form(): void {
	if ( ! is_user_logged_in() ) {
		wp_die( esc_html__( 'You have to sign in first.', 'users-plus-for-wordpress' ) );
	}

	check_admin_referer( 'upfw_fields_save' );

	$group   = sanitize_key( wp_unslash( $_POST['upfw_group'] ?? '' ) );
	$missing = upfw_save( get_current_user_id(), $_POST, $group );
	$back    = wp_get_referer();
	$back    = $back ? $back : home_url( '/' );

	wp_safe_redirect( add_query_arg( 'upfw', array() === $missing ? 'saved' : 'missing', $back ) );
	exit;
}
add_action( 'admin_post_upfw_fields_save', 'upfw_save_fields_form' );

/**
 * Shortcode sessions.
 *
 * @param array<string, string>|string $atts WordPress manda '' cuando no hay ninguno.
 */
function upfw_shortcode_sessions( $atts = array() ): string {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	upfw_enqueue_styles();

	return upfw_render(
		'sessions.php',
		array(
			'sessions'      => upfw_sessions( get_current_user_id() ),
			'can_close_one' => upfw_sessions_addressable(),
			'state'         => upfw_state(),
		)
	);
}
add_shortcode( 'upfw_sessions', 'upfw_shortcode_sessions' );

/**
 * Las redes sociales de la persona. Shortcode: [upfw_accounts]
 *
 * El atributo `only` parte la lista: `linked` son con las que ya entra y
 * `available` las que podría sumar. Sin él, todas juntas. Un sitio que las
 * muestra en dos cajas distintas no tiene que filtrar nada por su cuenta.
 *
 * @param array<string, string>|string $atts WordPress manda '' cuando no hay ninguno.
 */
function upfw_shortcode_accounts( $atts = array() ): string {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	upfw_enqueue_styles();

	$atts    = shortcode_atts( array( 'only' => '' ), (array) $atts, 'upfw_accounts' );
	$user_id = get_current_user_id();

	// De dónde vino, para volver ahí después de ir y venir del proveedor.
	set_transient( 'upfw_sso_back_' . $user_id, (string) home_url( add_query_arg( array() ) ), 10 * MINUTE_IN_SECONDS );

	$linked    = upfw_sso_linked( $user_id );
	$providers = upfw_sso_available();

	if ( 'linked' === $atts['only'] ) {
		$providers = array_filter( $providers, static fn( string $id ): bool => in_array( $id, $linked, true ), ARRAY_FILTER_USE_KEY );
	} elseif ( 'available' === $atts['only'] ) {
		$providers = array_filter( $providers, static fn( string $id ): bool => ! in_array( $id, $linked, true ), ARRAY_FILTER_USE_KEY );
	}

	return upfw_render(
		'accounts.php',
		array(
			'providers' => $providers,
			'linked'    => $linked,
			'state'     => upfw_state(),
			'only'      => (string) $atts['only'],
		)
	);
}
add_shortcode( 'upfw_accounts', 'upfw_shortcode_accounts' );
