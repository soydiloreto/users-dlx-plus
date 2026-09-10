<?php
/**
 * La pantalla de campos: un listado y, aparte, la ficha de cada campo.
 *
 * Es el corazón del plugin —qué se le pide a la gente— y por eso no es un
 * acordeón de formularios apilados: se ve la lista de un vistazo y se entra a
 * editar uno solo. Es como funciona cualquier otra lista de WordPress.
 *
 * La clave (`upfw_algo`) se propone sola a partir del nombre y después no se
 * puede cambiar: es el nombre con el que el dato quedó guardado en cada
 * persona, y renombrarla sería perder lo que ya está cargado.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

/** Convierte un nombre en una clave válida y única. */
function upfw_key_from( string $label, array $used ): string {
	$base = sanitize_key( remove_accents( $label ) );
	$base = 'upfw_' . ( '' === $base ? 'field' : $base );

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
function upfw_field_options_from( string $type, array $input ): array {
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
function upfw_field_save( array $input ): string {
	$fields = upfw_fields( '', false );
	$key    = sanitize_key( (string) ( $input['key'] ?? '' ) );
	$label  = sanitize_text_field( (string) ( $input['label'] ?? '' ) );

	if ( '' === $label ) {
		return '';
	}

	if ( '' === $key ) {
		$key = upfw_key_from( $label, wp_list_pluck( $fields, 'key' ) );
	}

	$field = upfw_normalize_field(
		array(
			'key'         => $key,
			'label'       => $label,
			'type'        => (string) ( $input['type'] ?? 'text' ),
			'edit'        => sanitize_key( (string) ( $input['edit'] ?? 'always' ) ),
			'edit_max'    => (int) ( $input['edit_max'] ?? 1 ),
			'help'        => (string) ( $input['help'] ?? '' ),
			'placeholder' => (string) ( $input['placeholder'] ?? '' ),
			'options'     => upfw_field_options_from( (string) ( $input['type'] ?? 'text' ), $input ),
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

	update_option( 'upfw_fields', $fields );

	return $key;
}

/**
 * Borra la definición de un campo.
 *
 * Los valores que la gente ya cargó NO se tocan: si mañana el campo vuelve,
 * vuelven con él. Borrar 25.000 filas por un clic en una pantalla de ajustes
 * sería una sorpresa cara.
 */
function upfw_field_delete( string $key ): void {
	$fields = array_values(
		array_filter(
			upfw_fields( '', false ),
			static fn( array $f ): bool => $f['key'] !== $key
		)
	);

	update_option( 'upfw_fields', $fields );
}

/**
 * Guardar, borrar o mover un campo.
 *
 * En admin_init, no dentro de la pantalla: para cuando WordPress llama al
 * callback de una página del admin ya imprimió la cabecera, y el redirect
 * termina en un aviso de «headers already sent» en el log.
 */
function upfw_fields_actions(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'upfw-fields' !== ( $_GET['page'] ?? '' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['upfw_field_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['upfw_field_nonce'] ) ), 'upfw_field' ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
		$key = upfw_field_save( (array) wp_unslash( $_POST['upfw_field'] ?? array() ) );

		wp_safe_redirect( upfw_admin_url( 'upfw-fields', array( 'upfw_done' => '' === $key ? 'nolabel' : 'saved' ) ) );
		exit;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['upfw_action'], $_GET['field'] ) ) {
		return;
	}

	check_admin_referer( 'upfw_field_action' );

	$key    = sanitize_key( wp_unslash( $_GET['field'] ) );
	$accion = sanitize_key( wp_unslash( $_GET['upfw_action'] ) );

	if ( 'delete' === $accion && ! upfw_field_is_native( $key ) ) {
		upfw_field_delete( $key );
	} elseif ( 'up' === $accion ) {
		upfw_field_move( $key, -1 );
	} elseif ( 'down' === $accion ) {
		upfw_field_move( $key, 1 );
	}

	wp_safe_redirect( upfw_admin_url( 'upfw-fields', array( 'upfw_done' => $accion ) ) );
	exit;
}
add_action( 'admin_init', 'upfw_fields_actions' );

/** Sube o baja un campo en el orden. */
function upfw_field_move( string $key, int $dir ): void {
	$fields = upfw_fields( '', false );
	$keys   = wp_list_pluck( $fields, 'key' );
	$i      = array_search( $key, $keys, true );

	if ( false === $i ) {
		return;
	}

	$j = $i + $dir;

	if ( $j < 0 || $j >= count( $fields ) ) {
		return;
	}

	[ $fields[ $i ], $fields[ $j ] ] = array( $fields[ $j ], $fields[ $i ] );

	update_option( 'upfw_fields', $fields );
}

/* ── La pantalla ───────────────────────────────────────────────────── */

function upfw_screen_fields(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$editing = isset( $_GET['field'] ) ? sanitize_key( wp_unslash( $_GET['field'] ) ) : '';

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$done = isset( $_GET['upfw_done'] ) ? sanitize_key( wp_unslash( $_GET['upfw_done'] ) ) : '';

	if ( '' !== $editing || isset( $_GET['upfw_new'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		upfw_screen_field_edit( $editing );
		return;
	}

	$tabs    = array(
		'list'  => __( 'Fields', 'users-plus-for-wordpress' ),
		'usage' => __( 'How to use them', 'users-plus-for-wordpress' ),
	);
	$current = upfw_tab( $tabs );

	upfw_screen_open( __( 'User fields', 'users-plus-for-wordpress' ), 'upfw-fields', $tabs, $current );

	$avisos = array(
		'saved'   => __( 'Field saved.', 'users-plus-for-wordpress' ),
		'delete'  => __( 'Field deleted. The data already stored was left alone.', 'users-plus-for-wordpress' ),
		'nolabel' => __( 'A field needs a name.', 'users-plus-for-wordpress' ),
	);

	if ( isset( $avisos[ $done ] ) ) {
		upfw_notice( $avisos[ $done ], 'nolabel' === $done ? 'error' : 'success' );
	}

	if ( 'usage' === $current ) {
		upfw_screen_fields_usage();
	} else {
		upfw_screen_fields_list();
	}

	upfw_screen_close();
}

function upfw_screen_fields_list(): void {
	$fields = upfw_fields( '', false );
	$types  = upfw_field_types();
	$groups = upfw_groups();
	?>
	<div class="upfw-donde">
		<p><strong><?php esc_html_e( 'Where all this lives', 'users-plus-for-wordpress' ); ?></strong></p>
		<p>
			<?php
			printf(
				/* translators: 1: nombre de la option, 2: nombre de la tabla */
				esc_html__( 'What each field IS —its name, type and behaviour— is one WordPress option, %1$s. What each PERSON answered is user meta: one row per person and per field in %2$s, with the field key as the name. No extra tables.', 'users-plus-for-wordpress' ),
				'<code>upfw_fields</code>',
				'<code>' . esc_html( $GLOBALS['wpdb']->usermeta ) . '</code>'
			);
			?>
			<?php esc_html_e( 'That is why the key cannot change once the field exists, and why deleting a field leaves the answers alone: they are two different things.', 'users-plus-for-wordpress' ); ?>
		</p>
	</div>

	<p class="upfw-admin__acciones">
		<a class="button button-primary" href="<?php echo esc_url( upfw_admin_url( 'upfw-fields', array( 'upfw_new' => 1 ) ) ); ?>">
			<?php esc_html_e( 'Add field', 'users-plus-for-wordpress' ); ?>
		</a>
	</p>

	<table class="wp-list-table widefat fixed striped upfw-list">
		<thead>
			<tr>
				<th class="upfw-list__name"><?php esc_html_e( 'Name', 'users-plus-for-wordpress' ); ?></th>
				<th><?php esc_html_e( 'Type', 'users-plus-for-wordpress' ); ?></th>
				<th><?php esc_html_e( 'Where', 'users-plus-for-wordpress' ); ?></th>
				<th><?php esc_html_e( 'Required', 'users-plus-for-wordpress' ); ?></th>
				<th><?php esc_html_e( 'Status', 'users-plus-for-wordpress' ); ?></th>
				<th class="upfw-list__order"><?php esc_html_e( 'Order', 'users-plus-for-wordpress' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( array() === $fields ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No fields yet.', 'users-plus-for-wordpress' ); ?></td></tr>
			<?php endif; ?>

			<?php
			foreach ( $fields as $field ) :
				$edit = upfw_admin_url( 'upfw-fields', array( 'field' => $field['key'] ) );
				?>
				<tr>
					<td class="upfw-list__name">
						<strong><a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $field['label'] ); ?></a></strong>
						<code><?php echo esc_html( $field['key'] ); ?></code>
						<div class="row-actions">
							<span class="edit"><a href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Edit', 'users-plus-for-wordpress' ); ?></a></span>

							<?php
							/*
							Los de WordPress no se borran: el dato existe igual y lo
									usan el escritorio y medio plugin del sitio. Se esconden. */
							?>
							<?php if ( ! upfw_field_is_native( $field['key'] ) ) : ?>
								<span class="trash"> |
									<a class="upfw-danger"
										href="
										<?php
										echo esc_url(
											wp_nonce_url(
												upfw_admin_url(
													'upfw-fields',
													array(
														'field' => $field['key'],
														'upfw_action' => 'delete',
													)
												),
												'upfw_field_action'
											)
										);
										?>
												"
										onclick="return confirm(<?php echo esc_attr( wp_json_encode( __( 'Delete this field? The data already stored is kept.', 'users-plus-for-wordpress' ) ) ); ?>);">
										<?php esc_html_e( 'Delete', 'users-plus-for-wordpress' ); ?>
									</a>
								</span>
							<?php endif; ?>
						</div>
					</td>
					<td><?php echo esc_html( $types[ $field['type'] ] ?? $field['type'] ); ?></td>
					<td><?php echo esc_html( $groups[ $field['group'] ] ?? $field['group'] ); ?></td>
					<td><?php echo $field['required'] ? esc_html__( 'Yes', 'users-plus-for-wordpress' ) : '—'; ?></td>
					<td>
						<?php
						if ( 'never' === $field['edit'] ) {
							esc_html_e( 'Read only', 'users-plus-for-wordpress' );
						} elseif ( 'limited' === $field['edit'] ) {
							printf(
								/* translators: %d: cuántas veces se puede cambiar */
								esc_html( _n( '%d time', '%d times', (int) $field['edit_max'], 'users-plus-for-wordpress' ) ),
								(int) $field['edit_max']
							);
						} else {
							echo '&mdash;';
						}
						?>
					</td>
					<td>
						<span class="upfw-pill upfw-pill--<?php echo $field['active'] ? 'on' : 'off'; ?>">
							<?php echo $field['active'] ? esc_html__( 'Active', 'users-plus-for-wordpress' ) : esc_html__( 'Hidden', 'users-plus-for-wordpress' ); ?>
						</span>
					</td>
					<td class="upfw-list__order">
						<a class="button button-small" href="
						<?php
						echo esc_url(
							wp_nonce_url(
								upfw_admin_url(
									'upfw-fields',
									array(
										'field'       => $field['key'],
										'upfw_action' => 'up',
									)
								),
								'upfw_field_action'
							)
						);
						?>
																" aria-label="<?php esc_attr_e( 'Move up', 'users-plus-for-wordpress' ); ?>">&uarr;</a>
						<a class="button button-small" href="
						<?php
						echo esc_url(
							wp_nonce_url(
								upfw_admin_url(
									'upfw-fields',
									array(
										'field'       => $field['key'],
										'upfw_action' => 'down',
									)
								),
								'upfw_field_action'
							)
						);
						?>
																" aria-label="<?php esc_attr_e( 'Move down', 'users-plus-for-wordpress' ); ?>">&darr;</a>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/** La ficha de un campo. Con clave vacía, es uno nuevo. */
function upfw_screen_field_edit( string $key ): void {
	$field = '' === $key ? upfw_normalize_field(
		array(
			'group'  => 'optional',
			'active' => 1,
		)
	) : upfw_field( $key );

	if ( null === $field ) {
		upfw_screen_open( __( 'User fields', 'users-plus-for-wordpress' ) );
		upfw_notice( __( 'That field does not exist.', 'users-plus-for-wordpress' ), 'error' );
		upfw_screen_close();
		return;
	}

	$nuevo = '' === $key;

	upfw_screen_open(
		$nuevo
		? __( 'New field', 'users-plus-for-wordpress' )
		: sprintf( /* translators: %s: nombre del campo */ __( 'Field: %s', 'users-plus-for-wordpress' ), $field['label'] )
	);
	?>
	<p><a href="<?php echo esc_url( upfw_admin_url( 'upfw-fields' ) ); ?>">&larr; <?php esc_html_e( 'Back to the list', 'users-plus-for-wordpress' ); ?></a></p>

	<form method="post" class="upfw-form-admin">
		<?php wp_nonce_field( 'upfw_field', 'upfw_field_nonce' ); ?>
		<input type="hidden" name="upfw_field[key]" value="<?php echo esc_attr( $field['key'] ); ?>">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="upfw-label"><?php esc_html_e( 'Name', 'users-plus-for-wordpress' ); ?></label></th>
				<td>
					<input type="text" id="upfw-label" name="upfw_field[label]" class="regular-text" value="<?php echo esc_attr( $field['label'] ); ?>" required>
					<?php if ( ! $nuevo ) : ?>
						<p class="description"><?php esc_html_e( 'Key:', 'users-plus-for-wordpress' ); ?> <code><?php echo esc_html( $field['key'] ); ?></code> — <?php esc_html_e( 'it cannot change: it is the name the data is stored under.', 'users-plus-for-wordpress' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'The key is generated from the name.', 'users-plus-for-wordpress' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="upfw-type"><?php esc_html_e( 'Type', 'users-plus-for-wordpress' ); ?></label></th>
				<td>
					<?php
					/*
					Los de WordPress vienen con su tipo puesto: cambiarle el
							tipo al nombre no lo mejora, y lo puede romper. */
					?>
					<select id="upfw-type" name="upfw_field[type]" <?php disabled( upfw_field_is_native( $field['key'] ) ); ?>>
						<?php foreach ( upfw_field_types() as $value => $name ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $field['type'], $value ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>

					<?php if ( upfw_field_is_native( $field['key'] ) ) : ?>
						<input type="hidden" name="upfw_field[type]" value="<?php echo esc_attr( $field['type'] ); ?>">
						<p class="description"><?php esc_html_e( 'This one is WordPress’s own: it can be renamed, reordered, made required or hidden, but it keeps its type and cannot be deleted.', 'users-plus-for-wordpress' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( '“Country” shows the full list with its dial codes; “Phone with country code” splits the number in two and stores it in international format.', 'users-plus-for-wordpress' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Who can change it', 'users-plus-for-wordpress' ); ?></th>
				<td>
					<?php
					$modos = array(
						'always'  => __( 'Whenever they want', 'users-plus-for-wordpress' ),
						'limited' => __( 'Only a few times, and then no more', 'users-plus-for-wordpress' ),
						'never'   => __( 'Never — they can see it, only an administrator changes it', 'users-plus-for-wordpress' ),
					);

					foreach ( $modos as $clave => $rotulo ) :
						?>
						<label class="upfw-roles__item">
							<input type="radio" name="upfw_field[edit]" value="<?php echo esc_attr( $clave ); ?>" <?php checked( $field['edit'], $clave ); ?>>
							<?php echo esc_html( $rotulo ); ?>
						</label>
					<?php endforeach; ?>

					<p class="upfw-si-tipo-edit">
						<label for="upfw-edit-max"><?php esc_html_e( 'How many times', 'users-plus-for-wordpress' ); ?></label>
						<input type="number" id="upfw-edit-max" name="upfw_field[edit_max]" min="1" max="99" class="small-text" value="<?php echo esc_attr( (string) $field['edit_max'] ); ?>">
					</p>

					<p class="description"><?php esc_html_e( 'This is about the person who owns the data. An administrator can always change it, from the user’s profile: otherwise a one-change field turns into a typo nobody can fix.', 'users-plus-for-wordpress' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="upfw-group"><?php esc_html_e( 'Where it goes', 'users-plus-for-wordpress' ); ?></label></th>
				<td>
					<select id="upfw-group" name="upfw_field[group]">
						<?php foreach ( upfw_groups() as $g => $g_label ) : ?>
							<option value="<?php echo esc_attr( $g ); ?>" <?php selected( $field['group'], $g ); ?>><?php echo esc_html( $g_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'The profile form shows two blocks: the essentials first, and underneath the optional ones with their own explanation. This decides which one the field lands in.', 'users-plus-for-wordpress' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="upfw-help"><?php esc_html_e( 'Help text', 'users-plus-for-wordpress' ); ?></label></th>
				<td>
					<input type="text" id="upfw-help" name="upfw_field[help]" class="large-text" value="<?php echo esc_attr( $field['help'] ); ?>">
					<p class="description"><?php esc_html_e( 'Why we are asking. Shown under the field.', 'users-plus-for-wordpress' ); ?></p>
				</td>
			</tr>
			<tr class="upfw-si-tipo" data-tipo="text textarea email url number datalist phone">
				<th scope="row"><label for="upfw-placeholder"><?php esc_html_e( 'Placeholder text', 'users-plus-for-wordpress' ); ?></label></th>
				<td>
					<input type="text" id="upfw-placeholder" name="upfw_field[placeholder]" class="regular-text" value="<?php echo esc_attr( $field['placeholder'] ); ?>">
					<p class="description"><?php esc_html_e( 'Shown in grey inside the empty field.', 'users-plus-for-wordpress' ); ?></p>
				</td>
			</tr>
			<tr class="upfw-si-tipo" data-tipo="select datalist">
				<th scope="row"><label for="upfw-options"><?php esc_html_e( 'Values', 'users-plus-for-wordpress' ); ?></label></th>
				<td>
					<textarea id="upfw-options" name="upfw_field[options]" class="large-text code" rows="5"><?php echo esc_textarea( implode( "\n", 'country' === $field['type'] || 'phone' === $field['type'] ? array() : $field['options'] ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One per line. In a fixed list they are the only accepted values; in text with suggestions they are just hints and the person can write something else.', 'users-plus-for-wordpress' ); ?></p>
				</td>
			</tr>

			<tr class="upfw-si-tipo" data-tipo="country">
				<th scope="row"><label for="upfw-preferred"><?php esc_html_e( 'Countries shown first', 'users-plus-for-wordpress' ); ?></label></th>
				<td>
					<select id="upfw-preferred" name="upfw_field[preferred][]" multiple size="8" class="upfw-multi">
						<?php foreach ( upfw_countries_sorted() as $iso => $country_name ) : ?>
							<option value="<?php echo esc_attr( $iso ); ?>" <?php selected( in_array( $iso, $field['options'], true ) ); ?>>
								<?php echo esc_html( $country_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php
						printf(
							/* translators: %d: cantidad de países */
							esc_html__( 'Optional. The list of %d countries comes with the plugin — there is nothing to load. These ones go on top, separated from the rest, so nobody has to scroll to find the one next door.', 'users-plus-for-wordpress' ),
							count( upfw_countries() )
						);
						?>
					</p>
				</td>
			</tr>

			<tr class="upfw-si-tipo" data-tipo="phone">
				<th scope="row"><label for="upfw-default-country"><?php esc_html_e( 'Country selected by default', 'users-plus-for-wordpress' ); ?></label></th>
				<td>
					<select id="upfw-default-country" name="upfw_field[default_country]">
						<option value=""><?php esc_html_e( '— None —', 'users-plus-for-wordpress' ); ?></option>
						<?php foreach ( upfw_countries_sorted() as $iso => $country_name ) : ?>
							<option value="<?php echo esc_attr( $iso ); ?>" <?php selected( ( $field['options'][0] ?? '' ), $iso ); ?>>
								<?php echo esc_html( $country_name . ' +' . upfw_country_dial( $iso ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'The dial code the field comes with. The person can change it: the full list is always there.', 'users-plus-for-wordpress' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Behaviour', 'users-plus-for-wordpress' ); ?></th>
				<td>
					<label><input type="checkbox" name="upfw_field[required]" value="1" <?php checked( $field['required'], 1 ); ?>> <?php esc_html_e( 'Required', 'users-plus-for-wordpress' ); ?></label><br>
					<label><input type="checkbox" name="upfw_field[active]" value="1" <?php checked( $field['active'], 1 ); ?>> <?php esc_html_e( 'Active: show it in the forms', 'users-plus-for-wordpress' ); ?></label>
				</td>
			</tr>
		</table>

		<?php submit_button( $nuevo ? __( 'Add field', 'users-plus-for-wordpress' ) : __( 'Save field', 'users-plus-for-wordpress' ) ); ?>
	</form>
	<?php
	upfw_screen_close();
}

function upfw_screen_fields_usage(): void {
	upfw_intro( __( 'The fields show up on their own in the dashboard profile, when adding a user and in the WordPress registration form. On the front end you place them with a shortcode.', 'users-plus-for-wordpress' ) );
	?>
	<table class="widefat striped upfw-shortcodes">
		<tbody>
			<tr><td><code>[upfw_fields]</code></td><td><?php esc_html_e( 'Every field, for the person to edit.', 'users-plus-for-wordpress' ); ?></td></tr>
			<tr><td><code>[upfw_fields group="basic"]</code></td><td><?php esc_html_e( 'Only the basic ones. With group="optional", only the others.', 'users-plus-for-wordpress' ); ?></td></tr>
			<tr><td><code>[upfw_login]</code></td><td><?php esc_html_e( 'The email sign-in form and the social buttons.', 'users-plus-for-wordpress' ); ?></td></tr>
			<tr><td><code>[upfw_accounts]</code></td><td><?php esc_html_e( 'Linked providers, to link or unlink.', 'users-plus-for-wordpress' ); ?></td></tr>
			<tr><td><code>[upfw_sessions]</code></td><td><?php esc_html_e( 'Open sessions, with the button to close them.', 'users-plus-for-wordpress' ); ?></td></tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Fitting them into your design', 'users-plus-for-wordpress' ); ?></h2>
	<p class="upfw-admin__intro">
		<?php esc_html_e( 'Copy any file from the plugin’s templates/ folder into your theme, inside a users-plus-for-wordpress/ folder, and edit it there. The plugin will use yours. You can also turn off its stylesheet in Sign in → Presentation.', 'users-plus-for-wordpress' ); ?>
	</p>
	<?php
}
