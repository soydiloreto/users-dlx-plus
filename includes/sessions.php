<?php
/**
 * Sesiones abiertas: verlas y cerrarlas.
 *
 * No hay tabla propia. WordPress ya guarda cada sesión en la user meta
 * `session_tokens` con su IP, su user agent, cuándo empezó y cuándo vence;
 * mantener una copia en una tabla aparte agrega una escritura por request, se
 * desincroniza cuando una sesión vence sola, y obliga a limpiar huérfanos.
 * Acá se lee de donde WordPress lo guarda y listo.
 *
 * Lo que sí falta y se calcula al mostrar: de qué navegador y qué sistema es
 * cada sesión, que sale del user agent. No se persiste nada.
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;

/** Duración de la cookie de sesión, según los ajustes. */
function users_plus_session_duration( int $expiracion, int $user_id, bool $recordar ): int {
	$dias = $recordar
		? (int) users_plus_option( 'users_plus_session_long_days' )
		: (int) users_plus_option( 'users_plus_session_short_days' );

	return $dias > 0 ? $dias * DAY_IN_SECONDS : $expiracion;
}
add_filter( 'auth_cookie_expiration', 'users_plus_session_duration', 10, 3 );

/**
 * De qué navegador, dispositivo y sistema es una sesión.
 *
 * Se mira el user agent, que miente si alguien quiere, pero acá no es una
 * medida de seguridad: es para que la persona reconozca cuál sesión es cuál.
 *
 * @return array{browser: string, os: string, device: string}
 */
function users_plus_user_agent( string $ua ): array {
	$browser = __( 'Unknown browser', 'users-plus' );
	$os      = '';

	// El orden importa: Edge y Chrome también dicen "Safari" en su cadena.
	$browsers = array(
		'Edg/'    => 'Edge',
		'OPR/'    => 'Opera',
		'Firefox' => 'Firefox',
		'Chrome'  => 'Chrome',
		'Safari'  => 'Safari',
		'Trident' => 'Internet Explorer',
	);

	foreach ( $browsers as $needle => $name ) {
		if ( false !== strpos( $ua, $needle ) ) {
			$browser = $name;
			break;
		}
	}

	$systems = array(
		'/iphone|ipad|ipod/i' => 'iOS',
		'/android/i'          => 'Android',
		'/windows/i'          => 'Windows',
		'/macintosh|mac os/i' => 'macOS',
		'/linux/i'            => 'Linux',
	);

	foreach ( $systems as $pattern => $name ) {
		if ( preg_match( $pattern, $ua ) ) {
			$os = $name;
			break;
		}
	}

	$mobile = (bool) preg_match( '/mobile|android|iphone|ipod/i', $ua );

	return array(
		'browser' => $browser,
		'os'      => $os,
		'device'  => $mobile
			? __( 'Phone', 'users-plus' )
			: __( 'Computer', 'users-plus' ),
	);
}

/**
 * ¿Podemos identificar una sesión suelta para cerrarla?
 *
 * WordPress no expone el verificador de cada sesión: sale de la user meta que
 * usa su gestor por defecto. Si el sitio cambió el gestor —cosa rara pero
 * posible— no tocamos nada y sólo ofrecemos "cerrar las demás".
 */
function users_plus_sessions_addressable(): bool {
	return 'WP_User_Meta_Session_Tokens' === get_class( WP_Session_Tokens::get_instance( get_current_user_id() ) );
}

/**
 * Las sesiones abiertas de una persona, ordenadas de la más reciente.
 *
 * @return array<int, array<string, mixed>>
 */
function users_plus_sessions( int $user_id ): array {
	$raw     = (array) get_user_meta( $user_id, 'session_tokens', true );
	$current = get_current_user_id() === $user_id && function_exists( 'wp_get_session_token' )
		? hash( 'sha256', (string) wp_get_session_token() )
		: '';

	$sessions = array();

	foreach ( $raw as $verifier => $data ) {
		$ua = (string) ( $data['ua'] ?? '' );

		$sessions[] = array_merge(
			users_plus_user_agent( $ua ),
			array(
				'id'      => (string) $verifier,
				'ip'      => users_plus_session_ip_of( (array) $data ),
				'started' => (int) ( $data['login'] ?? 0 ),
				'expires' => (int) ( $data['expiration'] ?? 0 ),
				'current' => (string) $verifier === $current,
			)
		);
	}

	usort( $sessions, static fn( array $a, array $b ): int => $b['started'] <=> $a['started'] );

	return $sessions;
}

/**
 * Cierra una sesión suelta.
 *
 * Se escribe la user meta directamente porque WP_Session_Tokens::destroy()
 * pide el token en claro, que sólo tiene el navegador de esa sesión. El
 * formato de esa meta es el del gestor por defecto y por eso se comprueba
 * antes.
 */
function users_plus_session_close( int $user_id, string $id ): bool {
	if ( ! users_plus_sessions_addressable() ) {
		return false;
	}

	$sessions = (array) get_user_meta( $user_id, 'session_tokens', true );

	if ( ! isset( $sessions[ $id ] ) ) {
		return false;
	}

	unset( $sessions[ $id ] );

	if ( array() === $sessions ) {
		delete_user_meta( $user_id, 'session_tokens' );
	} else {
		update_user_meta( $user_id, 'session_tokens', $sessions );
	}

	return true;
}

