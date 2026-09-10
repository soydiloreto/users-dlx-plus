<?php
/**
 * La pantalla de estado: lo que hoy se rompe sin avisar.
 *
 * Casi nada de lo que hace este plugin falla con un error a la vista. La
 * página de la cuenta pierde el shortcode y muestra el texto crudo; el correo
 * deja de salir y los enlaces de acceso no llegan a nadie; el sitio queda en
 * HTTP y las passkeys desaparecen sin explicación; alguien pasa los enlaces
 * permanentes a «simples» y el área de cuenta empieza a dar 404. Todas esas
 * cosas se descubren cuando alguien se queja, no cuando pasan.
 *
 * Acá están todas juntas, en una sola pantalla que no toca nada: mira y
 * cuenta. Lo que se arregla se arregla en Herramientas.
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;

/**
 * Un chequeo, con su veredicto.
 *
 * @param string $label Qué se miró.
 * @param string $state 'ok', 'warn' o 'fail'.
 * @param string $detail Qué se encontró.
 * @return array{label: string, state: string, detail: string}
 */
function users_dlx_plus_check( string $label, string $state, string $detail ): array {
	return array(
		'label'  => $label,
		'state'  => $state,
		'detail' => $detail,
	);
}

/**
 * ¿La página que tiene el área de cuenta está en condiciones?
 *
 * Tres cosas distintas pueden estar mal y se ven igual desde afuera: que no
 * se haya elegido ninguna, que la elegida ya no exista o esté en borrador, o
 * que exista pero le falte el shortcode. Cada una se dice por su nombre.
 *
 * @param string $option El option que guarda el ID de la página.
 * @param string $shortcode El shortcode que esa página tiene que contener.
 * @param string $label Cómo se llama esa página acá adentro.
 * @return array{label: string, state: string, detail: string}
 */
function users_dlx_plus_check_page( string $option, string $shortcode, string $label ): array {
	$id = (int) users_dlx_plus_option( $option );

	if ( $id <= 0 ) {
		return users_dlx_plus_check( $label, 'warn', __( 'No page chosen yet.', 'users-dlx-plus' ) );
	}

	$page = get_post( $id );

	if ( ! $page instanceof WP_Post || 'page' !== $page->post_type ) {
		return users_dlx_plus_check( $label, 'fail', __( 'The chosen page no longer exists.', 'users-dlx-plus' ) );
	}

	if ( 'publish' !== $page->post_status ) {
		return users_dlx_plus_check(
			$label,
			'fail',
			sprintf(
				/* translators: %s: title of the page */
				__( '“%s” is not published, so nobody can reach it.', 'users-dlx-plus' ),
				$page->post_title
			)
		);
	}

	if ( ! has_shortcode( (string) $page->post_content, $shortcode ) ) {
		return users_dlx_plus_check(
			$label,
			'fail',
			sprintf(
				/* translators: 1: title of the page, 2: the shortcode that is missing */
				__( '“%1$s” does not contain %2$s, so the page renders empty.', 'users-dlx-plus' ),
				$page->post_title,
				'[' . $shortcode . ']'
			)
		);
	}

	return users_dlx_plus_check( $label, 'ok', $page->post_title );
}

/**
 * Todos los chequeos, en orden de lo que más duele.
 *
 * @return array<int, array{label: string, state: string, detail: string}>
 */
