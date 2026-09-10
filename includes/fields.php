<?php
/**
 * Los campos de usuario: qué se le pide a una persona además del correo.
 *
 * La definición vive en una option (`users_dlx_plus_fields`) y se edita desde el admin;
 * el valor de cada persona vive en su user meta, con la clave del campo. No hay
 * tabla propia: son datos de usuario y WordPress ya tiene dónde ponerlos.
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;

/**
 * Los tipos de campo que entiende el plugin.
 *
 * @return array<string, string> tipo => nombre para mostrar.
 */
function users_dlx_plus_field_types(): array {
	return array(
		'text'     => __( 'Text', 'users-dlx-plus' ),
		'textarea' => __( 'Long text', 'users-dlx-plus' ),
		'email'    => __( 'Email address', 'users-dlx-plus' ),
		'phone'    => __( 'Phone with country code', 'users-dlx-plus' ),
		'country'  => __( 'Country', 'users-dlx-plus' ),
		'url'      => __( 'Web address', 'users-dlx-plus' ),
		'number'   => __( 'Number', 'users-dlx-plus' ),
		'date'     => __( 'Date', 'users-dlx-plus' ),
		'select'   => __( 'Fixed list', 'users-dlx-plus' ),
		'datalist' => __( 'Text with suggestions', 'users-dlx-plus' ),
		'checkbox' => __( 'Yes / no', 'users-dlx-plus' ),
	);
}

/**
 * ¿Este tipo usa la lista de opciones, y para qué?
 *
 * En «lista cerrada» y «texto con sugerencias» las opciones son los valores.
 * En «país» son los códigos ISO que van arriba de la lista, y en «teléfono»
 * el país que viene elegido por defecto. En el resto no se usan.
 */
function users_dlx_plus_field_uses_options( string $type ): string {
	switch ( $type ) {
		case 'select':
		case 'datalist':
			return 'values';

		case 'country':
			return 'preferred';

		case 'phone':
			return 'default';

		default:
			return '';
	}
}

/**
 * Los campos que ya son de WordPress.
 *
 * El nombre y el apellido no son un invento de este plugin: WordPress los
 * tiene desde siempre, los muestra en el escritorio y los usan medio mundo de
 * plugins. Que además aparezcan acá, en la misma lista y con las mismas
 * reglas que los demás, es lo que hace que «Mis datos» sea la pantalla donde
 * se editan TUS datos y no sólo los que este plugin agregó.
 *
 * No se guardan en una user meta cualquiera: van a donde WordPress los busca.
 *
 * @return array<string, string> clave => cómo se guarda.
 */
function users_dlx_plus_native_fields(): array {
	return array(
		'first_name' => 'meta',
		'last_name'  => 'meta',
	);
}

/** ¿Esta clave es de un campo de WordPress? */
function users_dlx_plus_field_is_native( string $key ): bool {
	return isset( users_dlx_plus_native_fields()[ $key ] );
}

/**
 * Los campos con los que arranca un sitio nuevo.
 *
 * Son los que hacen falta casi siempre. Cualquiera se puede borrar desde el
 * admin: no hay campos intocables.
 *
 * @return array<int, array<string, mixed>>
 */
function users_dlx_plus_default_fields(): array {
	return array(
		array(
			'key'      => 'first_name',
			'label'    => __( 'First name', 'users-dlx-plus' ),
			'type'     => 'text',
			'help'     => '',
			'options'  => array(),
			'required' => 1,
			'group'    => 'main',
			'active'   => 1,
		),
		array(
			'key'      => 'last_name',
			'label'    => __( 'Last name', 'users-dlx-plus' ),
			'type'     => 'text',
			'help'     => '',
			'options'  => array(),
			'required' => 0,
			'group'    => 'main',
			'active'   => 1,
		),
		array(
			'key'      => 'users_dlx_plus_country',
			'label'    => __( 'Country', 'users-dlx-plus' ),
			'type'     => 'country',
			'help'     => '',
			// Los de la región primero: el orden alfabético puro deja al país
			// del sitio a mitad de una lista de casi doscientos.
			'options'  => array( 'AR', 'CL', 'UY', 'PY', 'BO', 'BR', 'PE', 'MX', 'ES' ),
			'required' => 0,
			'group'    => 'main',
			'active'   => 1,
		),
		array(
			'key'      => 'users_dlx_plus_birthday',
			'label'    => __( 'Date of birth', 'users-dlx-plus' ),
			'type'     => 'date',
			'help'     => __( 'So we can wish you a happy birthday.', 'users-dlx-plus' ),
			'options'  => array(),
			'required' => 0,
			'group'    => 'extra',
			'active'   => 1,
		),
		array(
			'key'      => 'users_dlx_plus_gender',
			'label'    => __( 'Gender', 'users-dlx-plus' ),
			'type'     => 'datalist',
			'help'     => __( 'However you identify. Write anything you like, or leave it empty.', 'users-dlx-plus' ),
			'options'  => array(
				__( 'Woman', 'users-dlx-plus' ),
				__( 'Man', 'users-dlx-plus' ),
				__( 'Non-binary', 'users-dlx-plus' ),
				__( 'Prefer not to say', 'users-dlx-plus' ),
			),
			'required' => 0,
			'group'    => 'extra',
			'active'   => 1,
		),
		array(
			'key'      => 'users_dlx_plus_phone',
			'label'    => __( 'Mobile (WhatsApp)', 'users-dlx-plus' ),
			'type'     => 'phone',
			'help'     => __( 'With country code. Only for notifications you ask for.', 'users-dlx-plus' ),
			'options'  => array( 'AR' ),
			'required' => 0,
			'group'    => 'extra',
			'active'   => 1,
		),
	);
}

