<?php
/**
 * El nombre público.
 *
 * Son tres cosas distintas que WordPress mezcla y conviene separar:
 *
 *   - `user_login` es el correo, es la identidad, y WordPress no deja
 *     cambiarlo nunca. No se elige ni se muestra.
 *   - `user_nicename` es lo que va en la URL del perfil.
 *   - `display_name` es el nombre con el que aparece la persona.
 *
 * El nombre público de acá es el segundo, y de paso se guarda como `nickname`.
 * Así alguien puede elegir cómo lo ven y con qué dirección lo encuentran, sin
 * que eso toque cómo entra: el enlace siempre sale al correo de la cuenta.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

/** Nombres que no se pueden pedir: se confunden con partes del sitio. */
function upfw_handle_reserved(): array {
	$base = array(
		'admin', 'administrator', 'administrador', 'root', 'sistema', 'system',
		'soporte', 'support', 'ayuda', 'help', 'api', 'wp-admin', 'wp-login',
		'login', 'logout', 'registro', 'register', 'cuenta', 'account', 'perfil',
		'profile', 'usuario', 'user', 'usuarios', 'users', 'null', 'undefined',
	);

	$extra = preg_split( '/[\s,]+/', (string) upfw_option( 'upfw_handle_reserved' ), -1, PREG_SPLIT_NO_EMPTY );

	return array_values( array_unique( array_map( 'sanitize_title', array_merge( $base, is_array( $extra ) ? $extra : array() ) ) ) );
}

/**
 * Cómo queda un nombre después de pasarlo por las reglas.
 *
 * Es la misma función con la que WordPress arma un `user_nicename`, así que lo
 * que devuelve es exactamente lo que va a quedar en la dirección. Se usa para
 * mostrarlo antes de guardar: nadie tiene que adivinar en qué se convierte lo
 * que escribió.
 */
function upfw_handle_clean( string $handle ): string {
	return 'unicode' === upfw_option( 'upfw_handle_charset' )
		? sanitize_title( $handle, '', 'save' )
		: sanitize_title( remove_accents( $handle ) );
}

/** El nombre público de alguien. Vacío si nunca eligió uno. */
function upfw_handle( int $user_id ): string {
	return (string) get_user_meta( $user_id, 'upfw_handle', true );
}

/** Cuándo lo cambió por última vez. 0 si nunca. */
function upfw_handle_changed( int $user_id ): int {
	return (int) get_user_meta( $user_id, 'upfw_handle_changed', true );
}

/** ¿Puede cambiarlo hoy, o todavía está esperando? */
function upfw_handle_can_change( int $user_id ): bool {
	$dias = (int) upfw_option( 'upfw_handle_cooldown' );
	$ultimo = upfw_handle_changed( $user_id );

	return $dias <= 0 || 0 === $ultimo || ( time() - $ultimo ) >= $dias * DAY_IN_SECONDS;
}

/** Cuándo va a poder cambiarlo. 0 si ya puede. */
function upfw_handle_next_change( int $user_id ): int {
	if ( upfw_handle_can_change( $user_id ) ) {
		return 0;
	}

	return upfw_handle_changed( $user_id ) + (int) upfw_option( 'upfw_handle_cooldown' ) * DAY_IN_SECONDS;
}

/**
 * Revisa un nombre público.
 *
 * Devuelve el nombre saneado, o un WP_Error con el motivo. Los motivos se
 * cuentan en castellano de a uno: «no sirve» no le dice a nadie qué arreglar.
 *
 * @return string|WP_Error
 */
function upfw_handle_validate( string $handle, int $user_id ) {
	$handle = trim( $handle );
	$min    = max( 1, (int) upfw_option( 'upfw_handle_min' ) );
	$max    = max( $min, (int) upfw_option( 'upfw_handle_max' ) );

	$clean = upfw_handle_clean( $handle );

	if ( '' === $clean ) {
		return new WP_Error( 'upfw_handle_empty', __( 'You have to write something.', 'users-plus-for-wordpress' ) );
	}

	// Los espacios no pueden quedar: una dirección no los tiene. O se cambian
	// por guiones sin decir nada —que es lo que espera casi todo el mundo— o
	// se avisa, para que nadie se quede con un nombre que no escribió.
	if ( 'reject' === upfw_option( 'upfw_handle_spaces' ) && preg_match( '/\s/', $handle ) ) {
		return new WP_Error( 'upfw_handle_spaces', __( 'It cannot have spaces: this goes in a web address.', 'users-plus-for-wordpress' ) );
	}

	if ( is_email( $handle ) ) {
		return new WP_Error( 'upfw_handle_email', __( 'It cannot be an email address: that is how you sign in, not how people see you.', 'users-plus-for-wordpress' ) );
	}

	if ( mb_strlen( $clean ) < $min ) {
		return new WP_Error(
			'upfw_handle_short',
			sprintf(
				/* translators: %d: cantidad mínima de caracteres */
				__( 'It is too short: at least %d characters.', 'users-plus-for-wordpress' ),
				$min
			)
		);
	}

	if ( mb_strlen( $clean ) > $max ) {
		return new WP_Error(
			'upfw_handle_long',
			sprintf(
				/* translators: %d: cantidad máxima de caracteres */
				__( 'It is too long: at most %d characters.', 'users-plus-for-wordpress' ),
				$max
			)
		);
	}

	if ( in_array( $clean, upfw_handle_reserved(), true ) ) {
		return new WP_Error( 'upfw_handle_reserved', __( 'That one is taken by the site itself. Pick another.', 'users-plus-for-wordpress' ) );
	}

	if ( upfw_handle_taken( $clean, $user_id ) ) {
		return new WP_Error( 'upfw_handle_taken', __( 'Somebody already has that one.', 'users-plus-for-wordpress' ) );
	}

	return $clean;
}