function users_dlx_plus_checks(): array {
	$checks = array();

	$checks[] = users_dlx_plus_check_page( 'users_dlx_plus_account_page', 'users_dlx_plus_account', __( 'Account page', 'users-dlx-plus' ) );
	$checks[] = users_dlx_plus_check_page( 'users_dlx_plus_login_page', 'users_dlx_plus_login', __( 'Sign-in page', 'users-dlx-plus' ) );

	// El correo. Sin esto no entra nadie por enlace ni pasa un segundo paso.
	$mail = users_dlx_plus_mail_status();

	if ( 'fail' === $mail['state'] ) {
		$checks[] = users_dlx_plus_check(
			__( 'Outgoing mail', 'users-dlx-plus' ),
			'fail',
			'' !== $mail['error']
				? wp_strip_all_tags( $mail['error'] )
				: __( 'The last message could not be sent.', 'users-dlx-plus' )
		);
	} elseif ( 'ok' === $mail['state'] ) {
		$checks[] = users_dlx_plus_check(
			__( 'Outgoing mail', 'users-dlx-plus' ),
			'ok',
			sprintf(
				/* translators: %s: how long ago, e.g. "2 hours" */
				__( 'Last message sent %s ago.', 'users-dlx-plus' ),
				human_time_diff( $mail['time'] )
			)
		);
	} else {
		$checks[] = users_dlx_plus_check(
			__( 'Outgoing mail', 'users-dlx-plus' ),
			'warn',
			__( 'Nothing sent yet. Send a test from Tools.', 'users-dlx-plus' )
		);
	}

	// HTTPS. Las passkeys son WebAuthn, y WebAuthn no existe fuera de HTTPS.
	//
	// Se mira wp_is_using_https() y no is_ssl(): is_ssl() dice si *este* pedido
	// entró por TLS, que es falso en WP-CLI y puede serlo detrás de un proxy
	// que termina el TLS antes. Lo que decide si el navegador habla HTTPS —y
	// por lo tanto si WebAuthn existe— es el esquema de home_url().
	$passkeys = (int) users_dlx_plus_option( 'users_dlx_plus_passkey_enabled' );

	if ( wp_is_using_https() ) {
		$checks[] = users_dlx_plus_check( 'HTTPS', 'ok', __( 'The site is served over HTTPS.', 'users-dlx-plus' ) );
	} else {
		$checks[] = users_dlx_plus_check(
			'HTTPS',
			$passkeys ? 'fail' : 'warn',
			$passkeys
				? __( 'Passkeys are on, but WebAuthn does not work outside HTTPS: nobody can register or use one.', 'users-dlx-plus' )
				: __( 'The site is not served over HTTPS. Passkeys will not work if you turn them on.', 'users-dlx-plus' )
		);
	}

	// Enlaces permanentes. El área de cuenta cuelga de una rewrite rule.
	$permalinks = (string) get_option( 'permalink_structure' );

	$checks[] = '' === $permalinks
		? users_dlx_plus_check(
			__( 'Permalinks', 'users-dlx-plus' ),
			'fail',
			__( 'Set to “Plain”. The account area needs pretty permalinks to route its sections.', 'users-dlx-plus' )
		)
		: users_dlx_plus_check( __( 'Permalinks', 'users-dlx-plus' ), 'ok', $permalinks );

	// Las reglas de reescritura, que se regraban cuando cambia la versión.
	$checks[] = get_option( 'users_dlx_plus_rewrite_version' ) === USERS_DLX_PLUS_VERSION
		? users_dlx_plus_check( __( 'Rewrite rules', 'users-dlx-plus' ), 'ok', __( 'Up to date.', 'users-dlx-plus' ) )
		: users_dlx_plus_check(
			__( 'Rewrite rules', 'users-dlx-plus' ),
			'warn',
			__( 'Not rewritten for this version yet. Flush them from Tools if a section 404s.', 'users-dlx-plus' )
		);

	// Que haya al menos una forma de entrar.
	$checks[] = users_dlx_plus_check_ways_in();

	// Redes sociales cargadas pero apagadas, y al revés.
	$social = users_dlx_plus_check_social();

	if ( null !== $social ) {
		$checks[] = $social;
	}

	return $checks;
}

/**
 * ¿Queda alguna puerta abierta?
 *
 * Se puede apagar la contraseña, no configurar ninguna red y dejar el enlace
 * por correo como única entrada. Si además el correo no sale, no entra nadie
 * —ni el administrador— y el sitio queda cerrado con llave por dentro.
 *
 * @return array{label: string, state: string, detail: string}
 */
