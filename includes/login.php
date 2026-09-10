<?php
/**
 * Acceso por enlace de un solo uso ("magic link").
 *
 * Entrar y registrarse pueden ser el mismo paso: la persona escribe su correo,
 * recibe un enlace y con eso entra. Si la cuenta no existía, se crea ahí mismo.
 *
 * Cómo funciona:
 *   1. Se genera un token al azar.
 *   2. En la base se guarda sólo su hash, nunca el token: si alguien se lleva
 *      la base, no puede entrar con lo que hay ahí.
 *   3. Al abrir el enlace se valida, se inicia sesión y el token se borra —
 *      sirve una sola vez.
 *
 * Decisiones que importan:
 *   - La respuesta es idéntica exista o no la cuenta. Decir "no encontramos ese
 *     correo" convierte el formulario en un detector de quién está registrado.
 *   - Comparación en tiempo constante, para no filtrar el token por cuánto
 *     tarda en fallar.
 *   - Límite de pedidos por correo, para que no se pueda usar de ariete ni de
 *     máquina de mandar mails a terceros.
 *
 * Esto convive con el registro normal de WordPress: es una puerta más, no un
 * reemplazo. El ajuste "sólo correo" es el que cierra las otras.
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;

const USERS_DLX_PLUS_META_HASH    = '_users_dlx_plus_acceso_hash';
const USERS_DLX_PLUS_META_EXPIRES = '_users_dlx_plus_acceso_vence';

/**
 * Genera un token, guarda su hash y devuelve el token en claro.
 *
 * Lo que se persiste es el hash: el token en claro sólo existe en el correo.
 */
function users_dlx_plus_token_create( int $user_id ): string {
	$token = wp_generate_password( 40, false, false );

	update_user_meta( $user_id, USERS_DLX_PLUS_META_HASH, wp_hash( $token ) );
	update_user_meta( $user_id, USERS_DLX_PLUS_META_EXPIRES, time() + ( users_dlx_plus_login_expiry() * MINUTE_IN_SECONDS ) );

	return $token;
}

/**
 * ¿Es válido este token para este usuario?
 *
 * Se compara con hash_equals para que el tiempo de respuesta no dependa de
 * cuántos caracteres coincidieron.
 */
function users_dlx_plus_token_valid( int $user_id, string $token ): bool {
	$hash  = (string) get_user_meta( $user_id, USERS_DLX_PLUS_META_HASH, true );
	$vence = (int) get_user_meta( $user_id, USERS_DLX_PLUS_META_EXPIRES, true );

	if ( '' === $hash || $vence <= 0 || time() > $vence ) {
		return false;
	}

	return hash_equals( $hash, wp_hash( $token ) );
}

/** Quema el token: un enlace sirve una sola vez. */
function users_dlx_plus_token_burn( int $user_id ): void {
	delete_user_meta( $user_id, USERS_DLX_PLUS_META_HASH );
	delete_user_meta( $user_id, USERS_DLX_PLUS_META_EXPIRES );
}

/** URL del enlace de acceso. */
function users_dlx_plus_login_link( int $user_id, string $token ): string {
	return add_query_arg(
		array(
			'users_dlx_plus_login' => $user_id,
			'users_dlx_plus_token' => $token,
		),
		home_url( '/' )
	);
}

/**
 * Encuentra la cuenta del correo, o la crea si el sitio lo permite.
 *
 * Acá es donde "registro" y "login" dejan de ser dos cosas: el alta ocurre en
 * el mismo paso, sin formulario aparte y sin elegir contraseña.
 *
 * @return int ID del usuario, o 0 si no existe y no se puede crear.
 */
