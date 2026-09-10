<?php
/**
 * La pantalla de herramientas: lo poco que se puede hacer a mano.
 *
 * Cada cosa de acá existe porque alguna vez hubo que hacerla por SSH o con un
 * script suelto. Nada de esto es una función del plugin: son los cinco
 * botones que aparecen cuando algo salió mal y hay alguien esperando del otro
 * lado.
 *
 * Lo que NO está, a propósito: leer el código de dos pasos de una persona. El
 * código se guarda hasheado, así que «verlo» sería romperlo a fuerza bruta, y
 * un botón así le da a cualquier administrador el segundo factor de cualquier
 * cuenta —que es exactamente lo que el segundo factor tiene que impedir—. En
 * su lugar se le manda uno nuevo.
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;

/** El option donde se guarda qué pasó con la última herramienta que se usó. */
const USERS_DLX_PLUS_TOOL_RESULT = 'users_dlx_plus_tool_result';

/**
 * Deja anotado el resultado para después de la redirección.
 *
 * Nunca vuelve: redirige y corta. Va anotado como `never` en el docblock y no
 * en la firma porque el plugin todavía soporta PHP 8.0, donde ese tipo nativo
 * no existe.
 *
 * @param string $text  Qué pasó.
 * @param string $type  'success' o 'error'.
 * @return never
 */
function users_dlx_plus_tool_done( string $text, string $type = 'success' ): void {
	set_transient( USERS_DLX_PLUS_TOOL_RESULT . '_' . get_current_user_id(), array( $text, $type ), 60 );

	wp_safe_redirect( users_dlx_plus_admin_url( 'users-dlx-plus-tools' ) );
	exit;
}

/**
 * Todo lo que se dispara desde esta pantalla entra por acá.
 *
 * Un único `admin_post`, un único nonce y una única comprobación de permiso:
 * repartido en cinco endpoints, tarde o temprano uno se queda sin alguna de
 * las tres.
 *
 * @return void
 */
function users_dlx_plus_tools_action(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'users-dlx-plus' ) );
	}

	check_admin_referer( 'users_dlx_plus_tools' );

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
	$tool = isset( $_POST['tool'] ) ? sanitize_key( wp_unslash( $_POST['tool'] ) ) : '';

	// Un mapa y no un switch: cada herramienta termina en una redirección que
	// corta la ejecución, así que un `break` detrás de cada una sería código
	// muerto y un comentario de fall-through sería mentira.
	$herramientas = array(
		'flush'  => 'users_dlx_plus_tool_flush',
		'code'   => 'users_dlx_plus_tool_send_code',
		'close'  => 'users_dlx_plus_tool_close_sessions',
		'export' => 'users_dlx_plus_tool_export',
		'import' => 'users_dlx_plus_tool_import',
	);

	if ( ! isset( $herramientas[ $tool ] ) ) {
		users_dlx_plus_tool_done( __( 'Nothing to do.', 'users-dlx-plus' ), 'error' );
	}

	$herramientas[ $tool ]();
}

/**
 * Regraba las reglas de reescritura.
 *
 * La regla en sí se registra en `init` en cada pedido; lo que se pierde es la
 * copia guardada, y eso es lo que se rehace acá.
 *
 * @return never
 */
function users_dlx_plus_tool_flush(): void {
	flush_rewrite_rules( false );
	update_option( 'users_dlx_plus_rewrite_version', USERS_DLX_PLUS_VERSION );

	users_dlx_plus_tool_done( __( 'Rewrite rules rebuilt.', 'users-dlx-plus' ) );
}

add_action( 'admin_post_users_dlx_plus_tools', 'users_dlx_plus_tools_action' );

/**
 * Le manda a una persona un código de dos pasos nuevo.
 *
 * Se dice lo mismo exista o no la cuenta: esta pantalla es para el
 * administrador, pero el hábito de no confirmar quién está registrado se
 * mantiene igual.
 *
 * @return never
 */
function users_dlx_plus_tool_send_code(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado en users_dlx_plus_tools_action().
	$typed = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$user  = '' !== $typed ? get_user_by( 'email', $typed ) : false;

	if ( ! $user instanceof WP_User ) {
		users_dlx_plus_tool_done( __( 'No account with that e-mail address.', 'users-dlx-plus' ), 'error' );
	}

	$ok = users_dlx_plus_2fa_email_send( $user->ID );

	users_dlx_plus_tool_done(
		$ok
			? sprintf(
				/* translators: %s: e-mail address */
				__( 'A fresh code is on its way to %s.', 'users-dlx-plus' ),
				$user->user_email
			)
			: __( 'The code could not be sent. Check outgoing mail in Status.', 'users-dlx-plus' ),
		$ok ? 'success' : 'error'
	);
}