/**
 * ¿Ya lo tiene alguien?
 *
 * Se mira contra `user_nicename` y también contra `user_login`. Lo segundo
 * parece de más y no lo es: las cuentas que vienen de la migración tienen un
 * user_login que es un nombre de persona, y si además se puede entrar
 * escribiendo el nombre público, dos personas distintas respondiendo al mismo
 * texto hace que el enlace de acceso salga a la cuenta equivocada.
 */
function upfw_handle_taken( string $handle, int $user_id ): bool {
	global $wpdb;

	$found = $wpdb->get_var( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->users} WHERE ( user_nicename = %s OR user_login = %s ) AND ID <> %d LIMIT 1",
		$handle,
		$handle,
		$user_id
	) );

	return null !== $found;
}

/**
 * Guarda el nombre público.
 *
 * @return true|WP_Error
 */
function upfw_handle_save( int $user_id, string $handle ) {
	if ( upfw_handle( $user_id ) === $handle ) {
		return true;
	}

	if ( ! upfw_handle_can_change( $user_id ) ) {
		return new WP_Error(
			'upfw_handle_cooldown',
			sprintf(
				/* translators: %s: fecha a partir de la cual se puede cambiar */
				__( 'You can change it again on %s.', 'users-plus-for-wordpress' ),
				wp_date( 'j M Y', upfw_handle_next_change( $user_id ) )
			)
		);
	}

	$clean = upfw_handle_validate( $handle, $user_id );

	if ( is_wp_error( $clean ) ) {
		return $clean;
	}

	$updated = wp_update_user( array( 'ID' => $user_id, 'user_nicename' => $clean, 'nickname' => $clean ) );

	if ( is_wp_error( $updated ) ) {
		return $updated;
	}

	update_user_meta( $user_id, 'upfw_handle', $clean );
	update_user_meta( $user_id, 'upfw_handle_changed', time() );

	return true;
}

/** Quién responde a este nombre público. 0 si nadie. */
function upfw_handle_user( string $handle ): int {
	global $wpdb;

	$clean = sanitize_title( remove_accents( $handle ) );

	if ( '' === $clean ) {
		return 0;
	}

	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->users} WHERE user_nicename = %s OR user_login = %s LIMIT 1",
		$clean,
		$clean
	) );
}

/* ── El formulario ─────────────────────────────────────────────────── */

/**
 * Sólo el campo, sin formulario.
 *
 * Un sitio que ya tiene un formulario de datos personales no quiere otro
 * formulario al lado con su propio botón: quiere el campo adentro del suyo y
 * un solo «Guardar». Eso es esto.
 */
function upfw_handle_field( ?int $user_id = null ): string {
	$user_id = $user_id ?? get_current_user_id();

	if ( $user_id <= 0 || ! upfw_option( 'upfw_handle_enabled' ) ) {
		return '';
	}

	upfw_handle_enqueue();

	$user = get_userdata( $user_id );

	return upfw_render( 'account/handle-field', array(
		'handle' => '' !== upfw_handle( $user_id ) ? upfw_handle( $user_id ) : ( $user instanceof WP_User ? $user->user_nicename : '' ),
		'can'    => upfw_handle_can_change( $user_id ),
		'next'   => upfw_handle_next_change( $user_id ),
	) );
}

/** El campo del nombre público. Shortcode: [upfw_handle] */
function upfw_shortcode_handle(): string {
	if ( ! is_user_logged_in() || ! upfw_option( 'upfw_handle_enabled' ) ) {
		return '';
	}

	$user = wp_get_current_user();

	upfw_handle_enqueue();

	return upfw_render( 'account/handle', array(
		'user'    => $user,
		'handle'  => '' !== upfw_handle( $user->ID ) ? upfw_handle( $user->ID ) : $user->user_nicename,
		'can'     => upfw_handle_can_change( $user->ID ),
		'next'    => upfw_handle_next_change( $user->ID ),
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige el mensaje.
		'error'   => isset( $_GET['upfw_handle'] ) ? sanitize_text_field( wp_unslash( $_GET['upfw_handle'] ) ) : '',
	) );
}
add_shortcode( 'upfw_handle', 'upfw_shortcode_handle' );