function users_dlx_plus_user_for( string $email ): int {
	$user = get_user_by( 'email', $email );

	if ( $user ) {
		// En una red, existir no es ser miembro de este sitio: quien viene del
		// sitio de al lado entra, pero sin rol acá no puede hacer nada.
		users_dlx_plus_join_site( (int) $user->ID );

		return (int) $user->ID;
	}

	if ( ! users_dlx_plus_option( 'users_dlx_plus_login_register' ) ) {
		return 0;
	}

	// El nombre visible y el de la URL del perfil NO se dejan derivar del
	// user_login, porque el user_login es el correo: WordPress armaría un
	// display_name «alguien@gmail.com» que después sale en los foros, y un
	// user_nicename «alguiengmail-com» que se lee al derecho en la URL del
	// perfil. La cuenta se identifica con el correo; publicarlo es otra cosa.
	$visible = users_dlx_plus_name_from_email( $email );

	// La contraseña se genera al azar y nadie la conoce, ni siquiera quien se
	// registra: WordPress necesita el campo, el sitio no lo usa.
	$id = wp_insert_user(
		array(
			'user_login'    => $email,
			'user_email'    => $email,
			'user_pass'     => wp_generate_password( 64, true, true ),
			'display_name'  => $visible,
			// wp_insert_user() le agrega un sufijo si ya está tomado.
			'user_nicename' => sanitize_title( $visible ),
			'role'          => (string) users_dlx_plus_option( 'users_dlx_plus_login_role' ),
		)
	);

	if ( is_wp_error( $id ) ) {
		return 0;
	}

	users_dlx_plus_join_site( (int) $id );

	return (int) $id;
}

/** Cómo entra la gente: 'link', 'password' o 'both'. */
function users_dlx_plus_login_method(): string {
	$method = (string) users_dlx_plus_option( 'users_dlx_plus_login_method' );

	return in_array( $method, array( 'link', 'password', 'both' ), true ) ? $method : 'both';
}

/** ¿Se ofrece el enlace por correo? */
function users_dlx_plus_login_has_link(): bool {
	return 'password' !== users_dlx_plus_login_method();
}

/** ¿Se ofrece el formulario de usuario y contraseña? */
function users_dlx_plus_login_has_password(): bool {
	return 'link' !== users_dlx_plus_login_method();
}

/** ¿El enlace por correo es la única puerta? */
function users_dlx_plus_login_only_link(): bool {
	return 'link' === users_dlx_plus_login_method();
}

/**
 * Un nombre presentable a partir de un correo.
 *
 * Es lo que se muestra hasta que la persona escriba el suyo. Se queda con lo
 * que va antes de la arroba —el dominio no le dice nada a nadie— y le saca los
 * separadores para que «juan.perez88» se lea «juan perez88».
 */
function users_dlx_plus_name_from_email( string $email ): string {
	$local = (string) strstr( $email, '@', true );
	$local = '' === $local ? $email : $local;
	$local = trim( (string) preg_replace( '/[._\-]+/', ' ', $local ) );

	return '' === $local ? __( 'Someone', 'users-dlx-plus' ) : $local;
}

/** El asunto del correo, con el del sitio como respaldo. */
function users_dlx_plus_login_subject(): string {
	$subject = trim( (string) users_dlx_plus_option( 'users_dlx_plus_login_subject' ) );

	if ( '' === $subject ) {
		/* translators: %s: nombre del sitio */
		$subject = sprintf( __( 'Your sign-in link for %s', 'users-dlx-plus' ), get_bloginfo( 'name' ) );
	}

	return $subject;
}

/**
 * El cuerpo del correo. `{link}` y `{minutes}` se reemplazan.
 */
function users_dlx_plus_login_body( string $url ): string {
	$body = trim( (string) users_dlx_plus_option( 'users_dlx_plus_login_body' ) );

	if ( '' === $body ) {
		$body = __(
			"Click here to sign in:\n\n{link}\n\nThe link expires in {minutes} minutes and works once.\n\nIf you did not ask for it, ignore this message: nobody can get into your account without it.",
			'users-dlx-plus'
		);
	}

	return strtr(
		$body,
		array(
			'{link}'    => $url,
			'{minutes}' => (string) users_dlx_plus_login_expiry(),
		)
	);
}

