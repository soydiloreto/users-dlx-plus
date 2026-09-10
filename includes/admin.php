<?php
/**
 * El menú y lo que comparten sus pantallas.
 *
 * Cada entrada del menú es una pantalla con vida propia y sus propias solapas.
 * Repetir el menú como solapas en todas —que es lo que hacía antes— no aporta
 * nada: la navegación ya está a la izquierda, y ese lugar sirve para las
 * secciones de la pantalla en la que uno está.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

const UPFW_MENU = 'upfw';

/**
 * El nombre con el que se presenta el plugin en el escritorio.
 *
 * Se escribe una sola vez: lo usan el menú, el título de cada pantalla y la
 * pestaña del navegador. Escrito en tres lados, tarde o temprano dicen tres
 * cosas distintas.
 */
function upfw_plugin_name(): string {
	return (string) apply_filters( 'upfw_plugin_name', __( 'Users+', 'users-plus-for-wordpress' ) );
}

/**
 * El título de una pantalla, con el nombre del plugin adelante.
 *
 * En un escritorio con veinte plugins, «Campos de usuario» no dice de quién
 * es esa pantalla. «Usuarios+ | Campos de usuario», sí.
 */
function upfw_screen_title( string $title ): string {
	return sprintf(
		/* translators: 1: nombre del plugin, 2: nombre de la pantalla */
		_x( '%1$s | %2$s', 'título de una pantalla del escritorio', 'users-plus-for-wordpress' ),
		upfw_plugin_name(),
		$title
	);
}

/**
 * Lo mismo, en la pestaña del navegador.
 *
 * Se reemplaza el nombre de la pantalla adentro del título que arma
 * WordPress, para no quedarse con el resto —el nombre del sitio y el
 * «WordPress» del final— que es de él y no nuestro.
 */
function upfw_admin_title( string $admin_title, string $title ): string {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen instanceof WP_Screen || false === strpos( (string) $screen->id, UPFW_MENU ) ) {
		return $admin_title;
	}

	return str_replace( $title, upfw_screen_title( $title ), $admin_title );
}
add_filter( 'admin_title', 'upfw_admin_title', 10, 2 );

/** Las pantallas del menú, en orden. */
function upfw_screens(): array {
	return array(
		'upfw'            => __( 'Overview', 'users-plus-for-wordpress' ),
		'upfw-fields'     => __( 'User fields', 'users-plus-for-wordpress' ),
		'upfw-account'    => __( 'Account area', 'users-plus-for-wordpress' ),
		'upfw-login'      => __( 'Registration and login', 'users-plus-for-wordpress' ),
		'upfw-social'     => __( 'Social login', 'users-plus-for-wordpress' ),
		'upfw-sessions'   => __( 'User sessions', 'users-plus-for-wordpress' ),
		'upfw-appearance' => __( 'Appearance', 'users-plus-for-wordpress' ),
	);
}

function upfw_menu(): void {
	add_menu_page(
		upfw_plugin_name(),
		upfw_plugin_name(),
		'manage_options',
		UPFW_MENU,
		'upfw_screen_home',
		'dashicons-groups',
		71
	);

	$callbacks = array(
		'upfw'            => 'upfw_screen_home',
		'upfw-fields'     => 'upfw_screen_fields',
		'upfw-account'    => 'upfw_screen_account',
		'upfw-login'      => 'upfw_screen_login',
		'upfw-social'     => 'upfw_screen_social',
		'upfw-sessions'   => 'upfw_screen_sessions',
		'upfw-appearance' => 'upfw_screen_appearance',
	);

	foreach ( upfw_screens() as $slug => $title ) {
		add_submenu_page( UPFW_MENU, $title, $title, 'manage_options', $slug, $callbacks[ $slug ] );
	}
}
add_action( 'admin_menu', 'upfw_menu' );

