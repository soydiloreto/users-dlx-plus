<?php
/**
 * Passkeys (WebAuthn).
 *
 * Una passkey es una clave privada que vive en el dispositivo o en el llavero
 * de la persona y que nunca sale de ahí. El sitio guarda sólo la pública. No
 * hay nada que robar de la base, nada que reusar en otro sitio, y no se puede
 * phishear: el navegador se niega a firmar para un dominio que no es el que
 * registró la clave.
 *
 * Lo que se implementa acá es la verificación, que es la parte que importa:
 *
 *   - El desafío lo emite el servidor, dura poco y se usa una sola vez.
 *   - Se comprueba el tipo de operación, el origen y el hash del dominio.
 *   - Se exige la marca de «presencia de usuario», y la de «verificación» si
 *     el sitio la pide.
 *   - La firma se verifica con la clave pública guardada, sobre exactamente
 *     los bytes que manda el estándar.
 *
 * Del alta se aprovecha `getPublicKey()`, que los navegadores modernos ya
 * devuelven en formato DER: así no hace falta un intérprete de CBOR para leer
 * el objeto de atestación, que es la parte más frágil de cualquier
 * implementación de WebAuthn.
 *
 * Deliberadamente NO se verifica la atestación del fabricante: sirve para
 * exigir marcas de llave concretas en entornos corporativos, y en un sitio
 * abierto sólo agrega superficie de error.
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;

/** Cuánto vive un desafío. Corto: es un ida y vuelta de segundos. */
const USERS_DLX_PLUS_PASSKEY_TTL = 5 * MINUTE_IN_SECONDS;

/* ── Base64url, que es como viaja todo esto ────────────────────────── */

/** Codifica en base64url, que es como WebAuthn manda y espera todo. */
function users_dlx_plus_b64url_encode( string $bytes ): string {
	return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
}

/** B64url decode. */
function users_dlx_plus_b64url_decode( string $text ): string {
	$text = strtr( $text, '-_', '+/' );

	return (string) base64_decode( str_pad( $text, strlen( $text ) % 4 ? strlen( $text ) + 4 - strlen( $text ) % 4 : 0, '=' ), true );
}

/* ── Quiénes somos para el navegador ───────────────────────────────── */

/**
 * El dominio con el que se registra la passkey.
 *
 * Una passkey queda atada a este valor: si cambia, las que había dejan de
 * servir. Por eso sale del host y no de una opción que alguien pueda tocar
 * sin saber lo que hace.
 */
function users_dlx_plus_passkey_rp_id(): string {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );

	/**
	 * Filtra el dominio de las passkeys.
	 *
	 * Sólo tiene sentido tocarlo para subir un nivel —de `cuenta.sitio.com` a
	 * `sitio.com`— y compartirlas entre subdominios.
	 *
	 * @param string $rp_id
	 */
	return (string) apply_filters( 'users_dlx_plus_passkey_rp_id', is_string( $host ) ? $host : '' );
}

