<?php
/**
 * El segundo factor de la aplicación autenticadora (TOTP).
 *
 * Es el RFC 6238 y son treinta líneas de matemática: un secreto compartido,
 * la hora dividida en ventanas de treinta segundos, un HMAC-SHA1 y seis
 * dígitos. Se implementa acá y no con una librería porque una dependencia
 * externa en un plugin de WordPress es un problema de mantenimiento mucho más
 * grande que este archivo, y porque lo que hay que hacer está escrito en un
 * documento público que no cambia desde 2011.
 *
 * Lo que NO se hace acá es inventar criptografía: el HMAC lo hace PHP.
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;

/** El alfabeto de base32, que es como se escriben estos secretos. */
const USERS_PLUS_BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

/** Cuántos segundos dura cada código. Es 30 en todas las aplicaciones. */
const USERS_PLUS_TOTP_STEP = 30;

/** Cuántos dígitos. También son 6 en todas. */
const USERS_PLUS_TOTP_DIGITS = 6;

/**
 * Cuántas ventanas para atrás y para adelante se aceptan.
 *
 * Una. Sin eso, un reloj dos segundos corrido rechaza códigos correctos; con
 * más, la ventana de un código robado se estira sin necesidad.
 */
const USERS_PLUS_TOTP_DRIFT = 1;

/** Un secreto nuevo, en base32 y del largo que recomienda el RFC. */
function users_plus_totp_secret_new( int $length = 32 ): string {
	$secret = '';
	$bytes  = random_bytes( max( 1, $length ) );

	for ( $i = 0; $i < $length; $i++ ) {
		$secret .= USERS_PLUS_BASE32[ ord( $bytes[ $i ] ) & 31 ];
	}

	return $secret;
}

/** De base32 a los bytes crudos que come el HMAC. */
function users_plus_base32_decode( string $secret ): string {
	$secret = strtoupper( preg_replace( '/[^A-Z2-7]/i', '', $secret ) ?? '' );

	if ( '' === $secret ) {
		return '';
	}

	$bits = '';

	foreach ( str_split( $secret ) as $char ) {
		$bits .= str_pad( decbin( (int) strpos( USERS_PLUS_BASE32, $char ) ), 5, '0', STR_PAD_LEFT );
	}

	$bytes = '';

	foreach ( str_split( $bits, 8 ) as $chunk ) {
		if ( 8 === strlen( $chunk ) ) {
			$bytes .= chr( (int) bindec( $chunk ) );
		}
	}

	return $bytes;
}

/**
 * El código que corresponde a un secreto en un momento dado.
 *
 * @param int $timestamp Momento; 0 = ahora.
 */
function users_plus_totp_code( string $secret, int $timestamp = 0 ): string {
	$key = users_plus_base32_decode( $secret );

	if ( '' === $key ) {
		return '';
	}

	$counter = intdiv( 0 === $timestamp ? time() : $timestamp, USERS_PLUS_TOTP_STEP );

	// El contador va como ocho bytes, big-endian. pack('J') existe desde PHP 5.6.
	$hash = hash_hmac( 'sha1', pack( 'J', $counter ), $key, true );

	// «Truncamiento dinámico»: el último nibble dice de dónde leer los cuatro
	// bytes que importan. Es literal del RFC 4226, §5.4.
	$offset = ord( $hash[ strlen( $hash ) - 1 ] ) & 0x0F;
	$number = ( ( ord( $hash[ $offset ] ) & 0x7F ) << 24 )
		| ( ( ord( $hash[ $offset + 1 ] ) & 0xFF ) << 16 )
		| ( ( ord( $hash[ $offset + 2 ] ) & 0xFF ) << 8 )
		| ( ord( $hash[ $offset + 3 ] ) & 0xFF );

	return str_pad( (string) ( $number % ( 10 ** USERS_PLUS_TOTP_DIGITS ) ), USERS_PLUS_TOTP_DIGITS, '0', STR_PAD_LEFT );
}