/**
 * Cierra sesiones: las de una persona, o las de todo el mundo.
 *
 * Lo segundo deja afuera a quien lo apretó, y está bien que así sea: si se
 * usa es porque se sospecha que hay una sesión ajena abierta, y dejar la
 * propia viva por comodidad sería dejar abierta justamente la que importa.
 *
 * @return never
 */
function users_dlx_plus_tool_close_sessions(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- verificado en users_dlx_plus_tools_action().
	$scope = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'one';
	$typed = sanitize_email( wp_unslash( $_POST['close_email'] ?? '' ) );
	// phpcs:enable

	if ( 'all' === $scope ) {
		WP_Session_Tokens::destroy_all_for_all_users();

		users_dlx_plus_tool_done( __( 'Every session on the site is closed. Everyone signs in again, you included.', 'users-dlx-plus' ) );
	}

	$user = '' !== $typed ? get_user_by( 'email', $typed ) : false;

	if ( ! $user instanceof WP_User ) {
		users_dlx_plus_tool_done( __( 'No account with that e-mail address.', 'users-dlx-plus' ), 'error' );
	}

	WP_Session_Tokens::get_instance( $user->ID )->destroy_all();

	users_dlx_plus_tool_done(
		sprintf(
			/* translators: %s: e-mail address */
			__( 'Every session for %s is closed.', 'users-dlx-plus' ),
			$user->user_email
		)
	);
}

/**
 * Qué se lleva un export.
 *
 * Los ajustes y los campos: lo que define cómo se comporta el plugin. Ni una
 * credencial de red social —eso es un secreto, y un JSON que se manda por
 * correo no es lugar para uno— ni nada que pertenezca a una persona.
 *
 * @return array<string, mixed>
 */
function users_dlx_plus_tool_settings(): array {
	$out = array();

	foreach ( array_keys( users_dlx_plus_option_defaults() ) as $key ) {
		if ( 'users_dlx_plus_sso' === $key ) {
			continue;
		}

		$out[ $key ] = users_dlx_plus_option( $key );
	}

	$out['users_dlx_plus_fields'] = get_option( 'users_dlx_plus_fields', array() );

	return $out;
}

/**
 * Baja los ajustes como un JSON.
 *
 * @return never
 */
function users_dlx_plus_tool_export(): void {
	$payload = array(
		'plugin'   => 'users-dlx-plus',
		'version'  => USERS_DLX_PLUS_VERSION,
		'site'     => home_url(),
		'exported' => gmdate( 'c' ),
		'settings' => users_dlx_plus_tool_settings(),
	);

	$nombre = 'users-plus-' . gmdate( 'Y-m-d' ) . '.json';

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $nombre );

	echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
}

/**
 * Mete de vuelta un JSON exportado.
 *
 * Sólo se aceptan las claves que el plugin conoce. Un archivo con basura
 * adentro —o de otro plugin, o tocado a mano— no puede escribir options que
 * no sean suyas.
 *
 * @return never
 */
function users_dlx_plus_tool_import(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- verificado arriba; el contenido se valida como JSON acá abajo.
	$subido = isset( $_FILES['file']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['file']['tmp_name'] ) ) : '';

	if ( '' === $subido || ! is_uploaded_file( $subido ) ) {
		users_dlx_plus_tool_done( __( 'No file uploaded.', 'users-dlx-plus' ), 'error' );
	}

	$raw  = (string) file_get_contents( $subido ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- es un archivo local recién subido, no una URL.
	$json = json_decode( $raw, true );

	if ( ! is_array( $json ) || ! isset( $json['settings'] ) || ! is_array( $json['settings'] ) ) {
		users_dlx_plus_tool_done( __( 'That file is not a Users+ export.', 'users-dlx-plus' ), 'error' );
	}

	$conocidas = array_keys( users_dlx_plus_option_defaults() );
	$escritas  = 0;

	foreach ( $json['settings'] as $key => $value ) {
		if ( 'users_dlx_plus_fields' === $key && is_array( $value ) ) {
			update_option( 'users_dlx_plus_fields', $value );
			++$escritas;
			continue;
		}

		if ( ! in_array( $key, $conocidas, true ) || 'users_dlx_plus_sso' === $key ) {
			continue;
		}

		users_dlx_plus_save_options( array( $key => $value ) );
		++$escritas;
	}

	users_dlx_plus_tool_done(
		sprintf(
			/* translators: %d: number of settings written */
			_n( '%d setting restored.', '%d settings restored.', $escritas, 'users-dlx-plus' ),
			$escritas
		)
	);
}

/**
 * Una herramienta: título, explicación y su propio formulario.
 *
 * @param string   $title Cómo se llama.
 * @param string   $text  Qué hace y cuándo se usa.
 * @param callable $form  Lo que va adentro del formulario.
 * @param bool     $files Si el formulario sube un archivo.
 * @return void
 */