/** Manda el correo con el enlace. */
function users_dlx_plus_login_send( int $user_id, string $email, string $token ): bool {
	$url = users_dlx_plus_login_link( $user_id, $token );

	// En desarrollo no suele haber servidor de correo. Dejar el enlace en el
	// log es lo que hace que el flujo se pueda probar de punta a punta.
	if ( 'production' !== wp_get_environment_type() ) {
		error_log( '[users-dlx-plus] enlace de acceso para ' . $email . ': ' . $url ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Filtra el correo antes de mandarlo.
	 *
	 * @param array{asunto: string, cuerpo: string} $message
	 * @param string                                $email
	 * @param string                                $url
	 */
	$message = apply_filters(
		'users_dlx_plus_login_email',
		array(
			'asunto' => users_dlx_plus_login_subject(),
			'cuerpo' => users_dlx_plus_login_body( $url ),
		),
		$email,
		$url
	);

	return wp_mail( $email, $message['asunto'], $message['cuerpo'] );
}

/**
 * Procesa el formulario de "mandame el enlace".
 *
 * Responde siempre lo mismo, haya pasado lo que haya pasado.
 */
function users_dlx_plus_login_request(): void {
	$redirect = users_dlx_plus_login_url();

	if ( ! isset( $_POST['users_dlx_plus_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['users_dlx_plus_nonce'] ) ), 'users_dlx_plus_login' ) ) {
		wp_safe_redirect( add_query_arg( 'users-dlx-plus', 'error', $redirect ) );
		exit;
	}

	// Lo escrito puede ser un correo o, si el sitio lo permite, el nombre
	// público de alguien. En el segundo caso se sigue con el correo de esa
	// cuenta: el enlace nunca sale a una dirección escrita en el momento.
	$typed = sanitize_text_field( wp_unslash( $_POST['users_dlx_plus_email'] ?? '' ) );
	$email = sanitize_email( users_dlx_plus_handle_login_email( $typed ) );

	if ( '' === $email || ! is_email( $email ) ) {
		wp_safe_redirect( add_query_arg( 'users-dlx-plus', 'email', $redirect ) );
		exit;
	}

	// Todo lo que sigue termina en la misma pantalla, exista o no la cuenta.
	$done     = add_query_arg(
		array(
			'users-dlx-plus' => 'sent',
			'email'          => rawurlencode( $email ),
		),
		$redirect
	);
	$throttle = 'users_dlx_plus_throttle_' . md5( $email );

	if ( get_transient( $throttle ) ) {
		wp_safe_redirect( $done );
		exit;
	}

	set_transient( $throttle, 1, max( 1, (int) users_dlx_plus_option( 'users_dlx_plus_login_throttle' ) ) );

	$user_id = users_dlx_plus_user_for( $email );

	if ( $user_id > 0 ) {
		users_dlx_plus_login_send( $user_id, $email, users_dlx_plus_token_create( $user_id ) );
	}

	wp_safe_redirect( $done );
	exit;
}
add_action( 'admin_post_nopriv_users_dlx_plus_acceso', 'users_dlx_plus_login_request' );
add_action( 'admin_post_users_dlx_plus_acceso', 'users_dlx_plus_login_request' );

/**
 * Consume el enlace: valida, inicia sesión y quema el token.
 */
function users_dlx_plus_login_consume(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- el token ES la credencial.
	if ( ! isset( $_GET['users_dlx_plus_login'], $_GET['users_dlx_plus_token'] ) ) {
		return;
	}

	$user_id = absint( $_GET['users_dlx_plus_login'] );
	$token   = sanitize_text_field( wp_unslash( $_GET['users_dlx_plus_token'] ) );
	// phpcs:enable

	if ( $user_id <= 0 || '' === $token || ! users_dlx_plus_token_valid( $user_id, $token ) ) {
		wp_safe_redirect( add_query_arg( 'users-dlx-plus', 'expired', users_dlx_plus_login_url() ) );
		exit;
	}

	users_dlx_plus_token_burn( $user_id );

	/**
	 * Adónde va la persona después de entrar por el enlace.
	 *
	 * Es el punto donde un sitio manda a completar el perfil la primera vez.
	 *
	 * @param string $redirect
	 * @param int    $user_id
	 */
	$redirect = (string) apply_filters( 'users_dlx_plus_login_redirect', home_url( '/' ), $user_id );

	// No se abre la sesión acá: la abre users_dlx_plus_complete_login(), que además
	// decide si antes hay que pedir un segundo factor. Todas las puertas
	// terminan en la misma función a propósito.
	users_dlx_plus_complete_login( $user_id, 'link', true, $redirect );
}
add_action( 'init', 'users_dlx_plus_login_consume' );