/** El origen exacto que tiene que declarar el navegador. */
function users_dlx_plus_passkey_origin(): string {
	$parts = wp_parse_url( home_url() );

	return sprintf( '%s://%s%s', $parts['scheme'] ?? 'https', $parts['host'] ?? '', isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
}

/* ── Las llaves de cada persona ────────────────────────────────────── */

/**
 * Las passkeys de alguien.
 *
 * @return array<int, array<string, mixed>>
 */
function users_dlx_plus_passkeys( int $user_id ): array {
	$keys = get_user_meta( $user_id, 'users_dlx_plus_passkeys', true );

	return is_array( $keys ) ? array_values( $keys ) : array();
}

/** ¿Tiene al menos una? */
function users_dlx_plus_passkeys_ready( int $user_id ): bool {
	return array() !== users_dlx_plus_passkeys( $user_id );
}

/**
 * Guarda la lista de passkeys de una persona.
 *
 * @param array<int, array<string, mixed>> $keys
 */
function users_dlx_plus_passkeys_save( int $user_id, array $keys ): void {
	update_user_meta( $user_id, 'users_dlx_plus_passkeys', array_values( $keys ) );
}

/** Saca una por su identificador. */
function users_dlx_plus_passkey_forget( int $user_id, string $id ): void {
	users_dlx_plus_passkeys_save(
		$user_id,
		array_filter( users_dlx_plus_passkeys( $user_id ), static fn( array $k ): bool => $k['id'] !== $id )
	);
}

/**
 * A quién pertenece una passkey.
 *
 * Se busca por meta porque en el ingreso todavía no hay sesión: la passkey
 * dice quién es antes de que nadie diga su correo.
 */
function users_dlx_plus_passkey_owner( string $id ): int {
	$users = get_users(
		array(
			'meta_key' => 'users_dlx_plus_passkeys', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'fields'       => 'ID',
		'number'       => 500,
		)
	);

	foreach ( $users as $user_id ) {
		foreach ( users_dlx_plus_passkeys( (int) $user_id ) as $key ) {
			if ( hash_equals( (string) $key['id'], $id ) ) {
				return (int) $user_id;
			}
		}
	}

	return 0;
}

/* ── Desafíos ──────────────────────────────────────────────────────── */

/** Emite un desafío y lo guarda para poder compararlo después. */
function users_dlx_plus_passkey_challenge_new( string $scope ): string {
	$challenge = users_dlx_plus_b64url_encode( random_bytes( 32 ) );

	set_transient( 'users_dlx_plus_pk_' . $scope . '_' . md5( $challenge ), 1, USERS_DLX_PLUS_PASSKEY_TTL );

	return $challenge;
}

/** Lo consume: si existía lo borra y devuelve true. De un solo uso. */
function users_dlx_plus_passkey_challenge_use( string $scope, string $challenge ): bool {
	$key = 'users_dlx_plus_pk_' . $scope . '_' . md5( $challenge );

	if ( ! get_transient( $key ) ) {
		return false;
	}

	delete_transient( $key );

	return true;
}

/* ── Verificación ──────────────────────────────────────────────────── */

/**
 * Revisa el `clientDataJSON` que devuelve el navegador.
 *
 * @return array<string, mixed>|null
 */
function users_dlx_plus_passkey_client_data( string $json, string $type, string $scope ): ?array {
	$data = json_decode( $json, true );

	if ( ! is_array( $data ) ) {
		return null;
	}

	if ( ( $data['type'] ?? '' ) !== $type ) {
		return null;
	}

	// El origen tiene que ser exactamente el nuestro: es lo que hace que una
	// passkey no se pueda usar desde un sitio clonado.
	if ( ( $data['origin'] ?? '' ) !== users_dlx_plus_passkey_origin() ) {
		return null;
	}

	if ( ! users_dlx_plus_passkey_challenge_use( $scope, (string) ( $data['challenge'] ?? '' ) ) ) {
		return null;
	}

	return $data;
}

/**
 * Revisa el `authenticatorData`.
 *
 * Son 37 bytes fijos y después lo opcional: el hash del dominio, un byte de
 * banderas y un contador.
 *
 * @return array{flags: int, counter: int}|null
 */
function users_dlx_plus_passkey_auth_data( string $bytes ): ?array {
	if ( strlen( $bytes ) < 37 ) {
		return null;
	}

	if ( ! hash_equals( substr( $bytes, 0, 32 ), hash( 'sha256', users_dlx_plus_passkey_rp_id(), true ) ) ) {
		return null;
	}

	$flags = ord( $bytes[32] );

	// Bit 0: alguien estuvo presente. Sin eso, cualquier proceso podría firmar.
	if ( 0 === ( $flags & 0x01 ) ) {
		return null;
	}

	// Bit 2: además se verificó quién es (huella, cara, PIN).
	if ( users_dlx_plus_option( 'users_dlx_plus_passkey_verify' ) && 0 === ( $flags & 0x04 ) ) {
		return null;
	}

	return array(
		'flags'   => $flags,
		'counter' => (int) ( ( (array) unpack( 'N', substr( $bytes, 33, 4 ) ) )[1] ?? 0 ),
	);
}

/** Arma una clave pública utilizable a partir del DER que mandó el navegador. */
function users_dlx_plus_passkey_pem( string $der ): string {
	return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
}

/**
 * ¿La firma es de esa clave y sobre esos datos?
 *
 * Lo que se firma es la concatenación de `authenticatorData` con el SHA-256 de
 * `clientDataJSON`. No es una elección: está en el estándar y cualquier otra
 * cosa no valida.
 */
function users_dlx_plus_passkey_signature_ok( string $der, int $alg, string $auth_data, string $client_json, string $signature ): bool {
	$key = openssl_pkey_get_public( users_dlx_plus_passkey_pem( $der ) );

	if ( false === $key ) {
		return false;
	}

	// -7 es ECDSA con P-256 y SHA-256; -257 es RSA con SHA-256. Son los dos
	// que usan las passkeys reales.
	$digest = -257 === $alg ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA256;

	if ( ! in_array( $alg, array( -7, -257 ), true ) ) {
		return false;
	}

	return 1 === openssl_verify( $auth_data . hash( 'sha256', $client_json, true ), $signature, $key, $digest );
}

/* ── El ida y vuelta con el navegador ──────────────────────────────── */

/** ¿El sitio ofrece passkeys? */
function users_dlx_plus_passkeys_enabled(): bool {
	return (bool) users_dlx_plus_option( 'users_dlx_plus_passkey_enabled' );
}

/** Los datos para empezar un alta. */
/**
 * @return array<string, mixed>
 */
function users_dlx_plus_passkeys_register_options(): array {
	$user = wp_get_current_user();

	return array(
		'challenge'               => users_dlx_plus_passkey_challenge_new( 'reg' ),
		'rp'                      => array(
			'id'   => users_dlx_plus_passkey_rp_id(),
			'name' => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
		),
		'user'                    => array(
			// El id del usuario va como bytes opacos: no se le manda al
			// autenticador nada que identifique a la persona fuera del sitio.
			// La semilla dice 'upfw' y se queda así: es lo que el autenticador
			// guardó junto a cada passkey. Cambiarla le cambia la identidad a
			// quien ya tiene una, y su llave deja de reconocerse.
			'id'          => users_dlx_plus_b64url_encode( hash( 'sha256', 'upfw|' . $user->ID . '|' . wp_salt(), true ) ),
			'name'        => $user->user_email,
			'displayName' => users_dlx_plus_display_name( $user ),
		),
		'excludeCredentials'      => array_map(
			static fn( array $k ): array => array(
				'id'   => $k['id'],
				'type' => 'public-key',
			),
			users_dlx_plus_passkeys( $user->ID )
		),
		'authenticatorAttachment' => 'device' === (string) users_dlx_plus_option( 'users_dlx_plus_passkey_where' ) ? 'platform' : null,
		'userVerification'        => users_dlx_plus_option( 'users_dlx_plus_passkey_verify' ) ? 'required' : 'preferred',
		'residentKey'             => 'preferred',
	);
}

/** Los datos para empezar un ingreso. */
/**
 * @return array<string, mixed>
 */
function users_dlx_plus_passkeys_login_options(): array {
	return array(
		'challenge'        => users_dlx_plus_passkey_challenge_new( 'log' ),
		'rpId'             => users_dlx_plus_passkey_rp_id(),
		'userVerification' => users_dlx_plus_option( 'users_dlx_plus_passkey_verify' ) ? 'required' : 'preferred',
	);
}

/** Todo el diálogo con el navegador pasa por acá. */
function users_dlx_plus_passkeys_ajax(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- el nonce se verifica según el paso.
	$step = sanitize_key( wp_unslash( $_POST['step'] ?? '' ) );

	if ( ! users_dlx_plus_passkeys_enabled() ) {
		wp_send_json_error( array( 'message' => __( 'This site does not use passkeys.', 'users-dlx-plus' ) ), 400 );
	}

	// Las dos operaciones de alta exigen sesión y nonce; las de ingreso no
	// pueden exigir sesión, porque justamente sirven para abrirla.
	if ( in_array( $step, array( 'register-options', 'register' ), true ) ) {
		if ( ! is_user_logged_in() || ! check_ajax_referer( 'users_dlx_plus_passkeys', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Session expired. Reload the page.', 'users-dlx-plus' ) ), 403 );
		}
	}

	switch ( $step ) {
		case 'register-options':
			wp_send_json_success( users_dlx_plus_passkeys_register_options() );
			// wp_send_json_* contesta y corta: no hay caída al siguiente caso.

		case 'register':
			wp_send_json( users_dlx_plus_passkeys_register( wp_unslash( $_POST ) ) );
			// wp_send_json_* contesta y corta: no hay caída al siguiente caso.

		case 'login-options':
			wp_send_json_success( users_dlx_plus_passkeys_login_options() );
			// wp_send_json_* contesta y corta: no hay caída al siguiente caso.

		case 'login':
			wp_send_json( users_dlx_plus_passkeys_login( wp_unslash( $_POST ) ) );
	}
	// phpcs:enable

	wp_send_json_error( array( 'message' => __( 'Unknown step.', 'users-dlx-plus' ) ), 400 );
}
add_action( 'wp_ajax_users_dlx_plus_passkeys', 'users_dlx_plus_passkeys_ajax' );
add_action( 'wp_ajax_nopriv_users_dlx_plus_passkeys', 'users_dlx_plus_passkeys_ajax' );

/** Da de alta una passkey nueva. */
/**
 * @return array<string, mixed>
 */
/**
 * @param array<string, mixed> $post
 * @return array<string, mixed>
 */
function users_dlx_plus_passkeys_register( array $post ): array {
	$user_id = get_current_user_id();
	$id      = sanitize_text_field( (string) ( $post['id'] ?? '' ) );
	$der     = users_dlx_plus_b64url_decode( (string) ( $post['publicKey'] ?? '' ) );
	$alg     = (int) ( $post['algorithm'] ?? 0 );
	$json    = (string) ( $post['clientDataJSON'] ?? '' );

	if ( '' === $id || '' === $der || null === users_dlx_plus_passkey_client_data( $json, 'webauthn.create', 'reg' ) ) {
		return array(
			'success' => false,
			'data'    => array( 'message' => __( 'That did not check out. Try again.', 'users-dlx-plus' ) ),
		);
	}

	if ( ! in_array( $alg, array( -7, -257 ), true ) || false === openssl_pkey_get_public( users_dlx_plus_passkey_pem( $der ) ) ) {
		return array(
			'success' => false,
			'data'    => array( 'message' => __( 'That key is of a kind this site cannot verify.', 'users-dlx-plus' ) ),
		);
	}

	$keys = users_dlx_plus_passkeys( $user_id );

	foreach ( $keys as $key ) {
		if ( hash_equals( (string) $key['id'], $id ) ) {
			return array(
				'success' => true,
				'data'    => array( 'message' => __( 'That one was already here.', 'users-dlx-plus' ) ),
			);
		}
	}

	$keys[] = array(
		'id'      => $id,
		'key'     => base64_encode( $der ),
		'alg'     => $alg,
		'label'   => users_dlx_plus_passkey_clean_label( (string) ( $post['label'] ?? '' ) ),
		'created' => time(),
		'used'    => 0,
		'counter' => 0,
	);

	users_dlx_plus_passkeys_save( $user_id, $keys );

	users_dlx_plus_notify_security(
		$user_id,
		sprintf(
			/* translators: %s: el nombre que se le puso a la passkey */
			__( 'A passkey was added: %s.', 'users-dlx-plus' ),
			end( $keys )['label']
		)
	);

	return array(
		'success' => true,
		'data'    => array( 'message' => __( 'Passkey saved.', 'users-dlx-plus' ) ),
	);
}

/**
 * El nombre que se guarda para una llave.
 *
 * Lo elige la persona: son suyas y va a tener varias —el teléfono, la
 * notebook, la llave física— y «Passkey, Passkey, Passkey» no le dice a nadie
 * cuál sacar cuando pierde una. Si no escribe nada, se propone el dispositivo.
 */
function users_dlx_plus_passkey_clean_label( string $label ): string {
	$label = trim( sanitize_text_field( $label ) );

	if ( '' === $label ) {
		return users_dlx_plus_passkey_label();
	}

	return mb_substr( $label, 0, 60 );
}

/** Le cambia el nombre a una llave. */
function users_dlx_plus_passkey_rename( int $user_id, string $id, string $label ): void {
	$keys = users_dlx_plus_passkeys( $user_id );

	foreach ( $keys as $i => $key ) {
		if ( hash_equals( (string) $key['id'], $id ) ) {
			$keys[ $i ]['label'] = users_dlx_plus_passkey_clean_label( $label );
			users_dlx_plus_passkeys_save( $user_id, $keys );

			return;
		}
	}
}

/** Un nombre razonable para la llave, sacado del navegador. */
function users_dlx_plus_passkey_label(): string {
	$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

	foreach ( array(
		'iPhone'    => 'iPhone',
		'iPad'      => 'iPad',
		'Android'   => 'Android',
		'Macintosh' => 'Mac',
		'Windows'   => 'Windows',
		'Linux'     => 'Linux',
	) as $aguja => $nombre ) {
		if ( false !== stripos( $agent, $aguja ) ) {
			return $nombre;
		}
	}

	return __( 'Passkey', 'users-dlx-plus' );
}

/** Entra con una passkey. */
/**
 * @return array<string, mixed>
 */
/**
 * @param array<string, mixed> $post
 * @return array<string, mixed>
 */
function users_dlx_plus_passkeys_login( array $post ): array {
	$id        = sanitize_text_field( (string) ( $post['id'] ?? '' ) );
	$json      = (string) ( $post['clientDataJSON'] ?? '' );
	$auth_data = users_dlx_plus_b64url_decode( (string) ( $post['authenticatorData'] ?? '' ) );
	$signature = users_dlx_plus_b64url_decode( (string) ( $post['signature'] ?? '' ) );

	$fallo = array(
		'success' => false,
		'data'    => array( 'message' => __( 'That passkey did not check out.', 'users-dlx-plus' ) ),
	);

	if ( '' === $id || null === users_dlx_plus_passkey_client_data( $json, 'webauthn.get', 'log' ) ) {
		return $fallo;
	}

	$auth = users_dlx_plus_passkey_auth_data( $auth_data );

	if ( null === $auth ) {
		return $fallo;
	}

	$user_id = users_dlx_plus_passkey_owner( $id );

	if ( $user_id <= 0 ) {
		return $fallo;
	}

	$keys  = users_dlx_plus_passkeys( $user_id );
	$found = null;

	foreach ( $keys as $i => $key ) {
		if ( hash_equals( (string) $key['id'], $id ) ) {
			$found = $i;
			break;
		}
	}

	if ( null === $found ) {
		return $fallo;
	}

	$der = (string) base64_decode( (string) $keys[ $found ]['key'], true );

	if ( ! users_dlx_plus_passkey_signature_ok( $der, (int) $keys[ $found ]['alg'], $auth_data, $json, $signature ) ) {
		return $fallo;
	}

	// El contador sólo puede subir. Si baja, la llave se clonó; se avisa y se
	// sigue, porque muchas passkeys sincronizadas devuelven siempre cero y
	// rechazar ahí dejaría afuera a media internet.
	if ( $auth['counter'] > 0 && $auth['counter'] <= (int) $keys[ $found ]['counter'] ) {
		do_action( 'users_dlx_plus_passkey_counter_warning', $user_id, $id );
	}

	$keys[ $found ]['counter'] = $auth['counter'];
	$keys[ $found ]['used']    = time();

	users_dlx_plus_passkeys_save( $user_id, $keys );

	// Una passkey ya es dos factores en un paso: algo que tenés más algo que
	// sos o sabés. Pedirle además un código sería pedir tres.
	$redirect = (string) apply_filters( 'users_dlx_plus_login_redirect', home_url( '/' ), $user_id );

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true );

	do_action( 'users_dlx_plus_logged_in', $user_id, 'passkey' );

	return array(
		'success' => true,
		'data'    => array( 'redirect' => $redirect ),
	);
}

/* ── Lo que ve la gente ────────────────────────────────────────────── */

/**
 * El script, sólo donde hace falta.
 *
 * Se encola desde el shortcode que lo necesita y no en todo el sitio: es la
 * misma regla que la hoja de estilos.
 */
function users_dlx_plus_passkeys_enqueue(): void {
	if ( ! users_dlx_plus_passkeys_enabled() || wp_script_is( 'users-dlx-plus-passkeys', 'enqueued' ) ) {
		return;
	}

	wp_enqueue_script( 'users-dlx-plus-passkeys', USERS_DLX_PLUS_URL . 'assets/users-dlx-plus-passkeys.js', array(), users_dlx_plus_asset_version( 'assets/users-dlx-plus-passkeys.js' ), true );

	wp_localize_script(
		'users-dlx-plus-passkeys',
		'usersDlxPlusPasskeys',
		array(
			'ajax'   => admin_url( 'admin-ajax.php' ),
			'nonce'  => wp_create_nonce( 'users_dlx_plus_passkeys' ),
			'textos' => array(
				'error' => __( 'That did not work. Try again.', 'users-dlx-plus' ),
				'viejo' => __( 'This browser is too old for passkeys.', 'users-dlx-plus' ),
			),
		)
	);
}

/**
 * Renombrar o sacar una passkey desde el perfil.
 *
 * Las dos cosas viven en el mismo formulario —el nombre y los botones están en
 * la misma fila— así que son la misma acción con dos botones.
 */
function users_dlx_plus_passkeys_manage(): void {
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( users_dlx_plus_login_url() );
		exit;
	}

	check_admin_referer( 'users_dlx_plus_passkey' );

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- verificado arriba.
	$user_id = get_current_user_id();
	$id      = sanitize_text_field( wp_unslash( $_POST['users_dlx_plus_passkey'] ?? '' ) );
	$hacer   = sanitize_key( wp_unslash( $_POST['users_dlx_plus_passkey_do'] ?? '' ) );

	if ( 'delete' === $hacer ) {
		users_dlx_plus_passkey_forget( $user_id, $id );
		users_dlx_plus_notify_security( $user_id, __( 'A passkey was removed.', 'users-dlx-plus' ) );
		$aviso = 'passkeyoff';
	} else {
		users_dlx_plus_passkey_rename( $user_id, $id, sanitize_text_field( wp_unslash( $_POST['users_dlx_plus_passkey_label'] ?? '' ) ) );
		$aviso = 'passkeyname';
	}
	// phpcs:enable

	wp_safe_redirect( add_query_arg( 'users-dlx-plus', $aviso, users_dlx_plus_account_url( 'security' ) ) );
	exit;
}
add_action( 'admin_post_users_dlx_plus_passkey', 'users_dlx_plus_passkeys_manage' );
