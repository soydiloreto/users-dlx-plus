<?php
/**
 * Plantillas sobrescribibles.
 *
 * El plugin trae su propio marcado para que caiga en cualquier sitio y ande,
 * pero un sitio con diseño propio tiene que poder reemplazarlo sin tocar el
 * plugin. Se busca, en orden:
 *
 *   1. El filtro `users_plus_template`, que gana siempre.
 *   2. wp-content/themes/<theme-hijo>/users-plus/<archivo>
 *   3. wp-content/themes/<theme>/users-plus/<archivo>
 *   4. La plantilla del plugin.
 *
 * Es el mismo mecanismo que usan bbPress y LifterLMS, por dos razones: es el
 * que la gente que instala plugins ya conoce, y no obliga a nadie a copiar
 * archivos dentro del plugin, que se pierden en la próxima actualización.
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;

/** Ruta del archivo de plantilla que hay que usar. */
function users_plus_template( string $file ): string {
	// Se acepta con extensión o sin ella: quien pide una plantilla piensa en
	// «account/home», no en un archivo. Y se compara con is_file() y no con
	// file_exists(), que también da verdadero para un directorio —y
	// «account» es un directorio de plantillas.
	$file = '.php' === substr( $file, -4 ) ? $file : $file . '.php';

	$candidates = array(
		get_stylesheet_directory() . '/users-plus/' . $file,
		get_template_directory() . '/users-plus/' . $file,
		USERS_PLUS_DIR . 'templates/' . $file,
	);

	$path = '';

	foreach ( $candidates as $candidata ) {
		if ( is_file( $candidata ) ) {
			$path = $candidata;
			break;
		}
	}

	/**
	 * Filtra qué archivo se usa para pintar algo del plugin.
	 *
	 * @param string $path    Ruta encontrada.
	 * @param string $file Nombre pedido, ya con extensión: "account/home.php".
	 */
	return (string) apply_filters( 'users_plus_template', $path, $file );
}

/**
 * Pinta una plantilla y devuelve lo que imprimió.
 *
 * Las variables llegan como variables sueltas, que es lo que espera quien
 * escribe una plantilla y no una clase.
 *
 * @param array<string, mixed> $data
 */
function users_plus_render( string $file, array $data = array() ): string {
	$path = users_plus_template( $file );

	if ( '' === $path ) {
		return '';
	}

	// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- es el contrato de una plantilla.
	extract( $data, EXTR_SKIP );

	ob_start();
	include $path;

	return (string) ob_get_clean();
}

/**
 * La versión con la que se pide un archivo del plugin.
 *
 * La fecha del archivo, no la versión del plugin: mientras se trabaja, la
 * versión no cambia y el navegador se queda con la hoja vieja. Eso hizo perder
 * una tarde discutiendo un cambio que estaba hecho y no se veía.
 *
 * @param string $file Ruta relativa dentro del plugin, p. ej. "assets/users-plus.css".
 */
function users_plus_asset_version( string $file ): string {
	$path = USERS_PLUS_DIR . $file;
	$time = is_file( $path ) ? (int) filemtime( $path ) : 0;

	return $time > 0 ? USERS_PLUS_VERSION . '.' . $time : USERS_PLUS_VERSION;
}

/** El color de acento que se está usando. */
function users_plus_style_accent(): string {
	$accent = trim( (string) users_plus_option( 'users_plus_style_accent' ) );

	return '' !== $accent ? $accent : '#2b59d6';
}

/**
 * La hoja de estilos del plugin, sólo si el sitio la quiere.
 *
 * Se registra y no se encola: la encola cada shortcode al pintarse, así una
 * página que no muestra nada del plugin no carga su CSS.
 *
 * Un sitio con diseño propio tiene dos caminos, y el primero suele alcanzar:
 * redefinir las propiedades `--users-plus-*` para que los componentes tomen sus
 * colores, o apagar la hoja entera y estilar las clases `users-plus-*` por su
 * cuenta.
 */
