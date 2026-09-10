<?php
/**
 * La foto de perfil.
 *
 * WordPress trae un sistema de avatares y lo resuelve con Gravatar: le manda
 * el hash del correo de cada persona a un tercero y trae una imagen. Eso puede
 * estar bien o no según el sitio, así que acá hay tres capas y las tres se
 * prenden y se apagan:
 *
 *   1. La foto que la persona subió.
 *   2. Gravatar.
 *   3. Sus iniciales sobre el color de acento.
 *
 * La tercera se dibuja como un SVG en un data URI, y no como un `<span>` con
 * letras: el contrato de `get_avatar` es que devuelve una imagen, y medio
 * WordPress —y medio tema— asume eso.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

/** El adjunto que la persona subió, o 0. */
function upfw_avatar_id( int $user_id ): int {
	return (int) get_user_meta( $user_id, 'upfw_avatar', true );
}

/** La URL de la foto subida, en el tamaño pedido. Vacío si no hay. */
function upfw_avatar_url( int $user_id, int $size = 96 ): string {
	$id = upfw_avatar_id( $user_id );

	if ( $id <= 0 || ! wp_attachment_is_image( $id ) ) {
		return '';
	}

	$src = wp_get_attachment_image_src( $id, $size > 150 ? 'medium' : 'thumbnail' );

	return is_array( $src ) ? (string) $src[0] : '';
}

/** Las iniciales de alguien, para el avatar dibujado. */
function upfw_avatar_initials( int $user_id ): string {
	$user = get_userdata( $user_id );

	return $user instanceof WP_User ? upfw_initials( $user ) : '?';
}

/**
 * El avatar dibujado: las iniciales sobre el color de acento.
 *
 * Va como data URI para que no haya ni una petición más ni un archivo que
 * generar. El color sale del mismo ajuste que el resto de la hoja, así que un
 * sitio que cambia su acento cambia también estos avatares.
 */
function upfw_avatar_svg( int $user_id, int $size ): string {
	$letters = upfw_avatar_initials( $user_id );
	$accent  = upfw_style_accent();

	$svg = sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="%1$d" height="%1$d" role="img" aria-hidden="true">'
			. '<rect width="100" height="100" rx="50" fill="%2$s"/>'
			. '<text x="50" y="50" fill="#ffffff" font-family="system-ui, sans-serif" font-size="42" font-weight="700"'
			. ' text-anchor="middle" dominant-baseline="central">%3$s</text></svg>',
		$size,
		esc_attr( $accent ),
		esc_html( $letters )
	);

	return 'data:image/svg+xml;base64,' . base64_encode( $svg );
}

/**
 * Resuelve a quién corresponde lo que le llega a get_avatar.
 *
 * WordPress lo pasa de cinco formas distintas según quién llame. Sin esto, el
 * avatar aparece en el perfil y no en los comentarios, o al revés.
 *
 * @param mixed $id_or_email Lo que haya mandado WordPress: un id, un correo,
 *                           un WP_User, un WP_Comment o un WP_Post.
 */
function upfw_avatar_user_id( $id_or_email ): int {
	if ( is_numeric( $id_or_email ) ) {
		return (int) $id_or_email;
	}

	if ( $id_or_email instanceof WP_User ) {
		return (int) $id_or_email->ID;
	}

	if ( $id_or_email instanceof WP_Post ) {
		return (int) $id_or_email->post_author;
	}

	if ( $id_or_email instanceof WP_Comment ) {
		if ( ! empty( $id_or_email->user_id ) ) {
			return (int) $id_or_email->user_id;
		}

		$id_or_email = (string) $id_or_email->comment_author_email;
	}

	if ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
		$user = get_user_by( 'email', $id_or_email );

		return $user instanceof WP_User ? (int) $user->ID : 0;
	}

	return 0;
}

/**
 * La URL del avatar.
 *
 * Se filtra `pre_get_avatar_data` y no `get_avatar`, que es donde suele
 * meterse todo el mundo: acá se cambia el dato y el marcado lo sigue armando
 * WordPress, con las clases y los tamaños que espera cada tema. Y no se toca
 * ningún otro filtro: si hay otro plugin de avatares, que se peleen por
 * prioridad como corresponde, no borrándose entre ellos.
 *
 * @param array<string, mixed> $args
 * @param mixed                $id_or_email
 * @return array<string, mixed>
 */
function upfw_avatar_data( array $args, $id_or_email ): array {
	$user_id = upfw_avatar_user_id( $id_or_email );

	if ( $user_id <= 0 ) {
		return $args;
	}

	$size = isset( $args['size'] ) ? (int) $args['size'] : 96;

	if ( upfw_option( 'upfw_avatar_upload' ) ) {
		$url = upfw_avatar_url( $user_id, $size );

		if ( '' !== $url ) {
			$args['url']          = $url;
			$args['found_avatar'] = true;

			return $args;
		}
	}

	if ( upfw_option( 'upfw_avatar_gravatar' ) ) {
		return $args;
	}

	if ( upfw_option( 'upfw_avatar_initials' ) ) {
		$args['url']          = upfw_avatar_svg( $user_id, $size );
		$args['found_avatar'] = true;
	}

	return $args;
}
add_filter( 'pre_get_avatar_data', 'upfw_avatar_data', 99, 2 );