/**
 * El bloque del formulario al que pertenece un campo.
 *
 * Son dos y con nombre propio: el principal —lo que el sitio necesita— y el
 * adicional, el «si querés, contanos un poco más». Se aceptan los nombres
 * viejos para no romper lo que ya esté guardado.
 */
function users_dlx_plus_normalize_group( string $group ): string {
	$viejos = array(
		'basic'    => 'main',
		'optional' => 'extra',
	);
	$group  = $viejos[ $group ] ?? $group;

	return 'main' === $group ? 'main' : 'extra';
}

/**
 * Los bloques, para mostrarlos.
 *
 * @return array<string, string>
 */
function users_dlx_plus_groups(): array {
	return array(
		'main'  => __( 'Main block — what the site needs', 'users-dlx-plus' ),
		'extra' => __( 'Extra block — “if you like, tell us more”', 'users-dlx-plus' ),
	);
}

/**
 * Un campo, normalizado. Rellena lo que falte para que quien lo consuma no
 * tenga que chequear cada clave.
 *
 * @param array<string, mixed> $field
 * @return array<string, mixed>
 */
function users_dlx_plus_normalize_field( array $field ): array {
	$types = users_dlx_plus_field_types();

	return array(
		'key'         => sanitize_key( (string) ( $field['key'] ?? '' ) ),
		'label'       => sanitize_text_field( (string) ( $field['label'] ?? '' ) ),
		'type'        => isset( $types[ $field['type'] ?? '' ] ) ? (string) $field['type'] : 'text',
		'help'        => sanitize_text_field( (string) ( $field['help'] ?? '' ) ),
		'placeholder' => sanitize_text_field( (string) ( $field['placeholder'] ?? '' ) ),
		'options'     => array_values(
			array_filter(
				array_map(
					static fn( $o ): string => sanitize_text_field( (string) $o ),
					(array) ( $field['options'] ?? array() )
				),
				static fn( string $o ): bool => '' !== $o
			)
		),
		'required'    => empty( $field['required'] ) ? 0 : 1,
		'group'       => users_dlx_plus_normalize_group( (string) ( $field['group'] ?? '' ) ),
		'active'      => isset( $field['active'] ) && ! $field['active'] ? 0 : 1,
		// Qué puede hacer con este campo la persona dueña del dato:
		// 'always' cambiarlo cuando quiera, 'limited' unas cuantas veces,
		// 'never' mirarlo nomás. Quien administra puede siempre.
		'edit'        => in_array( $field['edit'] ?? '', array( 'always', 'limited', 'never' ), true )
			? (string) $field['edit']
			: 'always',
		'edit_max'    => max( 1, (int) ( $field['edit_max'] ?? 1 ) ),
	);
}

/**
 * Todos los campos definidos.
 *
 * @param string $group 'basic', 'optional' o '' para todos.
 * @param bool   $solo_activos
 * @return array<int, array<string, mixed>>
 */
function users_dlx_plus_fields( string $group = '', bool $solo_activos = true ): array {
	$fields = array_map( 'users_dlx_plus_normalize_field', (array) get_option( 'users_dlx_plus_fields', array() ) );

	$fields = array_values(
		array_filter(
			$fields,
			static function ( array $c ) use ( $group, $solo_activos ): bool {
				if ( '' === $c['key'] || '' === $c['label'] ) {
					return false;
				}
				if ( $solo_activos && ! $c['active'] ) {
					return false;
				}

				return '' === $group || $group === $c['group'];
			}
		)
	);

	/**
	 * Filtra la lista de campos.
	 *
	 * Es el punto para agregar o esconder uno desde un tema o desde otro
	 * plugin, sin tocar la configuración guardada.
	 *
	 * @param array<int, array<string, mixed>> $fields
	 * @param string                           $group
	 */
	return apply_filters( 'users_dlx_plus_fields', $fields, $group );
}

