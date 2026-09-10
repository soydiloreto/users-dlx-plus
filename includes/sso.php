<?php
/**
 * Entrar con una red social (SSO por OAuth 2).
 *
 * Está deliberadamente modelado como Nextend Social Login: un proveedor por
 * red, con su ID de cliente y su secreto, una URL de retorno que se copia y se
 * pega en la consola del proveedor, y la cuenta que se vincula por correo.
 *
 * Lo que cambia respecto de Nextend: los proveedores comparten un solo flujo.
 * Acá está el motor; la tabla de proveedores vive en sso-providers.php.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

/** Las credenciales guardadas de un proveedor. */
function upfw_sso_credentials( string $id ): array {
	$all = (array) get_option( 'upfw_sso', array() );

	return array(
		'active' => ! empty( $all[ $id ]['active'] ),
		'id'     => (string) ( $all[ $id ]['id'] ?? '' ),
		'secret' => (string) ( $all[ $id ]['secret'] ?? '' ),
	);
}

/** Guarda las credenciales de un proveedor sin tocar las de los demás. */
function upfw_sso_save_credentials( string $id, array $values ): void {
	$all      = (array) get_option( 'upfw_sso', array() );
	$previous = upfw_sso_credentials( $id );

	$new = array(
		'active' => empty( $values['active'] ) ? 0 : 1,
		'id'     => sanitize_text_field( (string) ( $values['id'] ?? '' ) ),
		'secret' => sanitize_text_field( (string) ( $values['secret'] ?? '' ) ),
	);

	// Si cambiaron las credenciales, lo que se probó fue otra cosa.
	$cambio = $new['id'] !== $previous['id'] || $new['secret'] !== $previous['secret'];

	$new['tested'] = $cambio ? 0 : ( upfw_sso_tested( $id ) ? 1 : 0 );

	// Y nadie prende un proveedor sin probarlo.
	if ( ! $new['tested'] ) {
		$new['active'] = 0;
	}

	$all[ $id ] = $new;

	update_option( 'upfw_sso', $all );
}

/**
 * En qué estado está un proveedor.
 *
 * Los cuatro estados son los de Nextend y el orden importa: no se puede
 * prender uno que no se probó. Probarlo es hacer el ida y vuelta de verdad
 * contra el proveedor — es la única forma de saber que el ID, el secreto y la
 * URL de retorno están bien, y de que nadie se entere de que no lo están
 * porque no pudo entrar.
 *
 * @return string not-configured | not-tested | disabled | enabled
 */
function upfw_sso_state( string $id ): string {
	if ( ! upfw_sso_configured( $id ) ) {
		return 'not-configured';
	}

	if ( ! upfw_sso_tested( $id ) ) {
		return 'not-tested';
	}

	return upfw_sso_credentials( $id )['active'] ? 'enabled' : 'disabled';
}

/** ¿Se probó y funcionó? */
function upfw_sso_tested( string $id ): bool {
	$all = (array) get_option( 'upfw_sso', array() );

	return ! empty( $all[ $id ]['tested'] );
}

/** Marca un proveedor como probado, o le saca la marca. */
function upfw_sso_set_tested( string $id, bool $tested ): void {
	$all = (array) get_option( 'upfw_sso', array() );

	$all[ $id ]           = (array) ( $all[ $id ] ?? array() );
	$all[ $id ]['tested'] = $tested ? 1 : 0;

	update_option( 'upfw_sso', $all );
}

/** ¿Tiene credenciales cargadas? */
function upfw_sso_configured( string $id ): bool {
	$c = upfw_sso_credentials( $id );

	return '' !== $c['id'] && '' !== $c['secret'];
}

/** ¿Está listo para que entre gente? Configurado, probado y prendido. */
function upfw_sso_ready( string $id ): bool {
	return 'enabled' === upfw_sso_state( $id );
}

/** Los proveedores que se pueden mostrar hoy. */
function upfw_sso_available(): array {
	return array_filter(
		upfw_sso_providers(),
		static fn( array $p, string $id ): bool => upfw_sso_ready( $id ),
		ARRAY_FILTER_USE_BOTH
	);
}