/** La URL de una pantalla del plugin, con los argumentos que haga falta. */
function upfw_admin_url( string $screen, array $args = array() ): string {
	return add_query_arg( array_merge( array( 'page' => $screen ), $args ), admin_url( 'admin.php' ) );
}

/**
 * La solapa activa dentro de una pantalla.
 *
 * @param array<string, string> $tabs
 */
function upfw_tab( array $tabs ): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

	return isset( $tabs[ $tab ] ) ? $tab : (string) array_key_first( $tabs );
}

/**
 * Las solapas de una pantalla.
 *
 * @param array<string, string> $tabs
 */
function upfw_tabs( string $screen, array $tabs, string $current, array $extra = array() ): void {
	if ( count( $tabs ) < 2 ) {
		return;
	}

	echo '<nav class="nav-tab-wrapper wp-clearfix">';

	foreach ( $tabs as $slug => $title ) {
		printf(
			'<a class="nav-tab%1$s" href="%2$s">%3$s</a>',
			$slug === $current ? ' nav-tab-active' : '',
			esc_url( upfw_admin_url( $screen, array_merge( $extra, array( 'tab' => $slug ) ) ) ),
			esc_html( $title )
		);
	}

	echo '</nav>';
}

/** La cabecera común: título y, si hay, solapas. */
function upfw_screen_open( string $title, string $screen = '', array $tabs = array(), string $current = '', array $extra = array() ): void {
	echo '<div class="wrap upfw-admin">';
	printf( '<h1>%s</h1>', esc_html( upfw_screen_title( $title ) ) );

	if ( array() !== $tabs ) {
		upfw_tabs( $screen, $tabs, $current, $extra );
	}
}

function upfw_screen_close(): void {
	echo '</div>';
}

/** Un aviso corto arriba de la pantalla. */
function upfw_notice( string $text, string $type = 'success' ): void {
	printf(
		'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
		esc_attr( $type ),
		esc_html( $text )
	);
}

/**
 * El aviso de que un ajuste lo fija el código, con quién lo fija.
 *
 * Va en admin.php y no en cada pantalla porque es el mismo aviso siempre, y
 * porque el día que se sume un tercer ajuste fijable no hay que acordarse de
 * copiar el texto bien.
 */
function upfw_forzado_aviso( string $key ): void {
	if ( ! upfw_option_forced( $key ) ) {
		return;
	}

	$quienes = upfw_option_forced_by();
	?>
	<div class="upfw-forzado">
		<p><?php esc_html_e( 'This site fixes this from code: whatever is chosen here, it stays as it is.', 'users-plus-for-wordpress' ); ?></p>

		<?php if ( array() !== $quienes ) : ?>
			<p><?php esc_html_e( 'It is filtered here — open the file to change it or take it out:', 'users-plus-for-wordpress' ); ?></p>
			<ul>
				<?php foreach ( $quienes as $quien ) : ?>
					<li><code><?php echo esc_html( $quien ); ?></code></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
}

/** Un párrafo de explicación, con ancho de lectura. */
function upfw_intro( string $text ): void {
	printf( '<p class="upfw-admin__intro">%s</p>', esc_html( $text ) );
}

/** Los estilos del admin del plugin. */
function upfw_admin_styles( string $hook ): void {
	if ( false === strpos( $hook, 'upfw' ) ) {
		return;
	}

	wp_enqueue_style( 'upfw-admin', UPFW_URL . 'assets/upfw-admin.css', array(), upfw_asset_version( 'assets/upfw-admin.css' ) );
	wp_enqueue_script( 'upfw-admin', UPFW_URL . 'assets/upfw-admin.js', array(), upfw_asset_version( 'assets/upfw-admin.js' ), true );

	// La vista previa de los botones usa la hoja de verdad, la misma que el
	// sitio: previsualizar con otra sería previsualizar otra cosa.
	upfw_sso_enqueue_button_styles();
}
add_action( 'admin_enqueue_scripts', 'upfw_admin_styles' );