/** Un campo por su clave, o null. */
/**
 * @return array<string, mixed>
 */
function users_dlx_plus_field( string $key ): ?array {
	foreach ( users_dlx_plus_fields( '', false ) as $field ) {
		if ( $field['key'] === $key ) {
			return $field;
		}
	}

	return null;
}

/** El valor que tiene una persona en un campo. */
function users_dlx_plus_value( int $user_id, string $key ): string {
	return (string) get_user_meta( $user_id, $key, true );
}

/**
 * Al guardar un nombre o un apellido, WordPress espera que el nombre visible
 * se rearme solo.
 *
 * Sin esto, quien se llamaba «juan@correo.com» sigue apareciendo así al lado
 * de lo que escribe, aunque haya completado su nombre hace un rato.
 */
function users_dlx_plus_refresh_display_name( int $user_id ): void {
	$user = get_userdata( $user_id );

	if ( ! $user instanceof WP_User ) {
		return;
	}

	$nombre = trim( (string) get_user_meta( $user_id, 'first_name', true ) . ' ' . (string) get_user_meta( $user_id, 'last_name', true ) );

	if ( '' === $nombre || $user->display_name === $nombre ) {
		return;
	}

	wp_update_user(
		array(
			'ID'           => $user_id,
			'display_name' => $nombre,
		)
	);
}

/* ── Quién puede cambiar qué ───────────────────────────────────────── */

/**
 * Anota un cambio, si de verdad cambió algo y si lo hizo la persona.
 *
 * Escribir lo mismo que ya estaba no gasta un cupo: quien aprieta «Guardar»
 * dos veces seguidas no cambió nada.
 *
 * @param array<string, mixed> $field
 */
function users_dlx_plus_field_count_edit( int $user_id, array $field, string $value ): void {
	if ( 'limited' !== $field['edit'] || get_current_user_id() !== $user_id ) {
		return;
	}

	if ( users_dlx_plus_value( $user_id, $field['key'] ) === $value ) {
		return;
	}

	update_user_meta( $user_id, 'users_dlx_plus_edits_' . $field['key'], users_dlx_plus_field_edits( $user_id, $field['key'] ) + 1 );
}


/**
 * Cuántas veces cambió esta persona este campo.
 *
 * Se cuenta sólo lo que hace ella con sus propios datos. Un administrador
 * corrigiendo el apellido de otro no le gasta el cupo a nadie: el límite
 * existe para que un nombre no cambie todos los días, no para dejar sin
 * arreglo un error de tipeo.
 */
function users_dlx_plus_field_edits( int $user_id, string $key ): int {
	return (int) get_user_meta( $user_id, 'users_dlx_plus_edits_' . $key, true );
}

/** Cuántos cambios le quedan. -1 si no hay límite. */
/**
 * @param array<string, mixed> $field
 */
function users_dlx_plus_field_edits_left( array $field, int $user_id ): int {
	if ( 'limited' !== $field['edit'] ) {
		return -1;
	}

	return max( 0, (int) $field['edit_max'] - users_dlx_plus_field_edits( $user_id, $field['key'] ) );
}

/**
 * ¿Esta persona puede cambiar este campo ahora?
 *
 * Quien administra siempre puede: si no, un campo de una sola edición se
 * convierte en un dato que ya nadie puede corregir, ni con motivo.
 *
 * @param array<string, mixed> $field
 */
function users_dlx_plus_field_editable( array $field, int $user_id ): bool {
	if ( current_user_can( 'edit_users' ) && get_current_user_id() !== $user_id ) {
		return true;
	}

	if ( 'never' === $field['edit'] ) {
		return false;
	}

	return 'limited' !== $field['edit'] || users_dlx_plus_field_edits_left( $field, $user_id ) > 0;
}

/**
 * Lo que se le dice a la persona debajo del campo sobre cuántas veces puede
 * cambiarlo. Vacío cuando no hay nada que aclarar.
 *
 * @param array<string, mixed> $field
 */