/**
 * El tramo de la URL donde vive el ida y vuelta con las redes.
 *
 * Se puede mover con el filtro por si un sitio ya tiene una página en /sso/.
 * Cambiarlo obliga a repasar las consolas de los proveedores, porque la URL
 * de retorno queda registrada allá.
 */
function upfw_sso_base(): string {
	return trim( (string) apply_filters( 'upfw_sso_base', 'sso' ), '/' );
}

/**
 * La URL de retorno que hay que pegar en la consola del proveedor.
 *
 * Es una dirección con ruta y sin parámetros a propósito: Microsoft Entra
 * rechaza de plano una URL de retorno con query string («URL may not contain a
 * query string») y Apple hace lo mismo. Lo que antes era `/?upfw_sso=google`
 * dejaba afuera a esos dos, así que la ruta es el camino que sirve para todos.
 *
 * Con enlaces permanentes «simples» no hay ruta posible y se vuelve al
 * parámetro, que es lo único que WordPress puede resolver en ese modo.
 */
function upfw_sso_redirect_uri( string $id ): string {
	if ( '' === (string) get_option( 'permalink_structure' ) ) {
		return add_query_arg( 'upfw_sso', $id, home_url( '/' ) );
	}

	return home_url( '/' . upfw_sso_base() . '/' . $id . '/' );
}

/** La URL que dispara el ida y vuelta. */
function upfw_sso_login_url( string $id ): string {
	return add_query_arg( 'upfw_go', 1, upfw_sso_redirect_uri( $id ) );
}

/** La URL de la prueba en vivo, para abrir en una ventana aparte. */
function upfw_sso_test_url( string $id ): string {
	return wp_nonce_url(
		add_query_arg(
			array(
				'upfw_go'   => 1,
				'upfw_test' => 1,
			),
			upfw_sso_redirect_uri( $id )
		),
		'upfw_sso_test_' . $id,
		'upfw_nonce'
	);
}

/** La regla que hace posible /sso/<red>/. */
function upfw_sso_rule(): void {
	add_rewrite_rule(
		'^' . preg_quote( upfw_sso_base(), '/' ) . '/([a-z0-9_-]+)/?$',
		'index.php?upfw_sso=$matches[1]',
		'top'
	);
}
add_action( 'init', 'upfw_sso_rule' );

/** Sin esto WordPress descarta el valor que capturó la regla. */
function upfw_sso_query_var( array $vars ): array {
	$vars[] = 'upfw_sso';

	return $vars;
}
add_filter( 'query_vars', 'upfw_sso_query_var' );

/* ── Lectura del perfil de cada proveedor ──────────────────────────── */

/**
 * OpenID Connect (Google, Microsoft, LinkedIn, Yahoo, Twitch, GitLab): el
 * perfil ya viene con nombres estándar.
 *
 * @param array<string, mixed> $data
 * @return array{id: string, email: string, name: string, last_name: string}
 */
function upfw_sso_map_oidc( array $data, string $token ): array {
	return array(
		'id'        => (string) ( $data['sub'] ?? '' ),
		'email'     => (string) ( $data['email'] ?? '' ),
		'name'      => (string) ( $data['given_name'] ?? $data['preferred_username'] ?? '' ),
		'last_name' => (string) ( $data['family_name'] ?? '' ),
	);
}

/** Facebook usa first_name / last_name. */
function upfw_sso_map_facebook( array $data, string $token ): array {
	return array(
		'id'        => (string) ( $data['id'] ?? '' ),
		'email'     => (string) ( $data['email'] ?? '' ),
		'name'      => (string) ( $data['first_name'] ?? '' ),
		'last_name' => (string) ( $data['last_name'] ?? '' ),
	);
}

/**
 * GitHub manda un solo campo `name` y esconde el correo si es privado: hay que
 * pedirlo aparte y quedarse con el primario verificado.
 */
