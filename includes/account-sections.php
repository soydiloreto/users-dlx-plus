<?php
/**
 * Las secciones que trae el plugin.
 *
 * Cada una se registra igual que lo haría cualquier otro plugin: no hay una
 * lista privilegiada. Lo único propio es que no se pueden borrar desde el
 * admin —apagar sí— porque el código que las pinta sigue estando.
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;

/** Registra las secciones propias del plugin. */
function users_plus_register_own_sections(): void {
	users_plus_register_section(
		'home',
		array(
			'label'    => __( 'Home', 'users-plus' ),
			'position' => 10,
			'render'   => 'users_plus_section_home',
		)
	);

	users_plus_register_section(
		'details',
		array(
			'label'    => __( 'Your details', 'users-plus' ),
			'position' => 30,
			'render'   => 'users_plus_section_details',
			'summary'  => 'users_plus_summary_details',
		)
	);

	users_plus_register_section(
		'accounts',
		array(
			'label'     => __( 'Linked accounts', 'users-plus' ),
			'position'  => 40,
			'render'    => 'users_plus_section_accounts',
			'summary'   => 'users_plus_summary_accounts',
			'available' => 'users_plus_sso_any',
			'why'       => __( 'There is no social network turned on, so there is nothing to link.', 'users-plus' ),
		)
	);

	users_plus_register_section(
		'security',
		array(
			'label'    => __( 'Security', 'users-plus' ),
			'position' => 50,
			'render'   => 'users_plus_section_security',
			'summary'  => 'users_plus_summary_security',
		)
	);

	users_plus_register_section(
		'notifications',
		array(
			'label'     => __( 'Notifications', 'users-plus' ),
			'position'  => 60,
			'render'    => 'users_plus_section_notifications',
			'available' => 'users_plus_notifications_any',
			'why'       => __( 'There is nothing to turn on or off: no notification is registered.', 'users-plus' ),
		)
	);

	users_plus_register_section(
		'privacy',
		array(
			'label'     => __( 'Your data', 'users-plus' ),
			'position'  => 70,
			'render'    => 'users_plus_section_privacy',
			'available' => 'users_plus_privacy_any',
			'why'       => __( 'Neither downloading your data nor deleting your account is allowed, so there is nothing to do here.', 'users-plus' ),
		)
	);
}
add_action( 'users_plus_register_sections', 'users_plus_register_own_sections' );

/** ¿Hay alguna red social prendida? */
function users_plus_sso_any(): bool {
	return function_exists( 'users_plus_sso_available' ) && array() !== users_plus_sso_available();
}

/** ¿Hay algún aviso que ofrecer? */
function users_plus_notifications_any(): bool {
	return array() !== users_plus_notification_prefs();
}

/** ¿Se le permite a alguien hacer algo con sus datos? */
function users_plus_privacy_any(): bool {
	return (bool) users_plus_option( 'users_plus_privacy_export' ) || (bool) users_plus_option( 'users_plus_privacy_delete' );
}

/* ── Portada ───────────────────────────────────────────────────────── */

/**
 * Las tarjetas del resumen.
 *
 * Las arma cada sección con lo que sabe. La portada no conoce ninguna: si
 * mañana LifterLMS agrega «cursos en progreso», aparece sola.
 *
 * @return array<int, array<string, string>>
 */
function users_plus_summaries(): array {
	$cards = array();

	foreach ( users_plus_sections() as $id => $section ) {
		if ( '' === $section['summary'] || ! is_callable( $section['summary'] ) ) {
			continue;
		}

		$card = call_user_func( $section['summary'] );

		if ( ! is_array( $card ) || '' === ( $card['value'] ?? '' ) ) {
			continue;
		}

		$cards[] = wp_parse_args(
			$card,
			array(
				'label' => $section['label'],
				'value' => '',
				'note'  => '',
				'link'  => users_plus_account_url( $id ),
				'cta'   => __( 'Open', 'users-plus' ),
			)
		);
	}

	/**
	 * Filtra las tarjetas del resumen de la cuenta.
	 *
	 * @param array<int, array<string, string>> $cards
	 */
	return apply_filters( 'users_plus_summaries', $cards );
}

/**
 * En la portada el título saluda.
 *
 * Es un filtro y no un <h2> propio de la plantilla a propósito: así sigue
 * habiendo un solo lugar donde se escribe el título de una sección.
 */
function users_plus_account_heading_home( string $heading, string $id, WP_User $user ): string {
	return 'home' === $id
		? sprintf(
			/* translators: %s: nombre de pila */
			__( 'Hello, %s', 'users-plus' ),
			users_plus_first_name( $user )
		)
		: $heading;
}
add_filter( 'users_plus_account_heading', 'users_plus_account_heading_home', 10, 3 );

