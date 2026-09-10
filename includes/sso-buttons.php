<?php
/**
 * Los botones de las redes: cómo se ven y cómo se pintan.
 *
 * El marcado lo arma el plugin —logo, texto, clases— y la apariencia la
 * eligen los ajustes: quien administra no debería tener que escribir CSS para
 * que los botones dejen de ser un enlace pelado.
 *
 * La hoja de estilos de los botones se encola siempre que se pintan, incluso
 * con los estilos del plugin apagados: el color de marca y la forma no son la
 * decoración del plugin, son la opción que quien administra acaba de elegir.
 * El sitio la puede pisar; apagarla en silencio sería otra cosa.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

/** Los tres acabados posibles, con su nombre para la pantalla de ajustes. */
function upfw_sso_button_skins(): array {
	return array(
		'brand' => __( 'Each brand’s colour', 'users-plus-for-wordpress' ),
		'light' => __( 'White with a border', 'users-plus-for-wordpress' ),
		'dark'  => __( 'Dark', 'users-plus-for-wordpress' ),
	);
}

/** Las formas posibles. */
function upfw_sso_button_shapes(): array {
	return array(
		'rounded' => __( 'Rounded corners', 'users-plus-for-wordpress' ),
		'pill'    => __( 'Pill', 'users-plus-for-wordpress' ),
		'square'  => __( 'Square corners', 'users-plus-for-wordpress' ),
	);
}

/** Qué muestra el botón. */
function upfw_sso_button_contents(): array {
	return array(
		'icon-text' => __( 'Logo and text', 'users-plus-for-wordpress' ),
		'icon'      => __( 'Logo only', 'users-plus-for-wordpress' ),
	);
}

/** Cuántos por fila. */
function upfw_sso_button_columns(): array {
	return array(
		1 => __( 'One per row', 'users-plus-for-wordpress' ),
		2 => __( 'Two per row', 'users-plus-for-wordpress' ),
		0 => __( 'As many as fit', 'users-plus-for-wordpress' ),
	);
}

/** El texto de un botón, con la plantilla de los ajustes. */
function upfw_sso_button_text( array $provider ): string {
	$template = trim( (string) upfw_option( 'upfw_sso_button_text' ) );

	if ( '' === $template ) {
		/* translators: %s: nombre de la red social */
		$template = __( 'Continue with %s', 'users-plus-for-wordpress' );
	}

	return false === strpos( $template, '%s' )
		? $template
		: sprintf( $template, $provider['name'] );
}

/** Las clases del contenedor, según los ajustes. */
function upfw_sso_buttons_class(): string {
	$columns = (int) upfw_option( 'upfw_sso_button_columns' );

	return sprintf(
		'upfw-socials upfw-socials--%1$s upfw-socials--%2$s upfw-socials--%3$s upfw-socials--cols-%4$d',
		sanitize_html_class( (string) upfw_option( 'upfw_sso_button_skin' ) ),
		sanitize_html_class( (string) upfw_option( 'upfw_sso_button_shape' ) ),
		sanitize_html_class( (string) upfw_option( 'upfw_sso_button_show' ) ),
		in_array( $columns, array( 0, 1, 2 ), true ) ? $columns : 2
	);
}

/**
 * Un botón.
 *
 * Con «sólo logo» el nombre sigue en el marcado, escondido para la vista y
 * disponible para un lector de pantalla: un botón sin nombre accesible es un
 * enlace que no se puede leer.
 *
 * @param string               $id       Identificador del proveedor.
 * @param array<string, mixed> $provider Su fila de la tabla.
 * @param string               $url      Adónde va. Vacío para la vista previa.
 */
function upfw_sso_button( string $id, array $provider, string $url = '' ): string {
	$text = upfw_sso_button_text( $provider );
	$icon = upfw_sso_icon( $id );

	return sprintf(
		'<a class="upfw-social upfw-social--%1$s" style="--upfw-brand: %2$s" href="%3$s"%4$s>%5$s<span class="upfw-social__text">%6$s</span></a>',
		esc_attr( $id ),
		esc_attr( $provider['color'] ),
		'' === $url ? '#' : esc_url( $url ),
		'' === $url ? ' tabindex="-1" aria-hidden="true"' : '',
		$icon,
		esc_html( $text )
	);
}

/**
 * Todos los botones que hay para mostrar.
 *
 * @param array<string, array<string, mixed>>|null $providers Para la vista
 *        previa del admin; si no se pasa, los que están prendidos.
 * @param bool                                     $live      Si los enlaces entran de verdad.
 */
function upfw_sso_buttons( ?array $providers = null, bool $live = true ): string {
	$providers = null === $providers ? upfw_sso_available() : $providers;

	if ( array() === $providers ) {
		return '';
	}

	upfw_sso_enqueue_button_styles();

	$html = '';

	foreach ( $providers as $id => $provider ) {
		$html .= upfw_sso_button( $id, $provider, $live ? upfw_sso_login_url( $id ) : '' );
	}

	return sprintf( '<div class="%1$s">%2$s</div>', esc_attr( upfw_sso_buttons_class() ), $html );
}

/** La hoja de los botones. Se encola una sola vez, y tarde: puede pintarse desde un shortcode. */
function upfw_sso_enqueue_button_styles(): void {
	if ( wp_style_is( 'upfw-social', 'enqueued' ) ) {
		return;
	}

	wp_enqueue_style( 'upfw-social', UPFW_URL . 'assets/upfw-social.css', array(), upfw_asset_version( 'assets/upfw-social.css' ) );
}