function users_dlx_plus_check_ways_in(): array {
	$formas = array();

	if ( users_dlx_plus_login_has_password() ) {
		$formas[] = __( 'password', 'users-dlx-plus' );
	}

	if ( users_dlx_plus_login_has_link() ) {
		$formas[] = __( 'e-mail link', 'users-dlx-plus' );
	}

	if ( array() !== users_dlx_plus_sso_available() ) {
		$formas[] = __( 'social login', 'users-dlx-plus' );
	}

	if ( array() === $formas ) {
		return users_dlx_plus_check(
			__( 'Ways in', 'users-dlx-plus' ),
			'fail',
			__( 'No sign-in method is enabled at all.', 'users-dlx-plus' )
		);
	}

	$solo_correo = array( __( 'e-mail link', 'users-dlx-plus' ) ) === $formas;

	if ( $solo_correo && ! users_dlx_plus_mail_works() ) {
		return users_dlx_plus_check(
			__( 'Ways in', 'users-dlx-plus' ),
			'fail',
			__( 'The e-mail link is the only way in, and mail is failing. Nobody can sign in.', 'users-dlx-plus' )
		);
	}

	return users_dlx_plus_check( __( 'Ways in', 'users-dlx-plus' ), 'ok', implode( ', ', $formas ) );
}

/**
 * Proveedores con las credenciales puestas pero apagados.
 *
 * Es el error más aburrido y el más común: se carga el ID y el secreto, se
 * guarda, y el botón no aparece porque falta tildar el proveedor.
 *
 * @return array{label: string, state: string, detail: string}|null
 */
function users_dlx_plus_check_social(): ?array {
	$listos   = array();
	$dormidos = array();

	foreach ( users_dlx_plus_sso_providers() as $id => $provider ) {
		if ( ! users_dlx_plus_sso_configured( $id ) ) {
			continue;
		}

		$listos[] = $id;

		if ( ! users_dlx_plus_sso_ready( $id ) ) {
			$dormidos[] = (string) ( $provider['name'] ?? $id );
		}
	}

	if ( array() === $listos ) {
		return null;
	}

	if ( array() === $dormidos ) {
		return users_dlx_plus_check(
			__( 'Social login', 'users-dlx-plus' ),
			'ok',
			sprintf(
				/* translators: %d: number of providers */
				_n( '%d provider configured and enabled.', '%d providers configured and enabled.', count( $listos ), 'users-dlx-plus' ),
				count( $listos )
			)
		);
	}

	return users_dlx_plus_check(
		__( 'Social login', 'users-dlx-plus' ),
		'warn',
		sprintf(
			/* translators: %s: comma-separated provider names */
			__( 'Credentials are saved but the provider is off, so no button shows: %s.', 'users-dlx-plus' ),
			implode( ', ', $dormidos )
		)
	);
}

/**
 * Cuánta gente usa cada cosa.
 *
 * Una sola consulta por número y sobre `usermeta`, que tiene índice por
 * `meta_key`. En un sitio con veinticinco mil cuentas esto tiene que costar
 * lo mismo que en uno con veinte.
 *
 * @return array<string, int>
 */
function users_dlx_plus_stats(): array {
	global $wpdb;

	$contar = static function ( string $meta ) use ( $wpdb ): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- es la pantalla de estado: el número tiene que ser el de ahora, no el del caché.
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != ''", $meta )
		);
	};

	return array(
		'users'    => (int) count_users()['total_users'],
		'totp'     => $contar( 'users_dlx_plus_totp_secret' ),
		'passkeys' => $contar( 'users_dlx_plus_passkeys' ),
		'social'   => $contar( 'users_dlx_plus_sso' ),
		'handles'  => $contar( 'users_dlx_plus_handle' ),
	);
}

/**
 * Lo que hay que pegar en un ticket de soporte.
 *
 * @return array<string, string>
 */