function users_dlx_plus_field_edit_note( array $field, int $user_id ): string {
	if ( 'never' === $field['edit'] ) {
		return __( 'This one cannot be changed from here. Write to us if it is wrong.', 'users-dlx-plus' );
	}

	if ( 'limited' !== $field['edit'] ) {
		return '';
	}

	$quedan = users_dlx_plus_field_edits_left( $field, $user_id );

	if ( 0 === $quedan ) {
		return __( 'You already used up the changes for this one. Write to us if it is wrong.', 'users-dlx-plus' );
	}

	return sprintf(
		/* translators: %d: cuántas veces más lo puede cambiar */
		_n( 'You can change this one %d more time.', 'You can change this one %d more times.', $quedan, 'users-dlx-plus' ),
		$quedan
	);
}

/**
 * Limpia un valor según el tipo del campo.
 *
 * @param array<string, mixed> $field
 */
function users_dlx_plus_sanitize( array $field, string $value ): string {
	$value = trim( $value );

	switch ( $field['type'] ) {
		case 'email':
			return sanitize_email( $value );

		case 'url':
			return esc_url_raw( $value );

		case 'textarea':
			return sanitize_textarea_field( $value );

		case 'number':
			return '' === $value ? '' : (string) floatval( $value );

		case 'date':
			// Se guarda como AAAA-MM-DD, que es lo que manda el input y lo
			// único que se ordena y se compara sin ambigüedad.
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';

		case 'phone':
			// Se guarda en formato internacional: "+" y dígitos, nada más. Lo
			// que se ve con espacios lo arma el formulario; el dato guardado
			// tiene que poder mandarse a una API sin limpiarlo de nuevo.
			$digits = (string) preg_replace( '/\D/', '', $value );

			return '' === $digits ? '' : '+' . $digits;

		case 'country':
			// Se guarda el código ISO, no el nombre: el nombre cambia con el
			// idioma y con el humor de la geopolítica, el código no.
			$iso = strtoupper( trim( $value ) );

			return isset( users_dlx_plus_countries()[ $iso ] ) ? $iso : '';

		case 'checkbox':
			return '' === $value ? '' : '1';

		case 'select':
			// Una lista cerrada es cerrada: lo que no está en la lista, no entra.
			return in_array( $value, $field['options'], true ) ? $value : '';

		default:
			return sanitize_text_field( $value );
	}
}

/**
 * Guarda los campos de una persona a partir de un array crudo (típicamente
 * $_POST). Sólo mira las claves que existen como campo.
 *
 * Un valor vacío borra la meta en vez de guardar una cadena vacía: así el
 * usuario no acumula filas que no dicen nada.
 *
 * @param array<string, mixed> $input
 * @param string               $group   Limita a un grupo, o '' para todos.
 * @return array<int, string> Etiquetas de los campos obligatorios que faltan.
 */
function users_dlx_plus_save( int $user_id, array $input, string $group = '' ): array {
	$missing = array();

	foreach ( users_dlx_plus_fields( $group ) as $field ) {
		$key = $field['key'];

		if ( ! array_key_exists( $key, $input ) ) {
			continue;
		}

		// El control del navegador se puede sacar con el inspector, así que
		// el que manda es éste: lo que no se puede editar, no se guarda.
		if ( ! users_dlx_plus_field_editable( $field, $user_id ) ) {
			continue;
		}

		$raw = (string) wp_unslash( $input[ $key ] );

		// El teléfono llega en dos partes: el prefijo del país, de su lista, y
		// el número. Se juntan acá y no en el navegador para que también valga
		// cuando el formulario llega sin JavaScript.
		if ( 'phone' === $field['type'] && '' !== trim( $raw ) ) {
			$dial = users_dlx_plus_country_dial( sanitize_text_field( (string) wp_unslash( $input[ $key . '_dial' ] ?? '' ) ) );
			$raw  = '+' . $dial . preg_replace( '/\D/', '', $raw );
		}

		$value = users_dlx_plus_sanitize( $field, $raw );

		if ( '' === $value ) {
			if ( $field['required'] ) {
				$missing[] = $field['label'];
				continue;
			}

			users_dlx_plus_field_count_edit( $user_id, $field, '' );
			delete_user_meta( $user_id, $key );
			continue;
		}

		users_dlx_plus_field_count_edit( $user_id, $field, $value );

		update_user_meta( $user_id, $key, $value );

		if ( users_dlx_plus_field_is_native( $key ) ) {
			$refrescar = true;
		}
	}

	if ( ! empty( $refrescar ) ) {
		users_dlx_plus_refresh_display_name( $user_id );
	}

	/**
	 * Corre después de guardar los campos de una persona.
	 *
	 * @param int                  $user_id
	 * @param array<string, mixed> $input
	 * @param string               $group
	 */
	do_action( 'users_dlx_plus_fields_saved', $user_id, $input, $group );

	return $missing;
}
