<?php
/**
 * La pantalla de campos: un listado y, aparte, la ficha de cada campo.
 *
 * Es el corazón del plugin —qué se le pide a la gente— y por eso no es un
 * acordeón de formularios apilados: se ve la lista de un vistazo y se entra a
 * editar uno solo. Es como funciona cualquier otra lista de WordPress.
 *
 * La clave (`users_dlx_plus_algo`) se propone sola a partir del nombre y después no se
 * puede cambiar: es el nombre con el que el dato quedó guardado en cada
 * persona, y renombrarla sería perder lo que ya está cargado.
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;

/** Convierte un nombre en una clave válida y única. */
/**
 * @param array<string, mixed> $used
 */
function users_dlx_plus_key_from( string $label, array $used ): string {
	$base = sanitize_key( remove_accents( $label ) );
	$base = 'users_dlx_plus_' . ( '' === $base ? 'field' : $base );

	$key = $base;
	$n   = 2;

	while ( in_array( $key, $used, true ) ) {
		$key = $base . '_' . $n;
		++$n;
	}

	return $key;
}

/**
 * De dónde salen las «opciones» de un campo, que cambia según el tipo.
 *
 * En una lista cerrada son los valores; en un país, los códigos que van
 * arriba; en un teléfono, el país por defecto. En el resto, nada: un campo de
 * fecha no tiene opciones y no tiene sentido mostrarle la caja.
 *
 * @param array<string, mixed> $input
 * @return array<int, string>
 */
function users_dlx_plus_field_options_from( string $type, array $input ): array {
	switch ( $type ) {
		case 'select':
		case 'datalist':
			return array_map( 'trim', explode( "\n", (string) ( $input['options'] ?? '' ) ) );

		case 'country':
			return array_map( 'strval', (array) ( $input['preferred'] ?? array() ) );

		case 'phone':
			$default = (string) ( $input['default_country'] ?? '' );

			return '' === $default ? array() : array( $default );

		default:
			return array();
	}
}

/** Guarda un campo (nuevo o existente) y devuelve su clave. */
/**
 * @param array<string, mixed> $input
 */
function users_dlx_plus_field_save( array $input ): string {
	$fields = users_dlx_plus_fields( '', false );
	$key    = sanitize_key( (string) ( $input['key'] ?? '' ) );
	$label  = sanitize_text_field( (string) ( $input['label'] ?? '' ) );

	if ( '' === $label ) {
		return '';
	}

	if ( '' === $key ) {
		$key = users_dlx_plus_key_from( $label, wp_list_pluck( $fields, 'key' ) );
	}

	$field = users_dlx_plus_normalize_field(
		array(
			'key'         => $key,
			'label'       => $label,
			'type'        => (string) ( $input['type'] ?? 'text' ),
			'edit'        => sanitize_key( (string) ( $input['edit'] ?? 'always' ) ),
			'edit_max'    => (int) ( $input['edit_max'] ?? 1 ),
			'help'        => (string) ( $input['help'] ?? '' ),
			'placeholder' => (string) ( $input['placeholder'] ?? '' ),
			'options'     => users_dlx_plus_field_options_from( (string) ( $input['type'] ?? 'text' ), $input ),
			'required'    => ! empty( $input['required'] ),
			'group'       => (string) ( $input['group'] ?? 'optional' ),
			'active'      => ! empty( $input['active'] ),
		)
	);

	$reemplazado = false;

	foreach ( $fields as $i => $existing ) {
		if ( $existing['key'] === $key ) {
			$fields[ $i ] = $field;
			$reemplazado  = true;
			break;
		}
	}

	if ( ! $reemplazado ) {
		$fields[] = $field;
	}

	update_option( 'users_dlx_plus_fields', $fields );

	return $key;
}

/**
 * Borra la definición de un campo.
 *
 * Los valores que la gente ya cargó NO se tocan: si mañana el campo vuelve,
 * vuelven con él. Borrar 25.000 filas por un clic en una pantalla de ajustes
 * sería una sorpresa cara.
 */
function users_dlx_plus_field_delete( string $key ): void {
	$fields = array_values(
		array_filter(
			users_dlx_plus_fields( '', false ),
			static fn( array $f ): bool => $f['key'] !== $key
		)
	);

	update_option( 'users_dlx_plus_fields', $fields );
}