/** Guarda el nombre público desde el front. */
function upfw_handle_submit(): void {
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( upfw_login_url() );
		exit;
	}

	check_admin_referer( 'upfw_handle' );

	$destino = upfw_account_url( 'details' );
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
	$result  = upfw_handle_save( get_current_user_id(), sanitize_text_field( wp_unslash( $_POST['upfw_handle'] ?? '' ) ) );

	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( add_query_arg( 'upfw_handle', rawurlencode( $result->get_error_message() ), $destino ) );
		exit;
	}

	wp_safe_redirect( add_query_arg( 'upfw', 'saved', $destino ) );
	exit;
}
add_action( 'admin_post_upfw_handle', 'upfw_handle_submit' );

/**
 * ¿Está libre este nombre?
 *
 * Responde la misma validación que corre al guardar —no hay dos juegos de
 * reglas— así que lo que se lee acá es exactamente lo que va a pasar después.
 * Se contesta sólo a quien tiene sesión: si no, esto sería una forma cómoda de
 * averiguar qué nombres existen en el sitio.
 */
function upfw_handle_check(): void {
	check_ajax_referer( 'upfw_handle_check', 'nonce' );

	$user_id = get_current_user_id();

	if ( $user_id <= 0 ) {
		wp_send_json_error();
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
	$clean = upfw_handle_validate( sanitize_text_field( wp_unslash( $_POST['handle'] ?? '' ) ), $user_id );

	if ( is_wp_error( $clean ) ) {
		wp_send_json_success( array(
			'free'   => false,
			'motivo' => $clean->get_error_message(),
		) );
	}

	wp_send_json_success( array(
		'free'   => true,
		'url'    => upfw_handle_base_url() . $clean . '/',
		'motivo' => upfw_handle( $user_id ) === $clean
			? __( 'This is the one you have now.', 'users-plus-for-wordpress' )
			: __( 'Nobody is using it: it is yours when you save.', 'users-plus-for-wordpress' ),
	) );
}
add_action( 'wp_ajax_upfw_handle_check', 'upfw_handle_check' );

/**
 * Entrar escribiendo el nombre público.
 *
 * El formulario de acceso pide un correo; si lo que llegó no lo es y esto está
 * prendido, se busca a quién corresponde y se sigue con SU correo. El enlace
 * nunca sale a una dirección que la persona haya escrito en ese momento: sale
 * a la de la cuenta, que es lo que hace que esto no abra una puerta nueva.
 */
function upfw_handle_login_email( string $typed ): string {
	if ( is_email( $typed ) || ! upfw_option( 'upfw_handle_login' ) ) {
		return $typed;
	}

	$user_id = upfw_handle_user( $typed );

	if ( $user_id <= 0 ) {
		return $typed;
	}

	$user = get_userdata( $user_id );

	return $user instanceof WP_User ? $user->user_email : $typed;
}

/**
 * El script que muestra en qué se convierte lo que se escribe.
 *
 * No valida nada —eso lo hace el servidor— sólo evita la sorpresa de escribir
 * «Pablo Di Loreto» y descubrir después que quedó «pablo-di-loreto».
 */
function upfw_handle_enqueue(): void {
	if ( wp_script_is( 'upfw-handle', 'enqueued' ) ) {
		return;
	}

	wp_enqueue_script( 'upfw-handle', UPFW_URL . 'assets/upfw-handle.js', array(), upfw_asset_version( 'assets/upfw-handle.js' ), true );

	wp_localize_script( 'upfw-handle', 'upfwHandle', array(
		'base'      => upfw_handle_base_url(),
		'unicode'   => (bool) ( 'unicode' === upfw_option( 'upfw_handle_charset' ) ),
		'ajax'      => admin_url( 'admin-ajax.php' ),
		'nonce'     => wp_create_nonce( 'upfw_handle_check' ),
		'checking'  => __( 'Checking…', 'users-plus-for-wordpress' ),
		'error'     => __( 'We could not check it right now.', 'users-plus-for-wordpress' ),
	) );
}

/**
 * La dirección donde se ve el perfil público de alguien.
 *
 * Con bbPress instalado es la de sus foros, que es la que la gente comparte;
 * si no, la de autor que trae WordPress.
 */
function upfw_handle_base_url(): string {
	$base = function_exists( 'bbp_get_user_profile_url' )
		? (string) bbp_get_user_profile_url( get_current_user_id() )
		: (string) get_author_posts_url( get_current_user_id() );

	// Se le saca el último tramo, que es justamente el nombre.
	$base = untrailingslashit( $base );

	return trailingslashit( substr( $base, 0, (int) strrpos( $base, '/' ) ) );
}
