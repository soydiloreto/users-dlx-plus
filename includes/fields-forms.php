<?php
/**
 * Los campos, metidos en los formularios que ya existen.
 *
 * El plugin no sabe cómo entra la gente al sitio, y no tiene por qué: los
 * mismos campos aparecen en el registro nativo de WordPress, en el alta que
 * hace un administrador, en el perfil del escritorio y —vía shortcode— en la
 * pantalla que tenga el sitio en el front. Un sitio con registro abierto y uno
 * con SSO o enlace por correo terminan con los mismos datos guardados.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

/**
 * Un campo suelto, en el marcado de tabla que usa el escritorio.
 *
 * @param array<string, mixed> $field
 */
function upfw_field_row( array $field, int $user_id ): void {
	$value = upfw_value( $user_id, $field['key'] );
	$id    = 'upfw-' . $field['key'];
	?>
	<tr>
		<th><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
		<td>
			<?php upfw_field_input( $field, $value, $id ); ?>
			<?php if ( '' !== $field['help'] ) : ?>
				<p class="description"><?php echo esc_html( $field['help'] ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

/**
 * El control de un campo: el <input>, <select> o <textarea> que corresponda.
 *
 * Está separado del marcado de alrededor a propósito: es lo único que no puede
 * cambiar entre el escritorio, el registro y la plantilla del front, así que
 * se escribe una vez y lo usan los tres.
 *
 * @param array<string, mixed> $field
 */
function upfw_field_input( array $field, string $value, string $id = '' ): void {
	$key              = $field['key'];
	$id               = '' === $id ? $key : $id;
	$required_attr    = $field['required'] ? ' required' : '';
	$placeholder_attr = '' === $field['placeholder'] ? '' : ' placeholder="' . esc_attr( $field['placeholder'] ) . '"';

	// Un campo que no se puede cambiar se muestra igual: el dato es de la
	// persona y tiene derecho a verlo. En los de escribir va `readonly`, que
	// deja copiar y sigue mandándose; en los de elegir no existe `readonly` y
	// hay que usar `disabled`. Lo que manda igual es el servidor: esto es
	// para que se entienda, no para impedir nada.
	$editable = upfw_field_editable( $field, get_current_user_id() );
	$lock     = $editable ? '' : ' readonly';
	$lock_sel = $editable ? '' : ' disabled';

	switch ( $field['type'] ) {
		case 'textarea':
			printf(
				'<textarea id="%1$s" name="%2$s" rows="4"%3$s%4$s>%5$s</textarea>',
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( $required_attr . $lock ),
				wp_kses_post( $placeholder_attr ),
				esc_textarea( $value )
			);
			return;

		case 'select':
			printf( '<select id="%1$s" name="%2$s"%3$s>', esc_attr( $id ), esc_attr( $key ), esc_attr( $required_attr . $lock_sel ) );
			printf( '<option value="">%s</option>', esc_html__( '— Choose —', 'users-plus-for-wordpress' ) );

			foreach ( $field['options'] as $option ) {
				printf(
					'<option value="%1$s"%2$s>%1$s</option>',
					esc_attr( $option ),
					selected( $value, $option, false )
				);
			}

			echo '</select>';
			return;

		case 'checkbox':
			printf(
				'<label><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s%4$s> %5$s</label>',
				esc_attr( $id ),
				esc_attr( $key ),
				checked( $value, '1', false ),
				esc_attr( $lock_sel ),
				esc_html( $field['label'] )
			);
			return;

		case 'country':
			printf( '<select id="%1$s" name="%2$s"%3$s>', esc_attr( $id ), esc_attr( $key ), esc_attr( $required_attr . $lock_sel ) );
			printf( '<option value="">%s</option>', esc_html__( '— Choose —', 'users-plus-for-wordpress' ) );

			$preferred = upfw_countries_sorted( $field['options'] );
			$cut       = count( $field['options'] );
			$n         = 0;

			foreach ( $preferred as $iso => $country_name ) {
				// Los preferidos van arriba y separados del resto: si no, el
				// país del sitio queda perdido a mitad de una lista de 189.
				if ( $cut > 0 && $n === $cut ) {
					echo '<option value="" disabled>──────────</option>';
				}

				printf(
					'<option value="%1$s"%2$s>%3$s</option>',
					esc_attr( $iso ),
					selected( $value, $iso, false ),
					esc_html( $country_name )
				);

				++$n;
			}

			echo '</select>';
			return;

		case 'phone':
			// Dos controles y un solo dato: el prefijo se elige de una lista y
			// el número se escribe sin él. Lo que se guarda es la suma.
			$dial_default = strtoupper( (string) ( $field['options'][0] ?? '' ) );
			$dial         = $dial_default;
			$national     = $value;

			// Al releer un valor guardado hay que volver a partirlo. Se prueba
			// del prefijo más largo al más corto porque +1 y +1242 conviven.
			if ( '' !== $value ) {
				$digits = ltrim( $value, '+' );
				$best   = 0;

				foreach ( upfw_countries() as $iso => $data ) {
					$len = strlen( $data[1] );

					if ( $len > $best && 0 === strpos( $digits, $data[1] ) ) {
						$best     = $len;
						$dial     = $iso;
						$national = substr( $digits, $len );
					}
				}
			}

			echo '<span class="upfw-phone">';
			printf( '<select id="%1$s-dial" name="%2$s_dial" class="upfw-phone__dial"%3$s>', esc_attr( $id ), esc_attr( $key ), esc_attr( $lock_sel ) );

			foreach ( upfw_countries_sorted( $field['options'] ) as $iso => $country_name ) {
				printf(
					'<option value="%1$s"%2$s>%3$s +%4$s</option>',
					esc_attr( $iso ),
					selected( $dial, $iso, false ),
					esc_html( $country_name ),
					esc_html( upfw_country_dial( $iso ) )
				);
			}

			echo '</select>';

			printf(
				'<input type="tel" id="%1$s" name="%2$s" value="%3$s" inputmode="tel" class="upfw-phone__number" autocomplete="tel-national"%4$s%5$s>',
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( $national ),
				esc_attr( $required_attr . $lock ),
				wp_kses_post( $placeholder_attr )
			);

			echo '</span>';
			return;

		case 'datalist':
			$list = 'upfw-list-' . $key;

			printf(
				'<input type="text" id="%1$s" name="%2$s" value="%3$s" list="%4$s" autocomplete="off"%5$s%6$s>',
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( $value ),
				esc_attr( $list ),
				esc_attr( $required_attr . $lock ),
				wp_kses_post( $placeholder_attr )
			);

			printf( '<datalist id="%s">', esc_attr( $list ) );

			foreach ( $field['options'] as $option ) {
				printf( '<option value="%s"></option>', esc_attr( $option ) );
			}

			echo '</datalist>';
			return;
	}

	$types = array(
		'email'  => 'email',
		'url'    => 'url',
		'number' => 'number',
		'date'   => 'date',
	);

	printf(
		'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s"%5$s%6$s>',
		esc_attr( $types[ $field['type'] ] ?? 'text' ),
		esc_attr( $id ),
		esc_attr( $key ),
		esc_attr( $value ),
		esc_attr( $required_attr . $lock ),
		wp_kses_post( $placeholder_attr )
	);
}

/* ── Perfil del escritorio ─────────────────────────────────────────── */

function upfw_profile_fields( $user ): void {
	$fields = upfw_fields();

	if ( array() === $fields ) {
		return;
	}
	?>
	<h2><?php esc_html_e( 'Additional details', 'users-plus-for-wordpress' ); ?></h2>
	<table class="form-table" role="presentation">
		<?php foreach ( $fields as $field ) : ?>
			<?php upfw_field_row( $field, (int) $user->ID ); ?>
		<?php endforeach; ?>
	</table>
	<?php
}
add_action( 'show_user_profile', 'upfw_profile_fields' );
add_action( 'edit_user_profile', 'upfw_profile_fields' );

function upfw_profile_save( int $user_id ): void {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress ya verificó el nonce del perfil antes de este hook.
	upfw_save( $user_id, $_POST );
}
add_action( 'personal_options_update', 'upfw_profile_save' );
add_action( 'edit_user_profile_update', 'upfw_profile_save' );

/* ── Alta desde el escritorio (Usuarios → Añadir) ──────────────────── */

function upfw_new_user_fields( string $type ): void {
	$fields = upfw_fields();

	if ( 'add-new-user' !== $type || array() === $fields ) {
		return;
	}
	?>
	<h2><?php esc_html_e( 'Additional details', 'users-plus-for-wordpress' ); ?></h2>
	<table class="form-table" role="presentation">
		<?php foreach ( $fields as $field ) : ?>
			<?php upfw_field_row( $field, 0 ); ?>
		<?php endforeach; ?>
	</table>
	<?php
}
add_action( 'user_new_form', 'upfw_new_user_fields' );

/* ── Registro nativo de WordPress ──────────────────────────────────── */

/**
 * Los campos en wp-login.php?action=register.
 *
 * Sirve para el sitio que sí tiene el registro abierto. En un sitio sin
 * contraseñas este formulario no se usa nunca y esto no molesta.
 */
function upfw_register_form_fields(): void {
	foreach ( upfw_fields() as $field ) {
		$id = 'upfw-' . $field['key'];
		?>
		<p>
			<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
			<?php upfw_field_input( $field, '', $id ); ?>
			<?php if ( '' !== $field['help'] ) : ?>
				<em class="description"><?php echo esc_html( $field['help'] ); ?></em>
			<?php endif; ?>
		</p>
		<?php
	}
}
add_action( 'register_form', 'upfw_register_form_fields' );

/**
 * Un campo obligatorio vacío no deja completar el registro.
 *
 * @param WP_Error $errores
 */
function upfw_register_validate( $errores, $login, $email ) {
	foreach ( upfw_fields() as $field ) {
		if ( ! $field['required'] ) {
			continue;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- lo verifica el propio registro de WordPress.
		$value = upfw_sanitize( $field, (string) wp_unslash( $_POST[ $field['key'] ] ?? '' ) );

		if ( '' === $value ) {
			$errores->add(
				'upfw_' . $field['key'],
				sprintf(
					/* translators: %s: nombre del campo */
					esc_html__( 'Error: “%s” is required.', 'users-plus-for-wordpress' ),
					esc_html( $field['label'] )
				)
			);
		}
	}

	return $errores;
}
add_filter( 'registration_errors', 'upfw_register_validate', 10, 3 );

function upfw_register_save( int $user_id ): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- lo verifica el propio registro de WordPress.
	upfw_save( $user_id, $_POST );
}
add_action( 'user_register', 'upfw_register_save' );