/* ── Subir y sacar ─────────────────────────────────────────────────── */

/** Los tipos que se aceptan. Nada de SVG: es código, no una foto. */
/**
 * @return array<int, string>
 */
function upfw_avatar_types(): array {
	return array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
}

/**
 * Guarda la foto que subió alguien.
 *
 * @param array<string, mixed> $file
 * @return int|WP_Error El id del adjunto.
 */
function upfw_avatar_upload( int $user_id, array $file ) {
	if ( ! upfw_option( 'upfw_avatar_upload' ) ) {
		return new WP_Error( 'upfw_avatar_off', __( 'This site does not accept profile photos.', 'users-plus-for-wordpress' ) );
	}

	if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return new WP_Error( 'upfw_avatar_none', __( 'No file arrived.', 'users-plus-for-wordpress' ) );
	}

	$max = max( 1, (int) upfw_option( 'upfw_avatar_max_kb' ) ) * KB_IN_BYTES;

	if ( (int) ( $file['size'] ?? 0 ) > $max ) {
		return new WP_Error(
			'upfw_avatar_big',
			sprintf(
				/* translators: %s: tamaño máximo, ya formateado */
				__( 'The photo is too heavy: at most %s.', 'users-plus-for-wordpress' ),
				size_format( $max )
			)
		);
	}

	// El tipo se mira por el contenido y no por el nombre del archivo: la
	// extensión la escribe quien sube.
	$type = wp_check_filetype_and_ext( $file['tmp_name'], (string) ( $file['name'] ?? '' ) );

	if ( empty( $type['type'] ) || ! in_array( $type['type'], upfw_avatar_types(), true ) ) {
		return new WP_Error( 'upfw_avatar_type', __( 'That is not a photo. It has to be a JPG, PNG, GIF or WebP.', 'users-plus-for-wordpress' ) );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$attachment_id = media_handle_sideload(
		array(
			'name'     => $file['name'],
			'tmp_name' => $file['tmp_name'],
		),
		0,
		null,
		array( 'post_author' => $user_id )
	);

	if ( is_wp_error( $attachment_id ) ) {
		return $attachment_id;
	}

	upfw_avatar_delete( $user_id );
	update_user_meta( $user_id, 'upfw_avatar', (int) $attachment_id );

	return (int) $attachment_id;
}

/**
 * Borra la foto de alguien.
 *
 * Se comprueba que el adjunto sea suyo antes de tocarlo: sin eso, una meta
 * con el id de la foto de otra persona borra la foto de esa otra persona.
 */
function upfw_avatar_delete( int $user_id ): void {
	$id = upfw_avatar_id( $user_id );

	if ( $id <= 0 ) {
		return;
	}

	if ( (int) get_post_field( 'post_author', $id ) === $user_id ) {
		wp_delete_attachment( $id, true );
	}

	delete_user_meta( $user_id, 'upfw_avatar' );
}

/** El formulario de la foto. Shortcode: [upfw_avatar] */
function upfw_shortcode_avatar(): string {
	if ( ! is_user_logged_in() || ! upfw_option( 'upfw_avatar_upload' ) ) {
		return '';
	}

	$user = wp_get_current_user();

	return upfw_render(
		'account/avatar',
		array(
			'user'  => $user,
			'has'   => upfw_avatar_id( $user->ID ) > 0,
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige el mensaje.
			'error' => isset( $_GET['upfw_avatar'] ) ? sanitize_text_field( wp_unslash( $_GET['upfw_avatar'] ) ) : '',
		)
	);
}
add_shortcode( 'upfw_avatar', 'upfw_shortcode_avatar' );

/** Recibe la foto o la saca. */
function upfw_avatar_submit(): void {
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( upfw_login_url() );
		exit;
	}

	check_admin_referer( 'upfw_avatar' );

	$user_id = get_current_user_id();
	$destino = upfw_account_url( 'details' );

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
	if ( isset( $_POST['upfw_avatar_remove'] ) ) {
		upfw_avatar_delete( $user_id );
		wp_safe_redirect( add_query_arg( 'upfw', 'saved', $destino ) );
		exit;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput -- lo valida upfw_avatar_upload().
	$result = upfw_avatar_upload( $user_id, (array) ( $_FILES['upfw_avatar_file'] ?? array() ) );

	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( add_query_arg( 'upfw_avatar', rawurlencode( $result->get_error_message() ), $destino ) );
		exit;
	}

	wp_safe_redirect( add_query_arg( 'upfw', 'saved', $destino ) );
	exit;
}
add_action( 'admin_post_upfw_avatar', 'upfw_avatar_submit' );
