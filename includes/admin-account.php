<?php
/**
 * La pantalla del área de cuenta: qué solapas hay y cómo se ordenan.
 *
 * Todo lo que se ve en «mi cuenta» se administra desde acá: las que trae el
 * plugin, las que agregan otros plugins (LifterLMS y compañía) y las que
 * agrega el sitio con su propio texto o el shortcode de otro plugin. Se
 * prenden, se apagan, se renombran y se ordenan sin tocar una línea de código.
 *
 * Las de código se pueden apagar pero no borrar: el código que las pinta sigue
 * ahí, y borrarlas de la option las haría volver en el próximo pedido.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

/** Guarda la configuración de una sección, mezclándola con la que ya había. */
function upfw_section_config_save( string $id, array $config ): void {
	$all = (array) upfw_option( 'upfw_account_sections' );

	$all[ $id ] = array_merge( (array) ( $all[ $id ] ?? array() ), $config );

	update_option( 'upfw_account_sections', $all );
}

/** Saca una sección propia. Las de código no se tocan. */
function upfw_section_delete( string $id ): void {
	$all = (array) upfw_option( 'upfw_account_sections' );

	if ( empty( $all[ $id ]['custom'] ) ) {
		return;
	}

	unset( $all[ $id ] );

	update_option( 'upfw_account_sections', $all );
}

/**
 * Reordena las secciones con la lista que llegó del arrastre.
 *
 * Se reescriben todas las posiciones de una, con huecos de 10, en vez de
 * tocar sólo las que se movieron: si dos secciones registradas comparten
 * posición —cosa que pasa cuando un plugin no la declara— reacomodar sólo un
 * par no movería nada y el arrastre parecería roto.
 *
 * @param array<int, string> $ids
 */
function upfw_section_reorder( array $ids ): void {
	$conocidas = upfw_sections( true );
	$orden     = 0;

	foreach ( $ids as $id ) {
		if ( ! isset( $conocidas[ $id ] ) ) {
			continue;
		}

		++$orden;
		upfw_section_config_save( $id, array( 'position' => $orden * 10 ) );
	}
}

/**
 * Guarda una sección desde el formulario del detalle.
 *
 * Sirve para las dos cosas —crear y editar— porque son la misma: una sección
 * es un nombre, una dirección y algo para mostrar. Devuelve el identificador,
 * o '' si no se pudo.
 *
 * @param array<string, mixed> $input
 */
function upfw_section_save( array $input ): string {
	$id     = sanitize_key( (string) ( $input['id'] ?? '' ) );
	$existe = upfw_sections( true );
	$nueva  = '' === $id || ! isset( $existe[ $id ] );
	$label  = sanitize_text_field( (string) ( $input['label'] ?? '' ) );

	if ( '' === $label ) {
		return '';
	}

	$slug = sanitize_title( (string) ( $input['slug'] ?? '' ) );
	$slug = '' === $slug ? sanitize_title( $label ) : $slug;

	if ( $nueva ) {
		$id = '' === $id ? $slug : $id;

		// Una sección propia no puede pisar a una de código: quedarían dos con
		// la misma dirección y ganaría cualquiera.
		if ( '' === $id || isset( $existe[ $id ] ) ) {
			return '';
		}
	}

	$config = array(
		'label'     => $label,
		'slug'      => $slug,
		'content'   => wp_kses_post( (string) ( $input['content'] ?? '' ) ),
		'placement' => in_array( $input['placement'] ?? '', array( 'before', 'after', 'replace' ), true )
			? (string) $input['placement']
			: 'after',
		'roles'     => array_values( array_filter( array_map( 'sanitize_key', (array) ( $input['roles'] ?? array() ) ) ) ),
	);

	if ( $nueva ) {
		$config['custom']   = true;
		$config['enabled']  = 1;
		$config['position'] = 900;
	}

	upfw_section_config_save( $id, $config );

	return $id;
}

