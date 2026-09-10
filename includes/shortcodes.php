<?php
/**
 * Los shortcodes: la puerta para que esto entre en cualquier diseño.
 *
 *   [users_plus_login]   el formulario de "mandame el enlace" + los botones sociales
 *   [users_plus_fields]    los campos de la persona, para editarlos
 *   [users_plus_sessions] las sesiones abiertas, con el botón de cerrarlas
 *   [users_plus_accounts]  las redes sociales vinculadas
 *
 * Cada uno pinta una plantilla que el sitio puede reemplazar (ver
 * includes/plantillas.php), y el CSS que traen se puede apagar desde los
 * ajustes. Así el que lo instala y no toca nada obtiene algo usable, y el que
 * tiene diseño propio no tiene que pelearle a nada.
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;

/** El estado que viene por la query, para saber qué mensaje mostrar. */
function users_plus_state(): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige qué mensaje se ve.
	return isset( $_GET['users-plus'] ) ? sanitize_key( wp_unslash( $_GET['users-plus'] ) ) : '';
}

/**
 * El desafío del segundo factor, si hay uno a medio hacer.
 *
 * Va antes del formulario de acceso y lo reemplaza: quien está a mitad de
 * camino no tiene que volver a escribir su correo, tiene que terminar.
 *
 * @return array<string, mixed>|array{}
 */
function users_plus_login_challenge(): array {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- el nonce propio ES la credencial.
	if ( ! isset( $_GET['users_plus_2fa'], $_GET['users_plus_key'] ) ) {
		return array();
	}

	$user_id = absint( $_GET['users_plus_2fa'] );
	$key     = sanitize_text_field( wp_unslash( $_GET['users_plus_key'] ) );
	$method  = sanitize_key( wp_unslash( $_GET['users_plus_method'] ?? '' ) );
	// phpcs:enable

	if ( array() === users_plus_2fa_pending( $user_id, $key ) ) {
		return array();
	}

	$methods = users_plus_2fa_available( $user_id );

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
function users_plus_shortcode_login( $atts = array() ): string {
	if ( is_user_logged_in() ) {
		return '';
	}

	users_plus_enqueue_styles();

	// A mitad de camino: falta el segundo factor y nada más.
	$challenge = users_plus_login_challenge();

	if ( array() !== $challenge ) {
		return users_plus_render( 'login-2fa', array_merge( $challenge, array( 'state' => users_plus_state() ) ) );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';

	return users_plus_render(
		'login.php',
		array(
			'state'     => users_plus_state(),
			'email'     => $email,
			'providers' => users_plus_sso_available(),
			'minutes'   => users_plus_login_expiry(),
		)
	);
}
add_shortcode( 'users_plus_login', 'users_plus_shortcode_login' );

/**
 * Los campos de la persona.
 *
 * `grupo` limita a los campos de un grupo, para que un sitio pueda partir el
 * formulario en dos bloques como hace el nuestro (lo que hace falta arriba, lo
 * opcional abajo).
 *
 * @param array<string, string>|string $atts WordPress manda '' cuando no hay ninguno.
 */
function users_plus_shortcode_fields( $atts = array() ): string {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	$atts = shortcode_atts(
		array(
			'group' => '',
			'title' => '',
		),
		(array) $atts,
		'users_plus_fields_save'
	);

	users_plus_enqueue_styles();

	return users_plus_render(
		'fields.php',
		array(
			'user_id' => get_current_user_id(),
			'fields'  => users_plus_fields( (string) $atts['group'] ),
			'group'   => (string) $atts['group'],
			'title'   => (string) $atts['title'],
			'state'   => users_plus_state(),
		)
	);
}
add_shortcode( 'users_plus_fields', 'users_plus_shortcode_fields' );

/** Guarda el formulario de [users_plus_fields]. */
function users_plus_save_fields_form(): void {
	if ( ! is_user_logged_in() ) {
		wp_die( esc_html__( 'You have to sign in first.', 'users-plus' ) );
	}

	check_admin_referer( 'users_plus_fields_save' );

	$group   = sanitize_key( wp_unslash( $_POST['users_plus_group'] ?? '' ) );
	$missing = users_plus_save( get_current_user_id(), $_POST, $group );
	$back    = wp_get_referer();
	$back    = $back ? $back : home_url( '/' );

	wp_safe_redirect( add_query_arg( 'users-plus', array() === $missing ? 'saved' : 'missing', $back ) );
	exit;
}
add_action( 'admin_post_users_plus_fields_save', 'users_plus_save_fields_form' );

/**
 * Shortcode sessions.
 *
 * @param array<string, string>|string $atts WordPress manda '' cuando no hay ninguno.
 */
function users_plus_shortcode_sessions( $atts = array() ): string {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	users_plus_enqueue_styles();

	return users_plus_render(
		'sessions.php',
		array(
			'sessions'      => users_plus_sessions( get_current_user_id() ),
			'can_close_one' => users_plus_sessions_addressable(),
			'state'         => users_plus_state(),
		)
	);
}
add_shortcode( 'users_plus_sessions', 'users_plus_shortcode_sessions' );

/**
 * Las redes sociales de la persona. Shortcode: [users_plus_accounts]
 *
 * El atributo `only` parte la lista: `linked` son con las que ya entra y
 * `available` las que podría sumar. Sin él, todas juntas. Un sitio que las
 * muestra en dos cajas distintas no tiene que filtrar nada por su cuenta.
 *
 * @param array<string, string>|string $atts WordPress manda '' cuando no hay ninguno.
 */
function users_plus_shortcode_accounts( $atts = array() ): string {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	users_plus_enqueue_styles();

	$atts    = shortcode_atts( array( 'only' => '' ), (array) $atts, 'users_plus_accounts' );
	$user_id = get_current_user_id();

	// De dónde vino, para volver ahí después de ir y venir del proveedor.
	set_transient( 'users_plus_sso_back_' . $user_id, (string) home_url( add_query_arg( array() ) ), 10 * MINUTE_IN_SECONDS );

	$linked    = users_plus_sso_linked( $user_id );
	$providers = users_plus_sso_available();

	if ( 'linked' === $atts['only'] ) {
		$providers = array_filter( $providers, static fn( string $id ): bool => in_array( $id, $linked, true ), ARRAY_FILTER_USE_KEY );
	} elseif ( 'available' === $atts['only'] ) {
		$providers = array_filter( $providers, static fn( string $id ): bool => ! in_array( $id, $linked, true ), ARRAY_FILTER_USE_KEY );
	}

	return users_plus_render(
		'accounts.php',
		array(
			'providers' => $providers,
			'linked'    => $linked,
			'state'     => users_plus_state(),
			'only'      => (string) $atts['only'],
		)
	);
}
add_shortcode( 'users_plus_accounts', 'users_plus_shortcode_accounts' );