function upfw_sso_map_github( array $data, string $token ): array {
	$email = (string) ( $data['email'] ?? '' );

	if ( '' === $email ) {
		foreach ( (array) upfw_sso_get( 'https://api.github.com/user/emails', $token ) as $row ) {
			if ( ! empty( $row['primary'] ) && ! empty( $row['verified'] ) ) {
				$email = (string) $row['email'];
				break;
			}
		}
	}

	return array_merge(
		upfw_sso_split_name( (string) ( $data['name'] ?? '' ) ),
		array(
			'id'    => (string) ( $data['id'] ?? '' ),
			'email' => $email,
		)
	);
}

/** WordPress.com devuelve el perfil bajo claves propias. */
function upfw_sso_map_wordpress( array $data, string $token ): array {
	return array_merge(
		upfw_sso_split_name( (string) ( $data['display_name'] ?? '' ) ),
		array(
			'id'    => (string) ( $data['ID'] ?? '' ),
			'email' => (string) ( $data['email'] ?? '' ),
		)
	);
}

/** Discord: el correo viene sólo si se pidió el scope `email`. */
function upfw_sso_map_discord( array $data, string $token ): array {
	return array_merge(
		upfw_sso_split_name( (string) ( $data['global_name'] ?? $data['username'] ?? '' ) ),
		array(
			'id'    => (string) ( $data['id'] ?? '' ),
			'email' => (string) ( $data['email'] ?? '' ),
		)
	);
}

/** Amazon: name / email / user_id. */
function upfw_sso_map_amazon( array $data, string $token ): array {
	return array_merge(
		upfw_sso_split_name( (string) ( $data['name'] ?? '' ) ),
		array(
			'id'    => (string) ( $data['user_id'] ?? '' ),
			'email' => (string) ( $data['email'] ?? '' ),
		)
	);
}

/**
 * X (Twitter) no devuelve el correo por más scope que se le pida.
 *
 * Se deja vacío a propósito: el flujo de arriba sabe qué hacer con eso —
 * vincular a una cuenta que ya está adentro sí se puede, crear una cuenta
 * nueva no, porque el correo es la identidad del sitio.
 */
function upfw_sso_map_twitter( array $data, string $token ): array {
	$user = (array) ( $data['data'] ?? array() );

	return array_merge(
		upfw_sso_split_name( (string) ( $user['name'] ?? '' ) ),
		array(
			'id'    => (string) ( $user['id'] ?? '' ),
			'email' => '',
		)
	);
}

/**
 * Parte un nombre completo en nombre y apellido.
 *
 * @return array{name: string, last_name: string}
 */
function upfw_sso_split_name( string $full ): array {
	$parts = preg_split( '/\s+/u', trim( $full ) ) ?: array();

	return array(
		'name'      => (string) ( $parts[0] ?? '' ),
		'last_name' => trim( implode( ' ', array_slice( $parts, 1 ) ) ),
	);
}

/* ── El ida y vuelta ───────────────────────────────────────────────── */

/**
 * Un GET autenticado que devuelve JSON.
 *
 * @return array<string, mixed>
 */
function upfw_sso_get( string $url, string $token ): array {
	$response = wp_remote_get(
		$url,
		array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
				// GitHub rechaza los pedidos sin user agent.
				'User-Agent'    => 'users-plus-for-wordpress',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return array();
	}

	return (array) json_decode( (string) wp_remote_retrieve_body( $response ), true );
}

/** Manda a la pantalla del proveedor. */
function upfw_sso_authorize( string $id, array $provider, bool $test = false ): void {
	$credentials = upfw_sso_credentials( $id );
	$state       = wp_generate_password( 24, false, false );

	$guardado = array(
		'provider' => $id,
		'test'     => $test ? 1 : 0,
	);

	// PKCE: en vez del secreto, se manda el hash de un valor al azar y en el
	// canje se manda el valor. Así el código robado en el camino de vuelta no
	// le sirve a nadie más. X lo exige; al resto no le molesta.
	if ( ! empty( $provider['pkce'] ) ) {
		$verifier             = wp_generate_password( 64, false, false );
		$guardado['verifier'] = $verifier;
	}

	// El `state` es lo que impide que alguien fabrique un retorno: se guarda
	// del lado del servidor y tiene que volver igual.
	set_transient( 'upfw_sso_' . $state, $guardado, 10 * MINUTE_IN_SECONDS );

	$args = array_merge(
		array(
			'client_id'     => rawurlencode( $credentials['id'] ),
			'redirect_uri'  => rawurlencode( upfw_sso_redirect_uri( $id ) ),
			'response_type' => 'code',
			'scope'         => rawurlencode( $provider['scope'] ),
			'state'         => $state,
		),
		$provider['extra']
	);

	if ( ! empty( $provider['pkce'] ) ) {
		$args['code_challenge']        = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
		$args['code_challenge_method'] = 'S256';
	}

	wp_redirect( add_query_arg( $args, $provider['authorize'] ) );
	exit;
}