/**
 * Prender, apagar, mover o borrar una sección.
 *
 * Va en admin_init y no dentro de la pantalla: cuando WordPress llama al
 * callback de una página del admin ya imprimió la cabecera, y ahí un
 * wp_safe_redirect() no puede hacer nada más que un aviso de «headers already
 * sent» en el log.
 */
function upfw_account_actions(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'upfw-account' !== ( $_GET['page'] ?? '' ) || ! isset( $_GET['upfw_action'], $_GET['section'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	check_admin_referer( 'upfw_section_action' );

	$id     = sanitize_key( wp_unslash( $_GET['section'] ) );
	$accion = sanitize_key( wp_unslash( $_GET['upfw_action'] ) );

	$volver = array( 'section' => $id );

	if ( 'delete' === $accion ) {
		upfw_section_delete( $id );
		$volver = array();
	} elseif ( 'on' === $accion || 'off' === $accion ) {
		upfw_section_config_save( $id, array( 'enabled' => 'on' === $accion ? 1 : 0 ) );
	}

	// Se vuelve a la misma sección: quien prende una y la pantalla lo devuelve
	// a la primera de la lista tiene que buscarla de nuevo cada vez.
	wp_safe_redirect( upfw_admin_url( 'upfw-account', $volver ) );
	exit;
}
add_action( 'admin_init', 'upfw_account_actions' );

function upfw_screen_account(): void {
	$tabs = array(
		'sections' => __( 'Sections', 'users-plus-for-wordpress' ),
		'layout'   => __( 'Where it lives', 'users-plus-for-wordpress' ),
	);

	$current = upfw_tab( $tabs );

	upfw_account_notice();
	upfw_screen_open( __( 'Account area', 'users-plus-for-wordpress' ), 'upfw-account', $tabs, $current );

	if ( 'layout' === $current ) {
		upfw_screen_account_layout();
	} else {
		upfw_screen_account_sections();
	}

	upfw_screen_close();
}

/**
 * Guarda lo que se mandó desde la pantalla.
 *
 * Va en admin_init, igual que las acciones de un clic, para poder redirigir:
 * cuando WordPress llama al callback de una página ya imprimió la cabecera.
 * Y redirigir hace falta —no es sólo higiene— porque después de crear una
 * sección hay que abrirla, y porque recargar no tiene que volver a mandar el
 * formulario.
 */
function upfw_account_post(): void {
	// phpcs:disable WordPress.Security.NonceVerification -- cada rama verifica el suyo.
	if ( 'upfw-account' !== ( $_GET['page'] ?? '' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$abierta = sanitize_key( wp_unslash( $_GET['section'] ?? '' ) );

	if ( isset( $_POST['upfw_orden_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['upfw_orden_nonce'] ) ), 'upfw_orden' ) ) {
		upfw_section_reorder( array_map( 'sanitize_key', (array) wp_unslash( $_POST['upfw_orden'] ?? array() ) ) );

		upfw_account_back( 'orden', $abierta );
	}

	if ( isset( $_POST['upfw_seccion_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['upfw_seccion_nonce'] ) ), 'upfw_seccion' ) ) {
		$guardada = upfw_section_save( (array) wp_unslash( $_POST['upfw_seccion'] ?? array() ) );

		upfw_account_back(
			'' === $guardada ? 'error' : 'guardada',
			'' === $guardada ? $abierta : $guardada
		);
	}

	if ( isset( $_POST['upfw_layout_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['upfw_layout_nonce'] ) ), 'upfw_layout' ) ) {
		upfw_save_options(
			array(
				'upfw_account_page'   => (int) ( $_POST['upfw_account_page'] ?? 0 ),
				'upfw_account_layout' => sanitize_key( wp_unslash( $_POST['upfw_account_layout'] ?? 'tabs' ) ),
				'upfw_account_header' => isset( $_POST['upfw_account_header'] ) ? 1 : 0,
			)
		);

		// La página cambió: las reglas de /cuenta/<seccion>/ hay que rehacerlas.
		delete_option( 'upfw_rewrite_version' );

		wp_safe_redirect(
			upfw_admin_url(
				'upfw-account',
				array(
					'tab'      => 'layout',
					'upfw_msg' => 'guardada',
				)
			)
		);
		exit;
	}
	// phpcs:enable
}
add_action( 'admin_init', 'upfw_account_post' );

/** Vuelve a la pantalla, en la sección que corresponda, con el aviso puesto. */
function upfw_account_back( string $msg, string $section = '' ): void {
	$args = array( 'upfw_msg' => $msg );

	if ( '' !== $section ) {
		$args['section'] = $section;
	}

	wp_safe_redirect( upfw_admin_url( 'upfw-account', $args ) );
	exit;
}

/** El aviso de lo que acaba de pasar. */
function upfw_account_notice(): void {
	$avisos = array(
		'orden'    => array( 'success', __( 'New order saved.', 'users-plus-for-wordpress' ) ),
		'guardada' => array( 'success', __( 'Section saved.', 'users-plus-for-wordpress' ) ),
		'borrada'  => array( 'success', __( 'Section removed.', 'users-plus-for-wordpress' ) ),
		'error'    => array( 'error', __( 'That section needs a name, and an address that is not taken.', 'users-plus-for-wordpress' ) ),
	);

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige el mensaje.
	$msg = isset( $_GET['upfw_msg'] ) ? sanitize_key( wp_unslash( $_GET['upfw_msg'] ) ) : '';

	if ( isset( $avisos[ $msg ] ) ) {
		upfw_notice( $avisos[ $msg ][1], $avisos[ $msg ][0] );
	}
}

/**
 * Las secciones: la lista a la izquierda y el detalle de una a la derecha.
 *
 * Es una pantalla de dos paneles y no una tabla porque son dos cosas
 * distintas: el orden, que se ve de un vistazo y se arrastra, y lo que una
 * sección es, que son ocho campos y un editor. Meter los ocho campos adentro
 * de una fila deformaba la tabla y escondía el orden.
 */
function upfw_screen_account_sections(): void {
	$sections = upfw_sections( true );
	$page     = upfw_account_page_id();
	$actual   = upfw_screen_account_current( $sections );
	?>
	<?php if ( $page <= 0 ) : ?>
		<div class="notice notice-warning inline upfw-estado-caja">
			<p><strong><?php esc_html_e( 'There is no account page yet', 'users-plus-for-wordpress' ); ?></strong></p>
			<p><?php esc_html_e( 'Pick the page that has the [upfw_account] shortcode, under “Where it lives”. Until then the links below go nowhere.', 'users-plus-for-wordpress' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="upfw-endpoints">
		<div class="upfw-endpoints__cabeza">
			<h2><?php esc_html_e( 'The sections of “my account”', 'users-plus-for-wordpress' ); ?></h2>
			<a class="button button-primary" href="<?php echo esc_url( upfw_admin_url( 'upfw-account', array( 'section' => 'upfw-new' ) ) ); ?>"><?php esc_html_e( 'Add section', 'users-plus-for-wordpress' ); ?></a>
		</div>

		<div class="upfw-endpoints__cuerpo">
			<form class="upfw-endpoints__orden" method="post">
				<?php wp_nonce_field( 'upfw_orden', 'upfw_orden_nonce' ); ?>

				<ul class="upfw-endpoints__lista" data-upfw-sortable>
					<?php foreach ( $sections as $id => $section ) : ?>
						<li class="upfw-endpoint <?php echo $id === $actual ? 'is-current' : ''; ?> <?php echo $section['enabled'] && upfw_section_available( $section ) ? '' : 'is-off'; ?>">
							<input type="hidden" name="upfw_orden[]" value="<?php echo esc_attr( $id ); ?>">
							<a class="upfw-endpoint__nombre" href="<?php echo esc_url( upfw_admin_url( 'upfw-account', array( 'section' => $id ) ) ); ?>"><?php echo esc_html( $section['label'] ); ?></a>
							<span class="upfw-endpoint__agarre" aria-hidden="true"></span>
						</li>
					<?php endforeach; ?>
				</ul>

				<?php
				/*
				Sin JavaScript no hay arrastre, así que queda el botón:
						una pantalla que sólo se puede usar con arrastre no se
						puede usar con el teclado. */
				?>
				<p class="upfw-endpoints__guardar-orden">
					<button type="submit" class="button"><?php esc_html_e( 'Save the order', 'users-plus-for-wordpress' ); ?></button>
				</p>
			</form>

			<div class="upfw-endpoints__detalle">
				<?php upfw_screen_account_section( $actual, $sections, $page ); ?>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Cuál sección se está mirando.
 *
 * Sin nada pedido, la primera: una pantalla de dos paneles con el derecho en
 * blanco parece rota.
 *
 * @param array<string, array<string, mixed>> $sections
 */
function upfw_screen_account_current( array $sections ): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige qué pintar.
	$pedida = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';

	if ( 'upfw-new' === $pedida || isset( $sections[ $pedida ] ) ) {
		return $pedida;
	}

	return (string) ( array_key_first( $sections ) ?? 'upfw-new' );
}

/**
 * El detalle de una sección.
 *
 * @param array<string, array<string, mixed>> $sections
 */
function upfw_screen_account_section( string $id, array $sections, int $page ): void {
	$nueva   = ! isset( $sections[ $id ] );
	$section = $nueva
		? array(
			'label'     => '',
			'slug'      => '',
			'content'   => '',
			'placement' => 'after',
			'roles'     => array(),
			'source'    => '',
			'custom'    => true,
			'enabled'   => true,
			'render'    => '',
			'available' => '',
			'why'       => '',
		)
		: $sections[ $id ];

	$con_codigo = ! $nueva && upfw_section_has_code( $section );
	?>
	<form method="post" class="upfw-endpoint-form">
		<?php wp_nonce_field( 'upfw_seccion', 'upfw_seccion_nonce' ); ?>
		<input type="hidden" name="upfw_seccion[id]" value="<?php echo esc_attr( $nueva ? '' : $id ); ?>">

		<div class="upfw-endpoint-form__cabeza">
			<h3><?php echo esc_html( $nueva ? __( 'New section', 'users-plus-for-wordpress' ) : $section['label'] ); ?></h3>

			<?php if ( ! $nueva ) : ?>
				<a class="upfw-toggle <?php echo $section['enabled'] ? 'is-on' : ''; ?>"
					href="
					<?php
					echo esc_url(
						wp_nonce_url(
							upfw_admin_url(
								'upfw-account',
								array(
									'section'     => $id,
									'upfw_action' => $section['enabled'] ? 'off' : 'on',
								)
							),
							'upfw_section_action'
						)
					);
					?>
							">
					<span class="upfw-toggle__perilla" aria-hidden="true"></span>
					<?php echo $section['enabled'] ? esc_html__( 'Showing', 'users-plus-for-wordpress' ) : esc_html__( 'Hidden', 'users-plus-for-wordpress' ); ?>
				</a>

				<?php if ( ! empty( $section['custom'] ) ) : ?>
					<a class="button upfw-danger"
						href="
						<?php
						echo esc_url(
							wp_nonce_url(
								upfw_admin_url(
									'upfw-account',
									array(
										'section'     => $id,
										'upfw_action' => 'delete',
									)
								),
								'upfw_section_action'
							)
						);
						?>
								"
						onclick="return confirm(<?php echo esc_attr( wp_json_encode( __( 'Delete this section?', 'users-plus-for-wordpress' ) ) ); ?>);">
						<?php esc_html_e( 'Remove', 'users-plus-for-wordpress' ); ?>
					</a>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<?php if ( ! $nueva && $section['enabled'] && ! upfw_section_available( $section ) ) : ?>
			<div class="notice notice-info inline upfw-estado-caja">
				<p><strong><?php esc_html_e( 'It is on, but it is not showing', 'users-plus-for-wordpress' ); ?></strong></p>
				<p><?php echo esc_html( (string) $section['why'] ); ?></p>
			</div>
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="upfw-seccion-label"><?php esc_html_e( 'Name', 'users-plus-for-wordpress' ); ?></label></th>
				<td>
					<input type="text" id="upfw-seccion-label" class="regular-text" name="upfw_seccion[label]" value="<?php echo esc_attr( $section['label'] ); ?>" required>
					<p class="description"><?php esc_html_e( 'What people read in the menu, and the title of the section.', 'users-plus-for-wordpress' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="upfw-seccion-slug"><?php esc_html_e( 'Address', 'users-plus-for-wordpress' ); ?></label></th>
				<td>
					<input type="text" id="upfw-seccion-slug" class="regular-text code" name="upfw_seccion[slug]" value="<?php echo esc_attr( $section['slug'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'made from the name', 'users-plus-for-wordpress' ); ?>">
					<?php if ( ! $nueva && $page > 0 ) : ?>
						<p class="description">
							<a href="<?php echo esc_url( upfw_account_url( $id ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( str_replace( home_url(), '', upfw_account_url( $id ) ) ); ?></a>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Who sees it', 'users-plus-for-wordpress' ); ?></th>
				<td>
					<?php $roles = (array) ( $section['roles'] ?? array() ); ?>
					<?php foreach ( wp_roles()->get_names() as $rol => $rotulo ) : ?>
						<label class="upfw-roles__item">
							<input type="checkbox" name="upfw_seccion[roles][]" value="<?php echo esc_attr( $rol ); ?>" <?php checked( in_array( $rol, $roles, true ) ); ?>>
							<?php echo esc_html( translate_user_role( $rotulo ) ); ?>
						</label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Tick nothing and everybody with an account sees it. Tick roles and only those do.', 'users-plus-for-wordpress' ); ?></p>
				</td>
			</tr>

			<?php if ( $con_codigo ) : ?>
				<tr>
					<th scope="row"><label for="upfw-seccion-placement"><?php esc_html_e( 'Where your content goes', 'users-plus-for-wordpress' ); ?></label></th>
					<td>
						<select id="upfw-seccion-placement" name="upfw_seccion[placement]">
							<?php
							$donde = array(
								'after'   => __( 'After what the plugin shows', 'users-plus-for-wordpress' ),
								'before'  => __( 'Before what the plugin shows', 'users-plus-for-wordpress' ),
								'replace' => __( 'Instead of it — your content replaces the section', 'users-plus-for-wordpress' ),
							);

							foreach ( $donde as $clave => $rotulo ) {
								printf(
									'<option value="%1$s"%2$s>%3$s</option>',
									esc_attr( $clave ),
									selected( $section['placement'], $clave, false ),
									esc_html( $rotulo )
								);
							}
							?>
						</select>
						<p class="description"><?php esc_html_e( 'This section is drawn by code. What you write below is added to it — unless you say it replaces it.', 'users-plus-for-wordpress' ); ?></p>
					</td>
				</tr>
			<?php else : ?>
				<input type="hidden" name="upfw_seccion[placement]" value="replace">
			<?php endif; ?>

			<tr>
				<th scope="row"><label for="upfw-seccion-contenido"><?php esc_html_e( 'Your content', 'users-plus-for-wordpress' ); ?></label></th>
				<td>
					<?php
					wp_editor(
						(string) $section['content'],
						'upfw-seccion-contenido',
						array(
							'textarea_name' => 'upfw_seccion[content]',
							'textarea_rows' => 10,
							'media_buttons' => true,
						)
					);
					?>
					<p class="description">
						<?php
						echo $con_codigo
							? esc_html__( 'Text, HTML, or the shortcode of another plugin. Leave it empty and the section stays as the plugin draws it.', 'users-plus-for-wordpress' )
							: esc_html__( 'Text, HTML, or the shortcode of another plugin — a course plugin, a membership, a support desk. This is the whole section.', 'users-plus-for-wordpress' );
						?>
					</p>
				</td>
			</tr>

			<?php if ( ! $nueva && '' !== (string) $section['source'] ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Comes from', 'users-plus-for-wordpress' ); ?></th>
					<td>
						<p><?php echo esc_html( $section['source'] ); ?> <code><?php echo esc_html( $id ); ?></code></p>
						<?php if ( empty( $section['custom'] ) ) : ?>
							<p class="description"><?php esc_html_e( 'It comes from code, so it cannot be deleted —the code that draws it is still there and it would come back— but it can be hidden.', 'users-plus-for-wordpress' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			<?php endif; ?>
		</table>

		<?php submit_button( $nueva ? __( 'Add section', 'users-plus-for-wordpress' ) : __( 'Save section', 'users-plus-for-wordpress' ) ); ?>
	</form>
	<?php
}

/** Dónde vive el área de cuenta y cómo se navega. */
function upfw_screen_account_layout(): void {
	upfw_intro( __( 'Which page is “my account”, and how people move between its sections.', 'users-plus-for-wordpress' ) );
	?>
	<form method="post">
		<?php wp_nonce_field( 'upfw_layout', 'upfw_layout_nonce' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="upfw_account_page"><?php esc_html_e( 'The account page', 'users-plus-for-wordpress' ); ?></label></th>
				<td>
					<?php
					wp_dropdown_pages(
						array(
							'name'              => 'upfw_account_page',
							'id'                => 'upfw_account_page',
							'selected'          => upfw_account_page_id(),
							'show_option_none'  => __( '— none —', 'users-plus-for-wordpress' ),
							'option_none_value' => 0,
						)
					);
					?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: el shortcode, literal */
							esc_html__( 'The page with %s in it. Declaring it here is what lets everything else —a certificate, a course, a forum— send people to the right place.', 'users-plus-for-wordpress' ),
							'<code>[upfw_account]</code>'
						);
						?>
					</p>
					<?php if ( '' === (string) get_option( 'permalink_structure' ) ) : ?>
						<p class="description"><?php esc_html_e( 'With plain permalinks the sections go as ?seccion=…; turn on pretty permalinks in Settings → Permalinks and they become /page/section/ on their own.', 'users-plus-for-wordpress' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'The menu', 'users-plus-for-wordpress' ); ?></th>
				<td>
					<?php upfw_forzado_aviso( 'upfw_account_layout' ); ?>

					<?php
					$layouts = array(
						'tabs' => __( 'Tabs across the top', 'users-plus-for-wordpress' ),
						'side' => __( 'A menu down the side', 'users-plus-for-wordpress' ),
						'none' => __( 'No menu — the site places it with [upfw_account_nav]', 'users-plus-for-wordpress' ),
					);

					foreach ( $layouts as $clave => $rotulo ) :
						?>
						<label class="upfw-roles__item">
							<input type="radio" name="upfw_account_layout" value="<?php echo esc_attr( $clave ); ?>" <?php checked( upfw_option( 'upfw_account_layout' ), $clave ); ?>>
							<?php echo esc_html( $rotulo ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'The header', 'users-plus-for-wordpress' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="upfw_account_header" value="1" <?php checked( upfw_option( 'upfw_account_header' ), 1 ); ?>>
						<?php esc_html_e( 'Show the name and the initials at the top', 'users-plus-for-wordpress' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
	<?php
}
