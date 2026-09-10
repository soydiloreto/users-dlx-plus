<?php
/**
 * Los avisos que manda este plugin, por su cuenta.
 *
 * La pantalla de notificaciones existe desde el principio, pero hasta acá
 * llegaba vacía: lo que se ofrecía ahí lo tenía que registrar el sitio. Eso
 * está bien para lo que es del sitio —una transmisión, un curso, un foro— y
 * está mal como punto de partida: un plugin que muestra una sección vacía en
 * una instalación limpia le está pidiendo al sitio que la complete.
 *
 * Así que acá viven los avisos que son de este plugin, porque son de cosas
 * que este plugin sabe y manda: quién entró a tu cuenta y qué cambió en tu
 * seguridad. No hay ninguno que dependa de otro plugin, y los del sitio se
 * suman a éstos con el mismo filtro de siempre.
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;

/**
 * Los avisos propios, los que este plugin manda de verdad.
 *
 * No se registran con el filtro: son el punto de partida, y el filtro es para
 * lo que el sitio le suma encima. Un plugin que se engancha a su propio filtro
 * para traer lo suyo hace que su base parezca opcional, y basta con que
 * alguien devuelva un array vacío desde otro lado para quedarse sin nada.
 *
 * @return array<string, array<string, string>>
 */
function users_plus_default_notifications(): array {
	$prefs = array();

	$prefs['users_plus_notify_login'] = array(
		'label'   => __( 'When somebody signs in to my account from a new device', 'users-plus' ),
		'help'    => __( 'The first time a browser or a phone gets in. From then on, that one is quiet.', 'users-plus' ),
		'default' => '1',
	);

	$prefs['users_plus_notify_security'] = array(
		'label'   => __( 'When something in my security changes', 'users-plus' ),
		'help'    => __( 'A passkey added or removed, two-step verification turned on or off, a social account linked or unlinked.', 'users-plus' ),
		'default' => '1',
	);

	return $prefs;
}

/** ¿Esta persona quiere este aviso? */
function users_plus_wants( int $user_id, string $key ): bool {
	$prefs = users_plus_notification_prefs();

	if ( ! isset( $prefs[ $key ] ) ) {
		return false;
	}

	$saved = get_user_meta( $user_id, $key, true );

	return '' === (string) $saved ? ! empty( $prefs[ $key ]['default'] ) : (bool) $saved;
}

/**
 * Manda un aviso, si la persona lo quiere.
 *
 * Devuelve false también cuando no lo quiere: quien llama no tiene que
 * preguntar dos veces ni saber cómo se guarda la preferencia.
 */
function users_plus_notify( int $user_id, string $key, string $subject, string $body ): bool {
	if ( ! users_plus_wants( $user_id, $key ) ) {
		return false;
	}

	$user = get_userdata( $user_id );

	if ( ! $user instanceof WP_User ) {
		return false;
	}

	/**
	 * Filtra un aviso antes de mandarlo.
	 *
	 * @param array{subject: string, body: string} $mail
	 * @param int                                  $user_id
	 * @param string                               $key
	 */
	$mail = (array) apply_filters(
		'users_plus_notification',
		array(
			'subject' => $subject,
			'body'    => $body,
		),
		$user_id,
		$key
	);

	return wp_mail( $user->user_email, (string) $mail['subject'], (string) $mail['body'] );
}

/** El nombre del sitio, sin las entidades HTML que guarda WordPress. */
function users_plus_site_name(): string {
	return wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
}

/* ── Entrar desde un aparato nuevo ─────────────────────────────────── */

/**
 * Cómo se reconoce un aparato.
 *
 * Es el user agent, hasheado. No es una medida de seguridad —el user agent
 * miente si alguien quiere— y no tiene por qué serlo: sirve para no avisar
 * cincuenta veces desde el mismo navegador. La IP queda afuera a propósito:
 * cambia sola, y con ella cada entrada desde el mismo teléfono sería «nueva».
 */