/** Cambia el código por un token de acceso. */
function upfw_sso_token( string $id, array $provider, string $code, string $verifier = '' ): string {
	$credentials = upfw_sso_credentials( $id );

	$body = array(
		'client_id'     => $credentials['id'],
		'client_secret' => $credentials['secret'],
		'code'          => $code,
		'redirect_uri'  => upfw_sso_redirect_uri( $id ),
		'grant_type'    => 'authorization_code',
	);

	if ( '' !== $verifier ) {
		$body['code_verifier'] = $verifier;
	}

	$headers = array( 'Accept' => 'application/json' );

	// X pide el secreto por Basic auth y no en el cuerpo.
	if ( 'twitter' === $id ) {
		$headers['Authorization'] = 'Basic ' . base64_encode( $credentials['id'] . ':' . $credentials['secret'] );
		unset( $body['client_secret'] );
	}

	$response = wp_remote_post(
		$provider['token'],
		array(
			'timeout' => 15,
			'headers' => $headers,
			'body'    => $body,
		)
	);

	if ( is_wp_error( $response ) ) {
		return '';
	}

	$parsed = (array) json_decode( (string) wp_remote_retrieve_body( $response ), true );

	return (string) ( $parsed['access_token'] ?? '' );
}

/**
 * Encuentra o crea la cuenta de una identidad social y la vincula.
 *
 * La vinculación es por correo, igual que el ajuste "Link accounts by email"
 * de Nextend: si ya hay una cuenta con ese correo, es de la misma persona.
 * Vale porque el correo lo verificó el proveedor, no nosotros.
 */
function upfw_sso_user( string $id, array $identity ): int {
	$meta = 'upfw_sso_' . $id;

	$existing = get_users(
		array(
			'meta_key' => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'   => $identity['id'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		'number'       => 1,
		'fields'       => 'ID',
		)
	);

	if ( $existing ) {
		return upfw_sso_role_blocked( (int) $existing[0] ) ? 0 : (int) $existing[0];
	}

	if ( '' === $identity['email'] || ! is_email( $identity['email'] ) ) {
		return 0;
	}

	$known = get_user_by( 'email', $identity['email'] );

	// La cuenta ya existe con ese correo pero sin esta red vinculada: es la
	// misma persona, y se le suma la red. Es lo que hace que entrar con Google
	// hoy y con GitHub mañana sea la misma cuenta y no dos.
	if ( $known ) {
		if ( ! upfw_option( 'upfw_sso_link_by_email' ) ) {
			return 0;
		}

		$user_id = (int) $known->ID;

		// Igual que con el enlace por correo: en una red hay que sumarlo a
		// este sitio, o entra y no puede hacer nada.
		upfw_join_site( $user_id );
	} else {
		if ( ! upfw_option( 'upfw_sso_register' ) ) {
			return 0;
		}

		$user_id = upfw_user_for( $identity['email'] );
	}

	if ( $user_id <= 0 || upfw_sso_role_blocked( $user_id ) ) {
		return 0;
	}

	update_user_meta( $user_id, $meta, $identity['id'] );

	// El nombre sólo se completa si estaba vacío: lo que la persona escribió en
	// el sitio manda sobre lo que diga la red social.
	foreach ( array(
		'first_name' => 'name',
		'last_name'  => 'last_name',
	) as $field => $from ) {
		if ( '' !== $identity[ $from ] && '' === (string) get_user_meta( $user_id, $field, true ) ) {
			update_user_meta( $user_id, $field, $identity[ $from ] );
		}
	}

	return $user_id;
}