function users_dlx_plus_tool_box( string $title, string $text, callable $form, bool $files = false, string $action = 'users_dlx_plus_tools' ): void {
	if ( '' !== $title ) {
		printf( '<h2>%s</h2>', esc_html( $title ) );
	}

	users_dlx_plus_intro( $text );

	printf(
		'<form method="post" action="%s"%s>',
		esc_url( admin_url( 'admin-post.php' ) ),
		$files ? ' enctype="multipart/form-data"' : ''
	);

	wp_nonce_field( $action );
	printf( '<input type="hidden" name="action" value="%s">', esc_attr( $action ) );

	$form();

	echo '</form>';
}

/** Screen tools. */
function users_dlx_plus_screen_tools(): void {
	users_dlx_plus_screen_open( __( 'Tools', 'users-dlx-plus' ) );

	$result = get_transient( USERS_DLX_PLUS_TOOL_RESULT . '_' . get_current_user_id() );

	if ( is_array( $result ) ) {
		delete_transient( USERS_DLX_PLUS_TOOL_RESULT . '_' . get_current_user_id() );
		users_dlx_plus_notice( (string) $result[0], (string) $result[1] );
	}

	users_dlx_plus_intro( __( 'Five buttons for when something went wrong and somebody is waiting on the other side.', 'users-dlx-plus' ) );

	users_dlx_plus_tool_box(
		__( 'Rebuild the rewrite rules', 'users-dlx-plus' ),
		__( 'Use this when a section of the account area returns a 404. The account area routes its sections through rewrite rules, and those go stale when permalinks change or another plugin rewrites them.', 'users-dlx-plus' ),
		static function (): void {
			echo '<input type="hidden" name="tool" value="flush">';
			submit_button( __( 'Rebuild', 'users-dlx-plus' ), 'secondary', 'submit', false );
		}
	);

	users_dlx_plus_tool_box(
		__( 'Send a test message', 'users-dlx-plus' ),
		__( 'It goes to your own address. If it does not arrive, the sign-in links and the second-step codes are not arriving either.', 'users-dlx-plus' ),
		static function (): void {
			submit_button( __( 'Send it', 'users-dlx-plus' ), 'secondary', 'submit', false );
		},
		false,
		'users_dlx_plus_mail_test'
	);

	users_dlx_plus_tool_box(
		__( 'Send someone a fresh code', 'users-dlx-plus' ),
		__( 'For when a person says the second-step code never arrived. It sends a new one and voids the previous one. You never get to see it — the code is stored hashed, which is the point.', 'users-dlx-plus' ),
		static function (): void {
			echo '<input type="hidden" name="tool" value="code">';
			printf(
				'<input type="email" name="email" class="regular-text" required placeholder="%s"> ',
				esc_attr__( 'their e-mail address', 'users-dlx-plus' )
			);
			submit_button( __( 'Send the code', 'users-dlx-plus' ), 'secondary', 'submit', false );
		}
	);

	users_dlx_plus_tool_box(
		__( 'Close sessions', 'users-dlx-plus' ),
		__( 'Closing every session on the site signs you out too. That is on purpose: if you are doing this, the session you are least sure about might be your own.', 'users-dlx-plus' ),
		static function (): void {
			echo '<input type="hidden" name="tool" value="close">';
			echo '<p><label><input type="radio" name="scope" value="one" checked> ';
			esc_html_e( 'Just this person:', 'users-dlx-plus' );
			printf(
				' <input type="email" name="close_email" class="regular-text" placeholder="%s"></label></p>',
				esc_attr__( 'their e-mail address', 'users-dlx-plus' )
			);
			echo '<p><label><input type="radio" name="scope" value="all"> ';
			esc_html_e( 'Everyone on the site, me included', 'users-dlx-plus' );
			echo '</label></p>';
			submit_button( __( 'Close them', 'users-dlx-plus' ), 'delete', 'submit', false );
		}
	);

	users_dlx_plus_tool_box(
		__( 'Settings as a file', 'users-dlx-plus' ),
		__( 'Take the settings and the user fields from one site to another — staging to production, or a site you set up once and want to repeat. Social login credentials are deliberately left out: those are secrets, and a JSON file that travels by e-mail is no place for one.', 'users-dlx-plus' ),
		static function (): void {
			echo '<p>';
			echo '<input type="hidden" name="tool" value="export">';
			submit_button( __( 'Download them', 'users-dlx-plus' ), 'secondary', 'submit', false );
			echo '</p>';
		}
	);

	users_dlx_plus_tool_box(
		'',
		__( 'Restoring overwrites what is set right now. Only keys this plugin knows are read, so a file from somewhere else cannot write settings that are not ours.', 'users-dlx-plus' ),
		static function (): void {
			echo '<p>';
			echo '<input type="hidden" name="tool" value="import">';
			echo '<input type="file" name="file" accept="application/json,.json" required> ';
			submit_button( __( 'Restore them', 'users-dlx-plus' ), 'secondary', 'submit', false );
			echo '</p>';
		},
		true
	);

	users_dlx_plus_screen_close();
}