/** Cierra todas menos la que se está usando. */
function users_plus_sessions_close_others( int $user_id ): void {
	$manager = WP_Session_Tokens::get_instance( $user_id );

	if ( get_current_user_id() === $user_id && function_exists( 'wp_get_session_token' ) ) {
		$manager->destroy_others( (string) wp_get_session_token() );
		return;
	}

	$manager->destroy_all();
}

/** Procesa los botones de la lista de sesiones. */
function users_plus_sessions_action(): void {
	if ( ! is_user_logged_in() ) {
		wp_die( esc_html__( 'You have to sign in first.', 'users-plus' ) );
	}

	check_admin_referer( 'users_plus_sessions' );

	$user_id = get_current_user_id();
	$id      = sanitize_text_field( wp_unslash( $_POST['users_plus_session'] ?? '' ) );

	if ( '' !== $id ) {
		users_plus_session_close( $user_id, $id );
	} else {
		users_plus_sessions_close_others( $user_id );
	}

	$back = wp_get_referer();

	wp_safe_redirect( add_query_arg( 'users-plus', 'sessions', $back ? $back : home_url( '/' ) ) );
	exit;
}
add_action( 'admin_post_users_plus_sessions', 'users_plus_sessions_action' );

/**
 * Los usuarios con sesiones abiertas, buscados y paginados.
 *
 * No hay combo de usuarios: con 25.000 cuentas, un <select> es medio megabyte
 * de HTML en cada carga de la pantalla. Se busca y se pagina contra la base.
 *
 * La consulta arranca por la user meta `session_tokens`, que tiene índice por
 * meta_key: quien no tiene ninguna sesión abierta ni siquiera tiene la fila, y
 * eso reduce 25.000 usuarios a los pocos que están adentro.
 *
 * @param string $search Texto libre: correo, usuario o nombre.
 * @param int    $page   Página, desde 1.
 * @param int    $per    Cuántos por página.
 * @return array{rows: array<int, array<string, mixed>>, total: int}
 */
function users_plus_sessions_search( string $search = '', int $page = 1, int $per = 20 ): array {
	global $wpdb;

	$page  = max( 1, $page );
	$per   = max( 1, min( 200, $per ) );
	$where = "m.meta_key = 'session_tokens'";
	$args  = array();

	if ( '' !== trim( $search ) ) {
		$like   = '%' . $wpdb->esc_like( trim( $search ) ) . '%';
		$where .= ' AND ( u.user_email LIKE %s OR u.user_login LIKE %s OR u.display_name LIKE %s )';
		$args   = array( $like, $like, $like );
	}

	$base = "FROM {$wpdb->usermeta} m INNER JOIN {$wpdb->users} u ON u.ID = m.user_id WHERE {$where}";

	// La tabla de sesiones no tiene una API de WordPress que la consulte, así
	// que se va al meta directamente. No se cachea a propósito: es una
	// pantalla de administración que se abre para ver el estado de ahora.
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $where se arma con marcadores y $args los llena.
	$total = (int) ( $args
		? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$base}", $args ) )
		: $wpdb->get_var( "SELECT COUNT(*) {$base}" ) );

	$sql  = "SELECT u.ID, u.user_login, u.user_email, u.display_name, m.meta_value {$base} ORDER BY u.user_email ASC LIMIT %d OFFSET %d";
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $args, array( $per, ( $page - 1 ) * $per ) ) ) );
	// phpcs:enable

	$out = array();

	foreach ( (array) $rows as $row ) {
		$tokens = maybe_unserialize( $row->meta_value );
		$tokens = is_array( $tokens ) ? $tokens : array();

		if ( array() === $tokens ) {
			continue;
		}

		// La sesión más reciente es la que describe la fila; el resto sólo
		// cuenta para el número.
		usort( $tokens, static fn( $a, $b ): int => (int) ( $b['login'] ?? 0 ) <=> (int) ( $a['login'] ?? 0 ) );
		$last = $tokens[0];

		$out[] = array_merge(
			users_plus_user_agent( (string) ( $last['ua'] ?? '' ) ),
			array(
				'user_id'  => (int) $row->ID,
				'login'    => (string) $row->user_login,
				'email'    => (string) $row->user_email,
				'name'     => (string) $row->display_name,
				'sessions' => count( $tokens ),
				'ip'       => users_plus_session_ip_of( (array) $last ),
				'started'  => (int) ( $last['login'] ?? 0 ),
				'expires'  => (int) ( $last['expiration'] ?? 0 ),
			)
		);
	}

	return array(
		'rows'  => $out,
		'total' => $total,
	);
}

/** Cierra todas las sesiones de un usuario. Sólo para quien administra. */
function users_plus_sessions_admin_close(): void {
	if ( ! current_user_can( 'edit_users' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'users-plus' ) );
	}

	check_admin_referer( 'users_plus_sessions_admin' );

	$user_id = absint( $_POST['users_plus_user'] ?? 0 );

	if ( $user_id > 0 ) {
		WP_Session_Tokens::get_instance( $user_id )->destroy_all();
	}

	$back = wp_get_referer();
	wp_safe_redirect( add_query_arg( 'users_plus_done', 'closed', $back ? $back : admin_url( 'admin.php?page=users-plus-sessions' ) ) );
	exit;
}
add_action( 'admin_post_users_plus_sessions_admin', 'users_plus_sessions_admin_close' );