/**
 * ¿Este rol tiene prohibido entrar con una red social?
 *
 * La cuenta con más poder es la que más conviene proteger, y una cuenta de
 * administración que entra por Google depende de que esa cuenta de Google no
 * se pierda. Con el acceso por enlace de correo siempre disponible, cerrarle
 * la puerta social a los roles elegidos no deja a nadie afuera.
 *
 * @param int $user_id Usuario a revisar.
 */
function upfw_sso_role_blocked( int $user_id ): bool {
	$blocked = (array) upfw_option( 'upfw_sso_blocked_roles' );

	if ( array() === $blocked ) {
		return false;
	}

	$user = get_userdata( $user_id );

	return $user instanceof WP_User && array() !== array_intersect( $blocked, (array) $user->roles );
}

/**
 * El pedido tal como llegó.
 *
 * WordPress borra `$_GET['error']` mientras resuelve la URL —lo usa para
 * anotar su propio 404— y un proveedor OAuth contesta justamente con `error`
 * cuando alguien cancela la autorización. Así que la copia se saca temprano,
 * en `init`, que corre antes de que eso pase.
 *
 * @return array<string, mixed>
 */
function upfw_sso_query(): array {
	static $query = null;

	if ( null === $query ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- es la copia, no el uso.
		$query = wp_unslash( $_GET );
	}

	return $query;
}
add_action( 'init', 'upfw_sso_query', 0 );

/** Un parámetro del pedido, ya limpio de barras. */
function upfw_sso_param( string $key ): string {
	$query = upfw_sso_query();

	return isset( $query[ $key ] ) && is_scalar( $query[ $key ] ) ? (string) $query[ $key ] : '';
}

/** ¿Vino este parámetro, aunque sea vacío? */
function upfw_sso_has( string $key ): bool {
	return array_key_exists( $key, upfw_sso_query() );
}

/**
 * El único punto de entrada: dispara la ida y atiende la vuelta.
 */