/**
 * Guardar, borrar o mover un campo.
 *
 * En admin_init, no dentro de la pantalla: para cuando WordPress llama al
 * callback de una página del admin ya imprimió la cabecera, y el redirect
 * termina en un aviso de «headers already sent» en el log.
 */
function users_dlx_plus_fields_actions(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'users-dlx-plus-fields' !== sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['users_dlx_plus_field_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['users_dlx_plus_field_nonce'] ) ), 'users_dlx_plus_field' ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verificado arriba; lo sanea campo por campo users_dlx_plus_field_save().
		$key = users_dlx_plus_field_save( (array) wp_unslash( $_POST['users_dlx_plus_field'] ?? array() ) );

		wp_safe_redirect( users_dlx_plus_admin_url( 'users-dlx-plus-fields', array( 'users_dlx_plus_done' => '' === $key ? 'nolabel' : 'saved' ) ) );
		exit;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['users_dlx_plus_action'], $_GET['field'] ) ) {
		return;
	}

	check_admin_referer( 'users_dlx_plus_field_action' );

	$key    = sanitize_key( wp_unslash( $_GET['field'] ) );
	$accion = sanitize_key( wp_unslash( $_GET['users_dlx_plus_action'] ) );

	if ( 'delete' === $accion && ! users_dlx_plus_field_is_native( $key ) ) {
		users_dlx_plus_field_delete( $key );
	} elseif ( 'up' === $accion ) {
		users_dlx_plus_field_move( $key, -1 );
	} elseif ( 'down' === $accion ) {
		users_dlx_plus_field_move( $key, 1 );
	}

	wp_safe_redirect( users_dlx_plus_admin_url( 'users-dlx-plus-fields', array( 'users_dlx_plus_done' => $accion ) ) );
	exit;
}
add_action( 'admin_init', 'users_dlx_plus_fields_actions' );

/** Sube o baja un campo en el orden. */
function users_dlx_plus_field_move( string $key, int $dir ): void {
	$fields = users_dlx_plus_fields( '', false );
	$keys   = wp_list_pluck( $fields, 'key' );
	$i      = array_search( $key, $keys, true );

	if ( false === $i ) {
		return;
	}

	$j = (int) $i + $dir;

	if ( $j < 0 || $j >= count( $fields ) ) {
		return;
	}

	[ $fields[ $i ], $fields[ $j ] ] = array( $fields[ $j ], $fields[ $i ] );

	update_option( 'users_dlx_plus_fields', $fields );
}

/* ── La pantalla ───────────────────────────────────────────────────── */

