<?php
/**
 * El segundo factor por correo.
 *
 * Es el más débil de los tres —quien tenga el correo tiene el código— y a la
 * vez el único que no exige instalar nada, así que suele ser el que hace que
 * la gente prenda el segundo factor. Vale para eso: para que exista uno.
 *
 * El código se guarda hasheado y con vencimiento, igual que el enlace de
 * acceso, y por el mismo motivo: una user meta con un código en claro es una
 * contraseña temporal escrita en la base.
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;

/** Cuánto vale el código que se manda por correo. */
const USERS_DLX_PLUS_2FA_EMAIL_TTL = 10 * MINUTE_IN_SECONDS;

/** Genera, guarda y manda el código. */
function users_dlx_plus_2fa_email_send( int $user_id ): bool {
	$user = get_userdata( $user_id );

	if ( ! $user instanceof WP_User ) {
		return false;
	}

	$code = str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );

	update_user_meta(
		$user_id,
		'users_dlx_plus_2fa_email',
		array(
			'hash'    => wp_hash( $code ),
			'expires' => time() + USERS_DLX_PLUS_2FA_EMAIL_TTL,
		)
	);

	$subject = sprintf(
		/* translators: %s: nombre del sitio */
		__( 'Your code for %s', 'users-dlx-plus' ),
		wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES )
	);

	$body = sprintf(
		/* translators: 1: el código, 2: minutos que dura */
		__( "Your sign-in code is:\n\n%1\$s\n\nIt is good for %2\$d minutes. If you did not ask for it, ignore this message: without the code nobody gets in.", 'users-dlx-plus' ),
		$code,
		(int) ( USERS_DLX_PLUS_2FA_EMAIL_TTL / MINUTE_IN_SECONDS )
	);

	/**
	 * Filtra el correo del segundo factor.
	 *
	 * @param array{subject: string, body: string} $mail
	 * @param int                                  $user_id
	 * @param string                               $code
	 */
	$mail = (array) apply_filters(
		'users_dlx_plus_2fa_email',
		array(
			'subject' => $subject,
			'body'    => $body,
		),
		$user_id,
		$code
	);

	return wp_mail( $user->user_email, (string) $mail['subject'], (string) $mail['body'] );
}

/** ¿El código que escribieron es el que se mandó y sigue vivo? */
function users_dlx_plus_2fa_email_verify( int $user_id, string $code ): bool {
	// Sin meta guardada esto devuelve '', y `(array) ''` es `array( '' )`: un
	// array que no está vacío. Se pregunta por el tipo, no por el contenido.
	$stored = get_user_meta( $user_id, 'users_dlx_plus_2fa_email', true );
	$code   = preg_replace( '/\D/', '', $code ) ?? '';

	if ( ! is_array( $stored ) || ! isset( $stored['hash'], $stored['expires'] ) ) {
		return false;
	}

	if ( (int) $stored['expires'] < time() || '' === $code ) {
		return false;
	}

	if ( ! hash_equals( (string) $stored['hash'], wp_hash( $code ) ) ) {
		return false;
	}

	// De un solo uso.
	delete_user_meta( $user_id, 'users_dlx_plus_2fa_email' );

	return true;
}