/** Section home. */
function users_plus_section_home( WP_User $user ): void {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plantilla, ya escapada.
	echo users_plus_render(
		'account/home',
		array(
			'user'  => $user,
			'cards' => users_plus_summaries(),
		)
	); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plantilla, ya escapada.
}

/* ── Datos personales ──────────────────────────────────────────────── */

/** Pinta la sección de datos personales. */
function users_plus_section_details( WP_User $user ): void {
	echo users_plus_render( 'account/details', array( 'user' => $user ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plantilla, ya escapada.
}

/** Summary details. */
/**
 * @return array<string, mixed>
 */
function users_plus_summary_details(): array {
	$user   = wp_get_current_user();
	$total  = 0;
	$hechos = 0;

	foreach ( users_plus_fields() as $field ) {
		++$total;

		if ( '' !== (string) users_plus_value( $user->ID, $field['key'] ) ) {
			++$hechos;
		}
	}

	if ( 0 === $total ) {
		return array();
	}

	return array(
		'value' => sprintf( '%d/%d', $hechos, $total ),
		'note'  => __( 'fields filled in', 'users-plus' ),
		'cta'   => __( 'Edit', 'users-plus' ),
	);
}

/* ── Cuentas vinculadas ────────────────────────────────────────────── */

/** Pinta la sección de cuentas vinculadas. */
function users_plus_section_accounts( WP_User $user ): void {
	echo users_plus_render( 'account/accounts', array( 'user' => $user ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plantilla, ya escapada.
}

/** Summary accounts. */
/**
 * @return array<string, mixed>
 */
function users_plus_summary_accounts(): array {
	if ( array() === users_plus_sso_available() ) {
		return array();
	}

	$linked = count( users_plus_sso_linked( get_current_user_id() ) );

	return array(
		'value' => number_format_i18n( $linked ),
		'note'  => _n( 'network linked', 'networks linked', $linked, 'users-plus' ),
		'cta'   => __( 'Manage', 'users-plus' ),
	);
}

/* ── Seguridad ─────────────────────────────────────────────────────── */

/** Pinta la sección de seguridad. */
function users_plus_section_security( WP_User $user ): void {
	echo users_plus_render( 'account/security', array( 'user' => $user ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plantilla, ya escapada.
}

/** Summary security. */
/**
 * @return array<string, mixed>
 */
function users_plus_summary_security(): array {
	$open = count( users_plus_sessions( get_current_user_id() ) );

	return array(
		'value' => number_format_i18n( $open ),
		'note'  => _n( 'open session', 'open sessions', $open, 'users-plus' ),
		'cta'   => __( 'Review', 'users-plus' ),
	);
}

/* ── Notificaciones ────────────────────────────────────────────────── */

/**
 * Las preferencias de notificación.
 *
 * El plugin no sabe de qué avisa este sitio, así que no inventa ninguna: pone
 * la pantalla y quien tenga algo que avisar registra su preferencia acá.
 *
 * @return array<string, array<string, string>>
 */
function users_plus_notification_prefs(): array {
	/**
	 * Filtra las preferencias de notificación.
	 *
	 * Llega con las del plugin puestas —las de la cuenta y la seguridad, que
	 * las manda él— y esto es para lo que el sitio quiera sumarle: una
	 * transmisión, un curso, un foro. Nada de eso es de un plugin de usuarios.
	 *
	 * Cada una: clave => array( 'label' => …, 'help' => …, 'default' => '1' ).
	 * El valor se guarda en la user meta con esa misma clave.
	 *
	 * @param array<string, array<string, string>> $prefs
	 */
	return (array) apply_filters( 'users_plus_notification_prefs', users_plus_default_notifications() );
}

/** Section notifications. */
function users_plus_section_notifications( WP_User $user ): void {
	echo do_shortcode( '[users_plus_notifications]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode propio.
}

/**
 * Las preferencias de notificación, también sueltas.
 *
 * Shortcode: [users_plus_notifications]. La pantalla es del plugin; de qué avisa el
 * sitio lo registra quien tenga algo que avisar, con el filtro de arriba.
 */
function users_plus_shortcode_notifications(): string {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	users_plus_enqueue_styles();

	return users_plus_render(
		'account/notifications',
		array(
			'user'  => wp_get_current_user(),
			'prefs' => users_plus_notification_prefs(),
		)
	);
}
add_shortcode( 'users_plus_notifications', 'users_plus_shortcode_notifications' );

/** Guarda las preferencias de notificación. */
function users_plus_notifications_save(): void {
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( users_plus_login_url() );
		exit;
	}

	check_admin_referer( 'users_plus_notifications' );

	$user_id = get_current_user_id();

	foreach ( users_plus_notification_prefs() as $key => $pref ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
		update_user_meta( $user_id, $key, isset( $_POST[ $key ] ) ? '1' : '0' );
	}

	wp_safe_redirect( add_query_arg( 'users-plus', 'saved', users_plus_account_url( 'notifications' ) ) );
	exit;
}
add_action( 'admin_post_users_plus_notifications', 'users_plus_notifications_save' );

/* ── Tus datos ─────────────────────────────────────────────────────── */

/**
 * ¿Esta cuenta puede pedir que la borren?
 *
 * Una cuenta con permisos de administración no se borra sola: se le baja el
 * rol primero. Si no, el sitio se queda sin quien lo administre por un clic.
 */
function users_plus_can_request_erase( int $user_id ): bool {
	$user = get_userdata( $user_id );

	return $user instanceof WP_User && ! user_can( $user, 'manage_options' );
}

/**
 * Las solicitudes de datos que hizo esta persona, de un tipo.
 *
 * @param string $kind 'export_personal_data' o 'remove_personal_data'.
 * @return array<int, WP_Post>
 */
function users_plus_data_requests( string $email, string $kind = '' ): array {
	$found = get_posts(
		array(
			'post_type'      => 'user_request',
			'post_name__in'  => '' !== $kind ? array( $kind ) : array( 'export_personal_data', 'remove_personal_data' ),
			'title'          => $email,
			'post_status'    => 'any',
			'posts_per_page' => 10,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	return $found;
}

/**
 * El archivo listo de una solicitud de exportación, si lo hay.
 *
 * WordPress guarda la URL al terminar de armar el ZIP; que exista es la única
 * señal fiable de que hay algo para bajar.
 */
function users_plus_data_file( WP_Post $request ): string {
	return 'export_personal_data' === $request->post_name
		? (string) get_post_meta( $request->ID, '_export_file_url', true )
		: '';
}

/**
 * Cómo se llama cada estado, en castellano y sin jerga.
 *
 * @return array<string, array{0: string, 1: string}> estado => [tono, texto]
 */
function users_plus_data_states(): array {
	return array(
		'request-pending'   => array( 'pending', __( 'Waiting for you to confirm by email', 'users-plus' ) ),
		'request-confirmed' => array( 'pending', __( 'Confirmed — we are preparing it', 'users-plus' ) ),
		'request-completed' => array( 'ok', __( 'Ready', 'users-plus' ) ),
		'request-failed'    => array( 'off', __( 'Failed', 'users-plus' ) ),
	);
}

/**
 * ¿Este sitio puede mandar el correo de confirmación?
 *
 * Sin él, la solicitud se crea y se queda esperando para siempre. Lo que se
 * mira es lo que pasó la última vez que se intentó mandar algo, no si hay un
 * plugin de correo instalado: eso último es lo que hacía que esta pantalla
 * dijera que todo bien mientras no salía nada.
 */
function users_plus_data_mail_ready(): bool {
	return users_plus_mail_works();
}

/** Section privacy. */
function users_plus_section_privacy( WP_User $user ): void {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plantilla, ya escapada.
	echo users_plus_render(
		'account/privacy',
		array(
			'user'      => $user,
			'exports'   => users_plus_data_requests( $user->user_email, 'export_personal_data' ),
			'erasures'  => users_plus_data_requests( $user->user_email, 'remove_personal_data' ),
			'can_erase' => users_plus_can_request_erase( $user->ID ),
		)
	);
}

/**
 * Crea la solicitud de exportar o borrar.
 *
 * Se usan las solicitudes nativas de WordPress y no un exportador propio: ésas
 * ya mandan el correo de confirmación, arman el ZIP, y —lo importante— llaman
 * a los exportadores y borradores que registran los demás plugins. Un
 * exportador escrito acá devolvería la mitad de los datos de la persona.
 */
function users_plus_data_request(): void {
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( users_plus_login_url() );
		exit;
	}

	check_admin_referer( 'users_plus_data_request' );

	$user = wp_get_current_user();
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
	$kind = 'erase' === sanitize_key( wp_unslash( $_POST['users_plus_request'] ?? '' ) ) ? 'remove_personal_data' : 'export_personal_data';

	if ( 'remove_personal_data' === $kind && ! users_plus_can_request_erase( $user->ID ) ) {
		wp_safe_redirect( add_query_arg( 'users-plus', 'admin', users_plus_account_url( 'privacy' ) ) );
		exit;
	}

	$request_id = wp_create_user_request( $user->user_email, $kind );

	if ( is_wp_error( $request_id ) ) {
		wp_safe_redirect( add_query_arg( 'users-plus', 'error', users_plus_account_url( 'privacy' ) ) );
		exit;
	}

	wp_send_user_request( $request_id );

	wp_safe_redirect( add_query_arg( 'users-plus', 'requested', users_plus_account_url( 'privacy' ) ) );
	exit;
}
add_action( 'admin_post_users_plus_data_request', 'users_plus_data_request' );