function upfw_sso_handle( $wp = null ): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- el `state` cumple ese papel.
	// La red puede venir de la ruta —/sso/google/— o del parámetro, que es lo
	// que quedó registrado en las consolas de antes y en los sitios con
	// enlaces permanentes simples.
	$ruta = $wp instanceof WP && isset( $wp->query_vars['upfw_sso'] );
	$id   = $ruta
		? sanitize_key( (string) $wp->query_vars['upfw_sso'] )
		: sanitize_key( upfw_sso_param( 'upfw_sso' ) );

	if ( '' === $id ) {
		return;
	}

	$providers = upfw_sso_providers();

	// Alcanza con que tenga credenciales cargadas. Exigir acá que además esté
	// prendido dejaba la prueba en vivo sin salida: no se puede prender una red
	// sin probarla, y la prueba no arrancaba porque la red no estaba prendida.
	// Quién puede disparar cada cosa se decide más abajo.
	if ( ! isset( $providers[ $id ] ) || ! upfw_sso_configured( $id ) ) {
		// Por la ruta sólo se llega a propósito: un enlace viejo a una red que
		// ya no está tiene que decirlo, no dibujar la portada.
		if ( $ruta ) {
			upfw_sso_fail();
		}

		return;
	}

	$provider = $providers[ $id ];

	// Modo prueba: sólo para quien administra y con su nonce. No inicia
	// sesión de nadie; hace el ida y vuelta y cuenta cómo salió.
	$test = upfw_sso_has( 'upfw_test' )
		&& current_user_can( 'manage_options' )
		&& wp_verify_nonce( sanitize_key( upfw_sso_param( 'upfw_nonce' ) ), 'upfw_sso_test_' . $id );

	if ( upfw_sso_has( 'upfw_go' ) ) {
		// Salir a autorizar es de una red prendida, o de la prueba de quien
		// administra. Una red a medio configurar no manda a nadie a ningún lado.
		if ( ! $test && ! upfw_sso_ready( $id ) ) {
			upfw_sso_fail();
		}

		upfw_sso_authorize( $id, $provider, $test );
	}

	// En una vuelta el proveedor puede contestar con un error en vez de un
	// código: mostrarlo es la mitad del valor de la prueba.
	if ( upfw_sso_has( 'error' ) ) {
		$detalle = sanitize_text_field( '' !== upfw_sso_param( 'error_description' ) ? upfw_sso_param( 'error_description' ) : upfw_sso_param( 'error' ) );
		$estado  = get_transient( 'upfw_sso_' . sanitize_text_field( upfw_sso_param( 'state' ) ) );

		if ( is_array( $estado ) && ! empty( $estado['test'] ) ) {
			upfw_sso_test_result( $provider, false, $detalle );
		}

		upfw_sso_fail();
	}

	$code  = sanitize_text_field( upfw_sso_param( 'code' ) );
	$state = sanitize_text_field( upfw_sso_param( 'state' ) );

	if ( '' === $code || '' === $state ) {
		return;
	}
	// phpcs:enable

	$stored = get_transient( 'upfw_sso_' . $state );
	delete_transient( 'upfw_sso_' . $state );

	if ( ! is_array( $stored ) || ( $stored['provider'] ?? '' ) !== $id ) {
		upfw_sso_fail();
	}

	$es_prueba = ! empty( $stored['test'] );

	// La vuelta de una red que se apagó entre la ida y la vuelta no entra a
	// nadie. La prueba sí sigue: es justamente el paso previo a prenderla.
	if ( ! $es_prueba && ! upfw_sso_ready( $id ) ) {
		upfw_sso_fail();
	}

	$token = upfw_sso_token( $id, $provider, $code, (string) ( $stored['verifier'] ?? '' ) );

	if ( '' === $token ) {
		if ( $es_prueba ) {
			upfw_sso_test_result( $provider, false, __( 'The provider did not hand over an access token. Check the client ID and the secret.', 'users-plus-for-wordpress' ) );
		}

		upfw_sso_fail();
	}

	$identity = call_user_func( $provider['map'], upfw_sso_get( $provider['profile'], $token ), $token );

	// La prueba termina acá: no entra nadie, sólo se anota que funciona.
	if ( $es_prueba ) {
		if ( '' === $identity['id'] ) {
			upfw_sso_test_result( $provider, false, __( 'The token worked but the profile came back empty. The app is probably missing the permissions this provider needs.', 'users-plus-for-wordpress' ) );
		}

		upfw_sso_set_tested( $id, true );
		upfw_sso_test_result( $provider, true, $identity['email'] );
	}

	// Si ya está adentro, esto es una vinculación desde el perfil, no un login.
	if ( is_user_logged_in() ) {
		if ( '' !== $identity['id'] ) {
			update_user_meta( get_current_user_id(), 'upfw_sso_' . $id, $identity['id'] );

			upfw_notify_security(
				get_current_user_id(),
				sprintf(
					/* translators: %s: nombre de la red social */
					__( 'The %s account was linked, and it now gets into this account.', 'users-plus-for-wordpress' ),
					$provider['name']
				)
			);
		}

		$back = (string) get_transient( 'upfw_sso_back_' . get_current_user_id() );
		wp_safe_redirect( add_query_arg( 'upfw', 'linked', $back ? $back : home_url( '/' ) ) );
		exit;
	}

	$user_id = upfw_sso_user( $id, $identity );

	if ( $user_id <= 0 ) {
		upfw_sso_fail();
	}

	/** Ver el filtro homónimo en includes/login.php. */
	$redirect = (string) apply_filters( 'upfw_login_redirect', home_url( '/' ), $user_id );

	// Igual que el enlace por correo: la sesión la abre el camino común, que
	// es también el que sabe si falta un segundo factor.
	upfw_complete_login( $user_id, 'sso', true, $redirect );
}
/*
Va en `parse_request` y no en `init` porque es ahí donde WordPress ya
	resolvió la ruta: antes de eso /sso/google/ todavía no es nada. */
add_action( 'parse_request', 'upfw_sso_handle' );

/** Vuelve a la pantalla de acceso con el aviso de que no se pudo. */
function upfw_sso_fail(): void {
	wp_safe_redirect( add_query_arg( 'upfw', 'social', upfw_login_url() ) );
	exit;
}

