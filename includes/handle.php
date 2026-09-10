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
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;

/** Nombres que no se pueden pedir: se confunden con partes del sitio. */
/**
 * @return array<int, string>
 */
function users_dlx_plus_handle_reserved(): array {
	$base = array(
		'admin', 'administrator', 'administrador', 'root', 'sistema', 'system',
		'soporte', 'support', 'ayuda', 'help', 'api', 'wp-admin', 'wp-login',
		'login', 'logout', 'registro', 'register', 'cuenta', 'account', 'perfil',
		'profile', 'usuario', 'user', 'usuarios', 'users', 'null', 'undefined',
	);

	$extra = preg_split( '/[\s,]+/', (string) users_dlx_plus_option( 'users_dlx_plus_handle_reserved' ), -1, PREG_SPLIT_NO_EMPTY );

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
function users_dlx_plus_handle_clean( string $handle ): string {
	return 'unicode' === users_dlx_plus_option( 'users_dlx_plus_handle_charset' )
		? sanitize_title( $handle, '', 'save' )
		: sanitize_title( remove_accents( $handle ) );
}

/** El nombre público de alguien. Vacío si nunca eligió uno. */
function users_dlx_plus_handle( int $user_id ): string {
	return (string) get_user_meta( $user_id, 'users_dlx_plus_handle', true );
}

/** Cuándo lo cambió por última vez. 0 si nunca. */
function users_dlx_plus_handle_changed( int $user_id ): int {
	return (int) get_user_meta( $user_id, 'users_dlx_plus_handle_changed', true );
}

/** ¿Puede cambiarlo hoy, o todavía está esperando? */
function users_dlx_plus_handle_can_change( int $user_id ): bool {
	$dias   = (int) users_dlx_plus_option( 'users_dlx_plus_handle_cooldown' );
	$ultimo = users_dlx_plus_handle_changed( $user_id );

	return $dias <= 0 || 0 === $ultimo || ( time() - $ultimo ) >= $dias * DAY_IN_SECONDS;
}

/** Cuándo va a poder cambiarlo. 0 si ya puede. */
function users_dlx_plus_handle_next_change( int $user_id ): int {
	if ( users_dlx_plus_handle_can_change( $user_id ) ) {
		return 0;
	}

	return users_dlx_plus_handle_changed( $user_id ) + (int) users_dlx_plus_option( 'users_dlx_plus_handle_cooldown' ) * DAY_IN_SECONDS;
}

/**
 * Revisa un nombre público.
 *
 * Devuelve el nombre saneado, o un WP_Error con el motivo. Los motivos se
 * cuentan en castellano de a uno: «no sirve» no le dice a nadie qué arreglar.
 *
 * @return string|WP_Error
 */
function users_dlx_plus_handle_validate( string $handle, int $user_id ) {
	$handle = trim( $handle );
	$min    = max( 1, (int) users_dlx_plus_option( 'users_dlx_plus_handle_min' ) );
	$max    = max( $min, (int) users_dlx_plus_option( 'users_dlx_plus_handle_max' ) );

	$clean = users_dlx_plus_handle_clean( $handle );

	if ( '' === $clean ) {
		return new WP_Error( 'users_dlx_plus_handle_empty', __( 'You have to write something.', 'users-dlx-plus' ) );
	}

	// Los espacios no pueden quedar: una dirección no los tiene. O se cambian
	// por guiones sin decir nada —que es lo que espera casi todo el mundo— o
	// se avisa, para que nadie se quede con un nombre que no escribió.
	if ( 'reject' === users_dlx_plus_option( 'users_dlx_plus_handle_spaces' ) && preg_match( '/\s/', $handle ) ) {
		return new WP_Error( 'users_dlx_plus_handle_spaces', __( 'It cannot have spaces: this goes in a web address.', 'users-dlx-plus' ) );
	}

	if ( is_email( $handle ) ) {
		return new WP_Error( 'users_dlx_plus_handle_email', __( 'It cannot be an email address: that is how you sign in, not how people see you.', 'users-dlx-plus' ) );
	}

	if ( mb_strlen( $clean ) < $min ) {
		return new WP_Error(
			'users_dlx_plus_handle_short',
			sprintf(
				/* translators: %d: cantidad mínima de caracteres */
				__( 'It is too short: at least %d characters.', 'users-dlx-plus' ),
				$min
			)
		);
	}

	if ( mb_strlen( $clean ) > $max ) {
		return new WP_Error(
			'users_dlx_plus_handle_long',
			sprintf(
				/* translators: %d: cantidad máxima de caracteres */
				__( 'It is too long: at most %d characters.', 'users-dlx-plus' ),
				$max
			)
		);
	}

	if ( in_array( $clean, users_dlx_plus_handle_reserved(), true ) ) {
		return new WP_Error( 'users_dlx_plus_handle_reserved', __( 'That one is taken by the site itself. Pick another.', 'users-dlx-plus' ) );
	}

	if ( users_dlx_plus_handle_taken( $clean, $user_id ) ) {
		return new WP_Error( 'users_dlx_plus_handle_taken', __( 'Somebody already has that one.', 'users-dlx-plus' ) );
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
function users_dlx_plus_handle_taken( string $handle, int $user_id ): bool {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- no hay API para buscar contra dos columnas de wp_users, y una respuesta cacheada acá diría que un nombre está libre cuando ya no lo está.
	$found = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->users} WHERE ( user_nicename = %s OR user_login = %s ) AND ID <> %d LIMIT 1",
			$handle,
			$handle,
			$user_id
		)
	);

	return null !== $found;
}