function users_plus_device_id(): string {
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

	return substr( hash( 'sha256', $ua ), 0, 16 );
}

/**
 * Avisa cuando alguien entra desde un aparato que no se había visto.
 *
 * Se cuelga de `users_plus_logged_in`, que es por donde pasan todas las formas de
 * entrar que maneja el plugin: el enlace, la contraseña, una red social y una
 * passkey. Una sola vez por aparato.
 */
function users_plus_notify_new_device( int $user_id, string $via ): void {
	$id        = users_plus_device_id();
	$conocidos = (array) get_user_meta( $user_id, 'users_plus_devices', true );
	$conocidos = array_filter( array_map( 'strval', $conocidos ) );

	if ( in_array( $id, $conocidos, true ) ) {
		return;
	}

	// Se anota antes de mandar: si el correo falla, el aviso no queda
	// repitiéndose en cada entrada desde el mismo navegador.
	$conocidos[] = $id;
	update_user_meta( $user_id, 'users_plus_devices', array_slice( $conocidos, -20 ) );

	// La primera vez que se ve un aparato es, para casi todo el mundo, la vez
	// que se creó la cuenta. Avisarle a alguien que entró él mismo, mientras
	// entra, no le dice nada.
	if ( 1 === count( $conocidos ) ) {
		return;
	}

	$agente = users_plus_user_agent( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' );

	$aparato = trim( sprintf( '%s · %s %s', $agente['device'], $agente['browser'], $agente['os'] ) );

	users_plus_notify(
		$user_id,
		'users_plus_notify_login',
		sprintf(
			/* translators: %s: nombre del sitio */
			__( 'New sign-in to your account on %s', 'users-plus' ),
			users_plus_site_name()
		),
		sprintf(
			/* translators: 1: aparato y navegador, 2: fecha y hora, 3: cómo entró, 4: dirección del área de cuenta */
			__( "Somebody just signed in to your account.\n\n%1\$s\n%2\$s\nWay in: %3\$s\n\nIf it was you, there is nothing to do. If it was not, close that session and review your security here:\n%4\$s", 'users-plus' ),
			$aparato,
			wp_date( 'j M Y, H:i' ),
			users_plus_via_label( $via ),
			users_plus_account_url( 'security' )
		)
	);
}
add_action( 'users_plus_logged_in', 'users_plus_notify_new_device', 10, 2 );

/** Cómo se entró, en castellano. */
function users_plus_via_label( string $via ): string {
	$labels = array(
		'link'     => __( 'a link sent to your email', 'users-plus' ),
		'password' => __( 'your password', 'users-plus' ),
		'sso'      => __( 'a social account', 'users-plus' ),
		'passkey'  => __( 'a passkey', 'users-plus' ),
	);

	return $labels[ $via ] ?? $via;
}

/* ── Cambios en la seguridad ───────────────────────────────────────── */

/**
 * Avisa que algo de la seguridad cambió.
 *
 * Lo llaman las partes del plugin que cambian algo: las passkeys, el segundo
 * factor y las redes vinculadas. El valor del aviso es justamente que llegue
 * cuando NO fuiste vos: si alguien entró y se agregó una passkey, ése es el
 * momento de enterarse.
 */
function users_plus_notify_security( int $user_id, string $que ): void {
	users_plus_notify(
		$user_id,
		'users_plus_notify_security',
		sprintf(
			/* translators: %s: nombre del sitio */
			__( 'Your security on %s changed', 'users-plus' ),
			users_plus_site_name()
		),
		sprintf(
			/* translators: 1: qué cambió, 2: fecha y hora, 3: dirección del área de cuenta */
			__( "This changed in your account:\n\n%1\$s\n%2\$s\n\nIf it was you, there is nothing to do. If it was not, review your security here:\n%3\$s", 'users-plus' ),
			$que,
			wp_date( 'j M Y, H:i' ),
			users_plus_account_url( 'security' )
		)
	);
}