/** Desvincula una red del usuario actual. */
function upfw_sso_unlink(): void {
	check_admin_referer( 'upfw_sso_unlink' );

	$id = sanitize_key( wp_unslash( $_POST['upfw_provider'] ?? '' ) );

	if ( '' !== $id && is_user_logged_in() ) {
		delete_user_meta( get_current_user_id(), 'upfw_sso_' . $id );

		upfw_notify_security(
			get_current_user_id(),
			sprintf(
				/* translators: %s: nombre de la red social */
				__( 'The %s account was unlinked.', 'users-plus-for-wordpress' ),
				upfw_sso_providers()[ $id ]['name'] ?? $id
			)
		);
	}

	$back = wp_get_referer();
	wp_safe_redirect( $back ? $back : home_url( '/' ) );
	exit;
}
add_action( 'admin_post_upfw_sso_unlink', 'upfw_sso_unlink' );

/** ¿Qué redes tiene vinculadas esta persona? */
function upfw_sso_linked( int $user_id ): array {
	$linked = array();

	foreach ( array_keys( upfw_sso_providers() ) as $id ) {
		if ( '' !== (string) get_user_meta( $user_id, 'upfw_sso_' . $id, true ) ) {
			$linked[] = $id;
		}
	}

	return $linked;
}

/**
 * El resultado de la prueba, en la ventana que la abrió.
 *
 * Es una página suelta y no una redirección porque la prueba corre en una
 * ventana aparte: lo que hay que hacer es contar qué pasó y cerrarla.
 *
 * @param array<string, mixed> $provider
 * @param bool                 $ok
 * @param string               $detail Correo recibido, o el error.
 */
function upfw_sso_test_result( array $provider, bool $ok, string $detail = '' ): void {
	$titulo = $ok
		? __( 'It works', 'users-plus-for-wordpress' )
		: __( 'It did not work', 'users-plus-for-wordpress' );

	$mensaje = $ok
		? sprintf(
			/* translators: %s: nombre del proveedor */
			__( 'The round trip with %s finished correctly. You can enable the button now.', 'users-plus-for-wordpress' ),
			$provider['name']
		)
		: sprintf(
			/* translators: %s: nombre del proveedor */
			__( 'The round trip with %s failed.', 'users-plus-for-wordpress' ),
			$provider['name']
		);

	if ( $ok && '' !== $detail ) {
		/* translators: %s: dirección de correo */
		$detail = sprintf( __( 'The provider handed over this email: %s', 'users-plus-for-wordpress' ), $detail );
	}

	nocache_headers();

	?><!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<title><?php echo esc_html( $titulo ); ?></title>
		<style>
			body { margin: 0; padding: 40px 32px; font: 15px/1.6 -apple-system, system-ui, sans-serif; color: #1d2327; background: #f0f0f1; }
			.caja { max-width: 34rem; margin: 0 auto; background: #fff; border: 1px solid #dcdcde; border-radius: 4px; padding: 28px 30px; }
			.caja h1 { margin: 0 0 10px; font-size: 21px; }
			.ok h1 { color: #0a5c3e; }
			.mal h1 { color: #b32d2e; }
			.detalle { margin: 14px 0 0; padding: 12px 14px; background: #f6f7f7; border-radius: 3px; word-break: break-word; }
			button { margin-top: 22px; padding: 8px 18px; border: 0; border-radius: 3px; background: #2271b1; color: #fff; font: inherit; cursor: pointer; }
		</style>
	</head>
	<body>
		<div class="caja <?php echo $ok ? 'ok' : 'mal'; ?>">
			<h1><?php echo esc_html( $titulo ); ?></h1>
			<p><?php echo esc_html( $mensaje ); ?></p>
			<?php if ( '' !== $detail ) : ?>
				<p class="detalle"><?php echo esc_html( $detail ); ?></p>
			<?php endif; ?>
			<button type="button" onclick="if (window.opener) { window.opener.location.reload(); } window.close();">
				<?php esc_html_e( 'Close', 'users-plus-for-wordpress' ); ?>
			</button>
		</div>
	</body>
	</html>
	<?php
	exit;
}