/**
 * Guarda el nombre público.
 *
 * @return true|WP_Error
 */
function users_dlx_plus_handle_save( int $user_id, string $handle ) {
	if ( users_dlx_plus_handle( $user_id ) === $handle ) {
		return true;
	}

	if ( ! users_dlx_plus_handle_can_change( $user_id ) ) {
		return new WP_Error(
			'users_dlx_plus_handle_cooldown',
			sprintf(
				/* translators: %s: fecha a partir de la cual se puede cambiar */
				__( 'You can change it again on %s.', 'users-dlx-plus' ),
				wp_date( 'j M Y', users_dlx_plus_handle_next_change( $user_id ) )
			)
		);
	}

	$clean = users_dlx_plus_handle_validate( $handle, $user_id );

	if ( is_wp_error( $clean ) ) {
		return $clean;
	}

	$updated = wp_update_user(
		array(
			'ID'            => $user_id,
			'user_nicename' => $clean,
			'nickname'      => $clean,
		)
	);

	if ( is_wp_error( $updated ) ) {
		return $updated;
	}

	update_user_meta( $user_id, 'users_dlx_plus_handle', $clean );
	update_user_meta( $user_id, 'users_dlx_plus_handle_changed', time() );

	return true;
}

/** Quién responde a este nombre público. 0 si nadie. */
function users_dlx_plus_handle_user( string $handle ): int {
	global $wpdb;

	$clean = sanitize_title( remove_accents( $handle ) );

	if ( '' === $clean ) {
		return 0;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ídem: dos columnas, y es la consulta que decide a quién sale un enlace de acceso.
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->users} WHERE user_nicename = %s OR user_login = %s LIMIT 1",
			$clean,
			$clean
		)
	);
}

/* ── El formulario ─────────────────────────────────────────────────── */

/**
 * Sólo el campo, sin formulario.
 *
 * Un sitio que ya tiene un formulario de datos personales no quiere otro
 * formulario al lado con su propio botón: quiere el campo adentro del suyo y
 * un solo «Guardar». Eso es esto.
 */
function users_dlx_plus_handle_field( ?int $user_id = null ): string {
	$user_id = $user_id ?? get_current_user_id();

	if ( $user_id <= 0 || ! users_dlx_plus_option( 'users_dlx_plus_handle_enabled' ) ) {
		return '';
	}

	users_dlx_plus_handle_enqueue();

	$user = get_userdata( $user_id );

	return users_dlx_plus_render(
		'account/handle-field',
		array(
			'handle' => '' !== users_dlx_plus_handle( $user_id ) ? users_dlx_plus_handle( $user_id ) : ( $user instanceof WP_User ? $user->user_nicename : '' ),
			'can'    => users_dlx_plus_handle_can_change( $user_id ),
			'next'   => users_dlx_plus_handle_next_change( $user_id ),
		)
	);
}

/** El campo del nombre público. Shortcode: [users_dlx_plus_handle] */
function users_dlx_plus_shortcode_handle(): string {
	if ( ! is_user_logged_in() || ! users_dlx_plus_option( 'users_dlx_plus_handle_enabled' ) ) {
		return '';
	}

	$user = wp_get_current_user();

	users_dlx_plus_handle_enqueue();

	return users_dlx_plus_render(
		'account/handle',
		array(
			'user'   => $user,
			'handle' => '' !== users_dlx_plus_handle( $user->ID ) ? users_dlx_plus_handle( $user->ID ) : $user->user_nicename,
			'can'    => users_dlx_plus_handle_can_change( $user->ID ),
			'next'   => users_dlx_plus_handle_next_change( $user->ID ),
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige el mensaje.
			'error'  => isset( $_GET['users_dlx_plus_handle'] ) ? sanitize_text_field( wp_unslash( $_GET['users_dlx_plus_handle'] ) ) : '',
		)
	);
}
add_shortcode( 'users_dlx_plus_handle', 'users_dlx_plus_shortcode_handle' );