/** ¿Este código corresponde a este secreto, ahora o hace un ratito? */
function users_plus_totp_check( string $secret, string $code ): bool {
	$code = preg_replace( '/\D/', '', $code ) ?? '';

	if ( strlen( $code ) !== USERS_PLUS_TOTP_DIGITS ) {
		return false;
	}

	for ( $i = -USERS_PLUS_TOTP_DRIFT; $i <= USERS_PLUS_TOTP_DRIFT; $i++ ) {
		if ( hash_equals( users_plus_totp_code( $secret, time() + $i * USERS_PLUS_TOTP_STEP ), $code ) ) {
			return true;
		}
	}

	return false;
}

/* ── Lo que ve el plugin ───────────────────────────────────────────── */

/** El secreto ya confirmado de alguien. Vacío si todavía no lo activó. */
function users_plus_totp_secret( int $user_id ): string {
	return (string) get_user_meta( $user_id, 'users_plus_totp', true );
}

/** ¿Tiene la aplicación configurada y confirmada? */
function users_plus_totp_ready( int $user_id ): bool {
	return '' !== users_plus_totp_secret( $user_id );
}

/**
 * El secreto que está probando ahora, generándolo si hace falta.
 *
 * Se guarda aparte del definitivo: hasta que no escriba un código correcto no
 * se activa nada, así nadie se queda afuera por haber abierto la pantalla y
 * haberse ido.
 */
function users_plus_totp_pending( int $user_id ): string {
	$secret = (string) get_user_meta( $user_id, 'users_plus_totp_pending', true );

	if ( '' === $secret ) {
		$secret = users_plus_totp_secret_new();
		update_user_meta( $user_id, 'users_plus_totp_pending', $secret );
	}

	return $secret;
}

/** Verifica contra el secreto activo. Es el callback del registro. */
function users_plus_totp_verify( int $user_id, string $code ): bool {
	$secret = users_plus_totp_secret( $user_id );

	if ( '' === $secret ) {
		return false;
	}

	// Un código sólo se usa una vez: sin esto, quien lo vea de reojo tiene
	// treinta segundos para escribirlo también.
	$used = (string) get_user_meta( $user_id, 'users_plus_totp_used', true );
	$code = preg_replace( '/\D/', '', $code ) ?? '';

	if ( '' !== $used && hash_equals( $used, $code ) ) {
		return false;
	}

	if ( ! users_plus_totp_check( $secret, $code ) ) {
		return false;
	}

	update_user_meta( $user_id, 'users_plus_totp_used', $code );

	return true;
}

/**
 * La URI que entienden todas las aplicaciones autenticadoras.
 *
 * El emisor va dos veces —en la etiqueta y como parámetro— porque las
 * aplicaciones viejas leen una y las nuevas la otra, y así la entrada queda
 * bien nombrada en las dos.
 */
function users_plus_totp_uri( int $user_id, string $secret ): string {
	$user   = get_userdata( $user_id );
	$issuer = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	$label  = $issuer . ':' . ( $user instanceof WP_User ? $user->user_email : (string) $user_id );

	return 'otpauth://totp/' . rawurlencode( $label ) . '?' . http_build_query(
		array(
			'secret' => $secret,
			'issuer' => $issuer,
			'digits' => USERS_PLUS_TOTP_DIGITS,
			'period' => USERS_PLUS_TOTP_STEP,
		),
		'',
		'&',
		PHP_QUERY_RFC3986
	);
}

/** El secreto en grupos de cuatro, para poder tipearlo sin equivocarse. */
function users_plus_totp_readable( string $secret ): string {
	return trim( chunk_split( $secret, 4, ' ' ) );
}

/** Activa la aplicación si el código que escribieron es correcto. */
function users_plus_totp_activate( int $user_id, string $code ): bool {
	$secret = (string) get_user_meta( $user_id, 'users_plus_totp_pending', true );

	if ( '' === $secret || ! users_plus_totp_check( $secret, $code ) ) {
		return false;
	}

	update_user_meta( $user_id, 'users_plus_totp', $secret );
	delete_user_meta( $user_id, 'users_plus_totp_pending' );

	return true;
}

/** La saca. */
function users_plus_totp_forget( int $user_id ): void {
	delete_user_meta( $user_id, 'users_plus_totp' );
	delete_user_meta( $user_id, 'users_plus_totp_pending' );
	delete_user_meta( $user_id, 'users_plus_totp_used' );
}