/** La pantalla de campos: el listado, o la ficha de uno. */
function users_dlx_plus_screen_fields(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$editing = isset( $_GET['field'] ) ? sanitize_key( wp_unslash( $_GET['field'] ) ) : '';

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$done = isset( $_GET['users_dlx_plus_done'] ) ? sanitize_key( wp_unslash( $_GET['users_dlx_plus_done'] ) ) : '';

	if ( '' !== $editing || isset( $_GET['users_dlx_plus_new'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		users_dlx_plus_screen_field_edit( $editing );
		return;
	}

	$tabs    = array(
		'list'  => __( 'Fields', 'users-dlx-plus' ),
		'usage' => __( 'How to use them', 'users-dlx-plus' ),
	);
	$current = users_dlx_plus_tab( $tabs );

	users_dlx_plus_screen_open( __( 'User fields', 'users-dlx-plus' ), 'users-dlx-plus-fields', $tabs, $current );

	$avisos = array(
		'saved'   => __( 'Field saved.', 'users-dlx-plus' ),
		'delete'  => __( 'Field deleted. The data already stored was left alone.', 'users-dlx-plus' ),
		'nolabel' => __( 'A field needs a name.', 'users-dlx-plus' ),
	);

	if ( isset( $avisos[ $done ] ) ) {
		users_dlx_plus_notice( $avisos[ $done ], 'nolabel' === $done ? 'error' : 'success' );
	}

	if ( 'usage' === $current ) {
		users_dlx_plus_screen_fields_usage();
	} else {
		users_dlx_plus_screen_fields_list();
	}

	users_dlx_plus_screen_close();
}

/** Screen fields list. */
function users_dlx_plus_screen_fields_list(): void {
	$fields = users_dlx_plus_fields( '', false );
	$types  = users_dlx_plus_field_types();
	$groups = users_dlx_plus_groups();
	?>
	<div class="users-dlx-plus-donde">
		<p><strong><?php esc_html_e( 'Where all this lives', 'users-dlx-plus' ); ?></strong></p>
		<p>
			<?php
			printf(
				/* translators: 1: nombre de la option, 2: nombre de la tabla */
				esc_html__( 'What each field IS —its name, type and behaviour— is one WordPress option, %1$s. What each PERSON answered is user meta: one row per person and per field in %2$s, with the field key as the name. No extra tables.', 'users-dlx-plus' ),
				'<code>users_dlx_plus_fields</code>',
				'<code>' . esc_html( $GLOBALS['wpdb']->usermeta ) . '</code>'
			);
			?>
			<?php esc_html_e( 'That is why the key cannot change once the field exists, and why deleting a field leaves the answers alone: they are two different things.', 'users-dlx-plus' ); ?>
		</p>
	</div>

	<p class="users-dlx-plus-admin__acciones">
		<a class="button button-primary" href="<?php echo esc_url( users_dlx_plus_admin_url( 'users-dlx-plus-fields', array( 'users_dlx_plus_new' => 1 ) ) ); ?>">
			<?php esc_html_e( 'Add field', 'users-dlx-plus' ); ?>
		</a>
	</p>

	<table class="wp-list-table widefat fixed striped users-dlx-plus-list">
		<thead>
			<tr>
				<th class="users-dlx-plus-list__name"><?php esc_html_e( 'Name', 'users-dlx-plus' ); ?></th>
				<th><?php esc_html_e( 'Type', 'users-dlx-plus' ); ?></th>
				<th><?php esc_html_e( 'Where', 'users-dlx-plus' ); ?></th>
				<th><?php esc_html_e( 'Required', 'users-dlx-plus' ); ?></th>
				<th><?php esc_html_e( 'Status', 'users-dlx-plus' ); ?></th>
				<th class="users-dlx-plus-list__order"><?php esc_html_e( 'Order', 'users-dlx-plus' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( array() === $fields ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No fields yet.', 'users-dlx-plus' ); ?></td></tr>
			<?php endif; ?>

			<?php
			foreach ( $fields as $field ) :
				$edit = users_dlx_plus_admin_url( 'users-dlx-plus-fields', array( 'field' => $field['key'] ) );
				?>
				<tr>
					<td class="users-dlx-plus-list__name">
						<strong><a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $field['label'] ); ?></a></strong>
						<code><?php echo esc_html( $field['key'] ); ?></code>
						<div class="row-actions">
							<span class="edit"><a href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Edit', 'users-dlx-plus' ); ?></a></span>

							<?php
							/*
							 * Los de WordPress no se borran: el dato existe igual y lo
							 * usan el escritorio y medio plugin del sitio. Se esconden.
							 */
							?>
							<?php if ( ! users_dlx_plus_field_is_native( $field['key'] ) ) : ?>
								<span class="trash"> |
									<a class="users-dlx-plus-danger"
										href="
										<?php
										echo esc_url(
											wp_nonce_url(
												users_dlx_plus_admin_url(
													'users-dlx-plus-fields',
													array(
														'field' => $field['key'],
														'users_dlx_plus_action' => 'delete',
													)
												),
												'users_dlx_plus_field_action'
											)
										);
										?>
												"
										onclick="return confirm(<?php echo esc_attr( (string) wp_json_encode( __( 'Delete this field? The data already stored is kept.', 'users-dlx-plus' ) ) ); ?>);">
										<?php esc_html_e( 'Delete', 'users-dlx-plus' ); ?>
									</a>
								</span>
							<?php endif; ?>
						</div>
					</td>
					<td><?php echo esc_html( $types[ $field['type'] ] ?? $field['type'] ); ?></td>
					<td><?php echo esc_html( $groups[ $field['group'] ] ?? $field['group'] ); ?></td>
					<td><?php echo $field['required'] ? esc_html__( 'Yes', 'users-dlx-plus' ) : '—'; ?></td>
					<td>
						<?php
						if ( 'never' === $field['edit'] ) {
							esc_html_e( 'Read only', 'users-dlx-plus' );
						} elseif ( 'limited' === $field['edit'] ) {
							printf(
								/* translators: %d: cuántas veces se puede cambiar */
								esc_html( _n( '%d time', '%d times', (int) $field['edit_max'], 'users-dlx-plus' ) ),
								(int) $field['edit_max']
							);
						} else {
							echo '&mdash;';
						}
						?>
					</td>
					<td>
						<span class="users-dlx-plus-pill users-dlx-plus-pill--<?php echo $field['active'] ? 'on' : 'off'; ?>">
							<?php echo $field['active'] ? esc_html__( 'Active', 'users-dlx-plus' ) : esc_html__( 'Hidden', 'users-dlx-plus' ); ?>
						</span>
					</td>
					<td class="users-dlx-plus-list__order">
						<a class="button button-small" href="
						<?php
						echo esc_url(
							wp_nonce_url(
								users_dlx_plus_admin_url(
									'users-dlx-plus-fields',
									array(
										'field' => $field['key'],
										'users_dlx_plus_action' => 'up',
									)
								),
								'users_dlx_plus_field_action'
							)
						);
						?>
																" aria-label="<?php esc_attr_e( 'Move up', 'users-dlx-plus' ); ?>">&uarr;</a>
						<a class="button button-small" href="
						<?php
						echo esc_url(
							wp_nonce_url(
								users_dlx_plus_admin_url(
									'users-dlx-plus-fields',
									array(
										'field' => $field['key'],
										'users_dlx_plus_action' => 'down',
									)
								),
								'users_dlx_plus_field_action'
							)
						);
						?>
																" aria-label="<?php esc_attr_e( 'Move down', 'users-dlx-plus' ); ?>">&darr;</a>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/** La ficha de un campo. Con clave vacía, es uno nuevo. */
function users_dlx_plus_screen_field_edit( string $key ): void {
	$field = '' === $key ? users_dlx_plus_normalize_field(
		array(
			'group'  => 'optional',
			'active' => 1,
		)
	) : users_dlx_plus_field( $key );

	if ( null === $field ) {
		users_dlx_plus_screen_open( __( 'User fields', 'users-dlx-plus' ) );
		users_dlx_plus_notice( __( 'That field does not exist.', 'users-dlx-plus' ), 'error' );
		users_dlx_plus_screen_close();
		return;
	}

	$nuevo = '' === $key;

	users_dlx_plus_screen_open(
		$nuevo
		? __( 'New field', 'users-dlx-plus' )
		: sprintf( /* translators: %s: nombre del campo */ __( 'Field: %s', 'users-dlx-plus' ), $field['label'] )
	);
	?>
	<p><a href="<?php echo esc_url( users_dlx_plus_admin_url( 'users-dlx-plus-fields' ) ); ?>">&larr; <?php esc_html_e( 'Back to the list', 'users-dlx-plus' ); ?></a></p>

	<form method="post" class="users-dlx-plus-form-admin">
		<?php wp_nonce_field( 'users_dlx_plus_field', 'users_dlx_plus_field_nonce' ); ?>
		<input type="hidden" name="users_dlx_plus_field[key]" value="<?php echo esc_attr( $field['key'] ); ?>">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="users-dlx-plus-label"><?php esc_html_e( 'Name', 'users-dlx-plus' ); ?></label></th>
				<td>
					<input type="text" id="users-dlx-plus-label" name="users_dlx_plus_field[label]" class="regular-text" value="<?php echo esc_attr( $field['label'] ); ?>" required>
					<?php if ( ! $nuevo ) : ?>
						<p class="description"><?php esc_html_e( 'Key:', 'users-dlx-plus' ); ?> <code><?php echo esc_html( $field['key'] ); ?></code> — <?php esc_html_e( 'it cannot change: it is the name the data is stored under.', 'users-dlx-plus' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'The key is generated from the name.', 'users-dlx-plus' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="users-dlx-plus-type"><?php esc_html_e( 'Type', 'users-dlx-plus' ); ?></label></th>
				<td>
					<?php
					/*
					 * Los de WordPress vienen con su tipo puesto: cambiarle el
					 * tipo al nombre no lo mejora, y lo puede romper.
					 */
					?>
					<select id="users-dlx-plus-type" name="users_dlx_plus_field[type]" <?php disabled( users_dlx_plus_field_is_native( $field['key'] ) ); ?>>
						<?php foreach ( users_dlx_plus_field_types() as $value => $name ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $field['type'], $value ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>

					<?php if ( users_dlx_plus_field_is_native( $field['key'] ) ) : ?>
						<input type="hidden" name="users_dlx_plus_field[type]" value="<?php echo esc_attr( $field['type'] ); ?>">
						<p class="description"><?php esc_html_e( 'This one is WordPress’s own: it can be renamed, reordered, made required or hidden, but it keeps its type and cannot be deleted.', 'users-dlx-plus' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( '“Country” shows the full list with its dial codes; “Phone with country code” splits the number in two and stores it in international format.', 'users-dlx-plus' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Who can change it', 'users-dlx-plus' ); ?></th>
				<td>
					<?php
					$modos = array(
						'always'  => __( 'Whenever they want', 'users-dlx-plus' ),
						'limited' => __( 'Only a few times, and then no more', 'users-dlx-plus' ),
						'never'   => __( 'Never — they can see it, only an administrator changes it', 'users-dlx-plus' ),
					);

					foreach ( $modos as $clave => $rotulo ) :
						?>
						<label class="users-dlx-plus-roles__item">
							<input type="radio" name="users_dlx_plus_field[edit]" value="<?php echo esc_attr( $clave ); ?>" <?php checked( $field['edit'], $clave ); ?>>
							<?php echo esc_html( $rotulo ); ?>
						</label>
					<?php endforeach; ?>

					<p class="users-dlx-plus-si-tipo-edit">
						<label for="users-dlx-plus-edit-max"><?php esc_html_e( 'How many times', 'users-dlx-plus' ); ?></label>
						<input type="number" id="users-dlx-plus-edit-max" name="users_dlx_plus_field[edit_max]" min="1" max="99" class="small-text" value="<?php echo esc_attr( (string) $field['edit_max'] ); ?>">
					</p>

					<p class="description"><?php esc_html_e( 'This is about the person who owns the data. An administrator can always change it, from the user’s profile: otherwise a one-change field turns into a typo nobody can fix.', 'users-dlx-plus' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="users-dlx-plus-group"><?php esc_html_e( 'Where it goes', 'users-dlx-plus' ); ?></label></th>
				<td>
					<select id="users-dlx-plus-group" name="users_dlx_plus_field[group]">
						<?php foreach ( users_dlx_plus_groups() as $g => $g_label ) : ?>
							<option value="<?php echo esc_attr( $g ); ?>" <?php selected( $field['group'], $g ); ?>><?php echo esc_html( $g_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'The profile form shows two blocks: the essentials first, and underneath the optional ones with their own explanation. This decides which one the field lands in.', 'users-dlx-plus' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="users-dlx-plus-help"><?php esc_html_e( 'Help text', 'users-dlx-plus' ); ?></label></th>
				<td>
					<input type="text" id="users-dlx-plus-help" name="users_dlx_plus_field[help]" class="large-text" value="<?php echo esc_attr( $field['help'] ); ?>">
					<p class="description"><?php esc_html_e( 'Why we are asking. Shown under the field.', 'users-dlx-plus' ); ?></p>
				</td>
			</tr>
			<tr class="users-dlx-plus-si-tipo" data-tipo="text textarea email url number datalist phone">
				<th scope="row"><label for="users-dlx-plus-placeholder"><?php esc_html_e( 'Placeholder text', 'users-dlx-plus' ); ?></label></th>
				<td>
					<input type="text" id="users-dlx-plus-placeholder" name="users_dlx_plus_field[placeholder]" class="regular-text" value="<?php echo esc_attr( $field['placeholder'] ); ?>">
					<p class="description"><?php esc_html_e( 'Shown in grey inside the empty field.', 'users-dlx-plus' ); ?></p>
				</td>
			</tr>
			<tr class="users-dlx-plus-si-tipo" data-tipo="select datalist">
				<th scope="row"><label for="users-dlx-plus-options"><?php esc_html_e( 'Values', 'users-dlx-plus' ); ?></label></th>
				<td>
					<textarea id="users-dlx-plus-options" name="users_dlx_plus_field[options]" class="large-text code" rows="5"><?php echo esc_textarea( implode( "\n", 'country' === $field['type'] || 'phone' === $field['type'] ? array() : $field['options'] ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One per line. In a fixed list they are the only accepted values; in text with suggestions they are just hints and the person can write something else.', 'users-dlx-plus' ); ?></p>
				</td>
			</tr>

			<tr class="users-dlx-plus-si-tipo" data-tipo="country">
				<th scope="row"><label for="users-dlx-plus-preferred"><?php esc_html_e( 'Countries shown first', 'users-dlx-plus' ); ?></label></th>
				<td>
					<select id="users-dlx-plus-preferred" name="users_dlx_plus_field[preferred][]" multiple size="8" class="users-dlx-plus-multi">
						<?php foreach ( users_dlx_plus_countries_sorted() as $iso => $country_name ) : ?>
							<option value="<?php echo esc_attr( $iso ); ?>" <?php selected( in_array( $iso, $field['options'], true ) ); ?>>
								<?php echo esc_html( $country_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php
						printf(
							/* translators: %d: cantidad de países */
							esc_html__( 'Optional. The list of %d countries comes with the plugin — there is nothing to load. These ones go on top, separated from the rest, so nobody has to scroll to find the one next door.', 'users-dlx-plus' ),
							count( users_dlx_plus_countries() )
						);
						?>
					</p>
				</td>
			</tr>

			<tr class="users-dlx-plus-si-tipo" data-tipo="phone">
				<th scope="row"><label for="users-dlx-plus-default-country"><?php esc_html_e( 'Country selected by default', 'users-dlx-plus' ); ?></label></th>
				<td>
					<select id="users-dlx-plus-default-country" name="users_dlx_plus_field[default_country]">
						<option value=""><?php esc_html_e( '— None —', 'users-dlx-plus' ); ?></option>
						<?php foreach ( users_dlx_plus_countries_sorted() as $iso => $country_name ) : ?>
							<option value="<?php echo esc_attr( $iso ); ?>" <?php selected( ( $field['options'][0] ?? '' ), $iso ); ?>>
								<?php echo esc_html( $country_name . ' +' . users_dlx_plus_country_dial( $iso ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'The dial code the field comes with. The person can change it: the full list is always there.', 'users-dlx-plus' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Behaviour', 'users-dlx-plus' ); ?></th>
				<td>
					<label><input type="checkbox" name="users_dlx_plus_field[required]" value="1" <?php checked( $field['required'], 1 ); ?>> <?php esc_html_e( 'Required', 'users-dlx-plus' ); ?></label><br>
					<label><input type="checkbox" name="users_dlx_plus_field[active]" value="1" <?php checked( $field['active'], 1 ); ?>> <?php esc_html_e( 'Active: show it in the forms', 'users-dlx-plus' ); ?></label>
				</td>
			</tr>
		</table>

		<?php submit_button( $nuevo ? __( 'Add field', 'users-dlx-plus' ) : __( 'Save field', 'users-dlx-plus' ) ); ?>
	</form>
	<?php
	users_dlx_plus_screen_close();
}

/** Screen fields usage. */
function users_dlx_plus_screen_fields_usage(): void {
	users_dlx_plus_intro( __( 'The fields show up on their own in the dashboard profile, when adding a user and in the WordPress registration form. On the front end you place them with a shortcode.', 'users-dlx-plus' ) );
	?>
	<table class="widefat striped users-dlx-plus-shortcodes">
		<tbody>
			<tr><td><code>[users_dlx_plus_fields]</code></td><td><?php esc_html_e( 'Every field, for the person to edit.', 'users-dlx-plus' ); ?></td></tr>
			<tr><td><code>[users_dlx_plus_fields group="basic"]</code></td><td><?php esc_html_e( 'Only the basic ones. With group="optional", only the others.', 'users-dlx-plus' ); ?></td></tr>
			<tr><td><code>[users_dlx_plus_login]</code></td><td><?php esc_html_e( 'The email sign-in form and the social buttons.', 'users-dlx-plus' ); ?></td></tr>
			<tr><td><code>[users_dlx_plus_accounts]</code></td><td><?php esc_html_e( 'Linked providers, to link or unlink.', 'users-dlx-plus' ); ?></td></tr>
			<tr><td><code>[users_dlx_plus_sessions]</code></td><td><?php esc_html_e( 'Open sessions, with the button to close them.', 'users-dlx-plus' ); ?></td></tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Fitting them into your design', 'users-dlx-plus' ); ?></h2>
	<p class="users-dlx-plus-admin__intro">
		<?php esc_html_e( 'Copy any file from the plugin’s templates/ folder into your theme, inside a users-dlx-plus/ folder, and edit it there. The plugin will use yours. You can also turn off its stylesheet in Sign in → Presentation.', 'users-dlx-plus' ); ?>
	</p>
	<?php
}