function users_dlx_plus_environment(): array {
	global $wp_version;

	return array(
		__( 'Plugin version', 'users-dlx-plus' )    => USERS_DLX_PLUS_VERSION,
		__( 'WordPress', 'users-dlx-plus' )         => (string) $wp_version,
		'PHP'                                       => PHP_VERSION,
		__( 'Site language', 'users-dlx-plus' )     => (string) get_locale(),
		__( 'Multisite', 'users-dlx-plus' )         => is_multisite() ? __( 'yes', 'users-dlx-plus' ) : __( 'no', 'users-dlx-plus' ),
		__( 'Object cache', 'users-dlx-plus' )      => wp_using_ext_object_cache() ? __( 'external', 'users-dlx-plus' ) : __( 'none', 'users-dlx-plus' ),
		__( 'WordPress profile', 'users-dlx-plus' ) => (string) users_dlx_plus_option( 'users_dlx_plus_wp_profile' ),
	);
}

/** Screen status. */
function users_dlx_plus_screen_status(): void {
	users_dlx_plus_screen_open( __( 'Status', 'users-dlx-plus' ) );
	users_dlx_plus_intro( __( 'Nothing here changes anything: it looks and it counts. What needs fixing gets fixed in Tools.', 'users-dlx-plus' ) );

	$checks = users_dlx_plus_checks();
	$malos  = 0;

	foreach ( $checks as $check ) {
		if ( 'fail' === $check['state'] ) {
			++$malos;
		}
	}

	if ( 0 === $malos ) {
		users_dlx_plus_notice( __( 'Everything checks out.', 'users-dlx-plus' ) );
	} else {
		users_dlx_plus_notice(
			sprintf(
				/* translators: %d: number of failing checks */
				_n( '%d thing needs attention.', '%d things need attention.', $malos, 'users-dlx-plus' ),
				$malos
			),
			'error'
		);
	}

	$iconos = array(
		'ok'   => 'dashicons-yes-alt',
		'warn' => 'dashicons-warning',
		'fail' => 'dashicons-dismiss',
	);

	echo '<table class="widefat striped users-dlx-plus-status">';
	echo '<tbody>';

	foreach ( $checks as $check ) {
		printf(
			'<tr class="users-dlx-plus-status--%1$s"><td class="users-dlx-plus-status__icon"><span class="dashicons %2$s"></span></td><th scope="row">%3$s</th><td>%4$s</td></tr>',
			esc_attr( $check['state'] ),
			esc_attr( $iconos[ $check['state'] ] ),
			esc_html( $check['label'] ),
			esc_html( $check['detail'] )
		);
	}

	echo '</tbody></table>';

	$stats = users_dlx_plus_stats();

	printf( '<h2>%s</h2>', esc_html__( 'How much of it is being used', 'users-dlx-plus' ) );
	echo '<table class="widefat striped"><tbody>';

	$filas = array(
		__( 'Accounts', 'users-dlx-plus' )           => $stats['users'],
		__( 'With an authenticator app', 'users-dlx-plus' ) => $stats['totp'],
		__( 'With a passkey', 'users-dlx-plus' )     => $stats['passkeys'],
		__( 'With a social account linked', 'users-dlx-plus' ) => $stats['social'],
		__( 'With a public name', 'users-dlx-plus' ) => $stats['handles'],
	);

	foreach ( $filas as $label => $valor ) {
		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s</td></tr>',
			esc_html( (string) $label ),
			esc_html( number_format_i18n( $valor ) )
		);
	}

	echo '</tbody></table>';

	printf( '<h2>%s</h2>', esc_html__( 'Environment', 'users-dlx-plus' ) );
	users_dlx_plus_intro( __( 'Copy this into a support message and half the back-and-forth disappears.', 'users-dlx-plus' ) );
	echo '<table class="widefat striped"><tbody>';

	foreach ( users_dlx_plus_environment() as $label => $valor ) {
		printf(
			'<tr><th scope="row">%1$s</th><td><code>%2$s</code></td></tr>',
			esc_html( (string) $label ),
			esc_html( $valor )
		);
	}

	echo '</tbody></table>';

	users_dlx_plus_screen_close();
}