/** Guarda el nombre público desde el front. */
function users_dlx_plus_handle_submit(): void {
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( users_dlx_plus_login_url() );
		exit;
	}

	check_admin_referer( 'users_dlx_plus_handle' );

	$destino = users_dlx_plus_account_url( 'details' );
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
	$result = users_dlx_plus_handle_save( get_current_user_id(), sanitize_text_field( wp_unslash( $_POST['users_dlx_plus_handle'] ?? '' ) ) );

	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( add_query_arg( 'users_dlx_plus_handle', rawurlencode( $result->get_error_message() ), $destino ) );
		exit;
	}

	wp_safe_redirect( add_query_arg( 'users-dlx-plus', 'saved', $destino ) );
	exit;
}
add_action( 'admin_post_users_dlx_plus_handle', 'users_dlx_plus_handle_submit' );

/**
 * ¿Está libre este nombre?
 *
 * Responde la misma validación que corre al guardar —no hay dos juegos de
 * reglas— así que lo que se lee acá es exactamente lo que va a pasar después.
 * Se contesta sólo a quien tiene sesión: si no, esto sería una forma cómoda de
 * averiguar qué nombres existen en el sitio.
 */
function users_dlx_plus_handle_check(): void {
	check_ajax_referer( 'users_dlx_plus_handle_check', 'nonce' );

	$user_id = get_current_user_id();

	if ( $user_id <= 0 ) {
		wp_send_json_error();
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
	$clean = users_dlx_plus_handle_validate( sanitize_text_field( wp_unslash( $_POST['handle'] ?? '' ) ), $user_id );

	if ( is_wp_error( $clean ) ) {
		wp_send_json_success(
			array(
				'free'   => false,
				'motivo' => $clean->get_error_message(),
			)
		);
	}

	wp_send_json_success(
		array(
			'free'   => true,
			'url'    => users_dlx_plus_handle_base_url() . $clean . '/',
			'motivo' => users_dlx_plus_handle( $user_id ) === $clean
				? __( 'This is the one you have now.', 'users-dlx-plus' )
				: __( 'Nobody is using it: it is yours when you save.', 'users-dlx-plus' ),
		)
	);
}
add_action( 'wp_ajax_users_dlx_plus_handle_check', 'users_dlx_plus_handle_check' );

/**
 * Entrar escribiendo el nombre público.
 *
 * El formulario de acceso pide un correo; si lo que llegó no lo es y esto está
 * prendido, se busca a quién corresponde y se sigue con SU correo. El enlace
 * nunca sale a una dirección que la persona haya escrito en ese momento: sale
 * a la de la cuenta, que es lo que hace que esto no abra una puerta nueva.
 */
function users_dlx_plus_handle_login_email( string $typed ): string {
	if ( is_email( $typed ) || ! users_dlx_plus_option( 'users_dlx_plus_handle_login' ) ) {
		return $typed;
	}

	$user_id = users_dlx_plus_handle_user( $typed );

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
function users_dlx_plus_handle_enqueue(): void {
	if ( wp_script_is( 'users-dlx-plus-handle', 'enqueued' ) ) {
		return;
	}

	wp_enqueue_script( 'users-dlx-plus-handle', USERS_DLX_PLUS_URL . 'assets/users-dlx-plus-handle.js', array(), users_dlx_plus_asset_version( 'assets/users-dlx-plus-handle.js' ), true );

	wp_localize_script(
		'users-dlx-plus-handle',
		'usersDlxPlusHandle',
		array(
			'base'     => users_dlx_plus_handle_base_url(),
			'unicode'  => (bool) ( 'unicode' === users_dlx_plus_option( 'users_dlx_plus_handle_charset' ) ),
			'ajax'     => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'users_dlx_plus_handle_check' ),
			'checking' => __( 'Checking…', 'users-dlx-plus' ),
			'error'    => __( 'We could not check it right now.', 'users-dlx-plus' ),
		)
	);
}

/**
 * La dirección donde se ve el perfil público de alguien.
 *
 * Con bbPress instalado es la de sus foros, que es la que la gente comparte;
 * si no, la de autor que trae WordPress.
 */
function users_dlx_plus_handle_base_url(): string {
	$base = function_exists( 'bbp_get_user_profile_url' )
		? (string) bbp_get_user_profile_url( get_current_user_id() )
		: (string) get_author_posts_url( get_current_user_id() );

	// Se le saca el último tramo, que es justamente el nombre.
	$base = untrailingslashit( $base );

	return trailingslashit( substr( $base, 0, (int) strrpos( $base, '/' ) ) );
}