function users_plus_styles(): void {
	if ( ! users_plus_option( 'users_plus_styles' ) ) {
		return;
	}

	wp_register_style( 'users-plus', USERS_PLUS_URL . 'assets/users-plus.css', array(), users_plus_asset_version( 'assets/users-plus.css' ) );

	// Los dos valores que se eligen desde el admin viajan como propiedades, no
	// como reglas: no hay ningún archivo que generar ni que invalidar, y lo que
	// se toca es exactamente lo que el resto de la hoja ya estaba leyendo.
	$tokens = '';

	if ( '' !== trim( (string) users_plus_option( 'users_plus_style_accent' ) ) ) {
		$tokens .= '--users-plus-accent: ' . sanitize_hex_color( users_plus_style_accent() ) . ';';
	}

	$radius = (string) users_plus_option( 'users_plus_style_radius' );

	if ( '' !== trim( $radius ) ) {
		$tokens .= '--users-plus-radius: ' . (int) $radius . 'px;';
		$tokens .= '--users-plus-radius-sm: ' . max( 0, (int) $radius - 4 ) . 'px;';
	}

	if ( '' !== $tokens ) {
		wp_add_inline_style( 'users-plus', ':root{' . $tokens . '}' );
	}

	// Y si la página que se está pidiendo ya trae uno de nuestros shortcodes,
	// se encola acá. Encolarla sólo cuando el shortcode se pinta llega tarde
	// en los temas de bloques, que arman la plantilla en otro momento: en
	// Twenty Twenty-Five la hoja no salía y todo se veía en crudo.
	if ( users_plus_page_has_shortcode() ) {
		wp_enqueue_style( 'users-plus' );
	}
}
add_action( 'wp_enqueue_scripts', 'users_plus_styles', 5 );

/** ¿Lo que se va a pintar tiene alguno de los shortcodes del plugin? */
function users_plus_page_has_shortcode(): bool {
	$post = get_post();

	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	foreach ( array( 'users_plus_account', 'users_plus_account_nav', 'users_plus_login', 'users_plus_fields', 'users_plus_accounts', 'users_plus_sessions', 'users_plus_handle', 'users_plus_avatar', 'users_plus_notifications' ) as $shortcode ) {
		if ( has_shortcode( $post->post_content, $shortcode ) ) {
			return true;
		}
	}

	/**
	 * Filtra si esta página necesita la hoja del plugin.
	 *
	 * Un sitio que pinta los shortcodes desde una plantilla —y no desde el
	 * contenido— la prende por acá.
	 *
	 * @param bool    $needs
	 * @param WP_Post $post
	 */
	return (bool) apply_filters( 'users_plus_needs_styles', false, $post );
}

/** Encola la hoja, si está registrada. La llaman los shortcodes al pintarse. */
function users_plus_enqueue_styles(): void {
	if ( wp_style_is( 'users-plus', 'registered' ) ) {
		wp_enqueue_style( 'users-plus' );
	}
}

/**
 * Una caja que se abre y se cierra.
 *
 * Es un `<details>` y no un div con JavaScript: el navegador ya sabe abrirlo,
 * cerrarlo, enfocarlo con el teclado, buscar adentro con Ctrl+F aunque esté
 * cerrado, y abrirlo al imprimir. Todo eso habría que reescribirlo —mal— para
 * llegar al mismo lugar.
 *
 * @param string $title  El título de la caja.
 * @param bool   $open   Si arranca abierta.
 * @param string $classes Clases extra.
 */
function users_plus_panel_open( string $title, bool $open = false, string $classes = '' ): void {
	printf(
		'<details class="users-plus-panel %1$s"%2$s>'
			. '<summary class="users-plus-panel__cabeza">'
			. '<span class="users-plus-panel__titulo">%3$s</span>'
			// La flecha es un elemento y no un pseudo: así se puede dibujar
			// con dos bordes, que es lo único que sale nítido en cualquier
			// pantalla, y girarla al abrir.
			. '<span class="users-plus-panel__flecha" aria-hidden="true"></span>'
			. '</summary><div class="users-plus-panel__cuerpo">',
		esc_attr( $classes ),
		$open ? ' open' : '',
		esc_html( $title )
	);
}

/**
 * Cierra la caja abierta con users_plus_panel_open().
 *
 * Con `$guardar`, la caja termina con su propio botón. Como todas las cajas de
 * una pantalla viven dentro del mismo formulario, cualquiera de esos botones
 * manda la página entera: no hay que acordarse de cuál apretar ni volver
 * arriba a buscarlo.
 */
function users_plus_panel_close( string $guardar = '' ): void {
	if ( '' !== $guardar ) {
		printf(
			'<p class="users-plus-panel__guardar"><button type="submit" class="users-plus-button">%s</button></p>',
			esc_html( $guardar )
		);
	}

	echo '</div></details>';
}
