<?php
/**
 * El menú y lo que comparten sus pantallas.
 *
 * Cada entrada del menú es una pantalla con vida propia y sus propias solapas.
 * Repetir el menú como solapas en todas —que es lo que hacía antes— no aporta
 * nada: la navegación ya está a la izquierda, y ese lugar sirve para las
 * secciones de la pantalla en la que uno está.
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;

const USERS_DLX_PLUS_MENU = 'users-dlx-plus';

/**
 * El nombre con el que se presenta el plugin en el escritorio.
 *
 * Se escribe una sola vez: lo usan el menú, el título de cada pantalla y la
 * pestaña del navegador. Escrito en tres lados, tarde o temprano dicen tres
 * cosas distintas.
 */
function users_dlx_plus_plugin_name(): string {
	return (string) apply_filters( 'users_dlx_plus_plugin_name', __( 'Users+', 'users-dlx-plus' ) );
}

/**
 * El título de una pantalla, con el nombre del plugin adelante.
 *
 * En un escritorio con veinte plugins, «Campos de usuario» no dice de quién
 * es esa pantalla. «Usuarios+ | Campos de usuario», sí.
 */
function users_dlx_plus_screen_title( string $title ): string {
	return sprintf(
		/* translators: 1: nombre del plugin, 2: nombre de la pantalla */
		_x( '%1$s | %2$s', 'título de una pantalla del escritorio', 'users-dlx-plus' ),
		users_dlx_plus_plugin_name(),
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
function users_dlx_plus_admin_title( string $admin_title, string $title ): string {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen instanceof WP_Screen || false === strpos( (string) $screen->id, USERS_DLX_PLUS_MENU ) ) {
		return $admin_title;
	}

	return str_replace( $title, users_dlx_plus_screen_title( $title ), $admin_title );
}
add_filter( 'admin_title', 'users_dlx_plus_admin_title', 10, 2 );

/** Las pantallas del menú, en orden. */
/**
 * @return array<string, mixed>
 */
function users_dlx_plus_screens(): array {
	return array(
		'users-dlx-plus'            => __( 'Overview', 'users-dlx-plus' ),
		'users-dlx-plus-fields'     => __( 'User fields', 'users-dlx-plus' ),
		'users-dlx-plus-account'    => __( 'Account area', 'users-dlx-plus' ),
		'users-dlx-plus-login'      => __( 'Registration and login', 'users-dlx-plus' ),
		'users-dlx-plus-social'     => __( 'Social login', 'users-dlx-plus' ),
		'users-dlx-plus-sessions'   => __( 'User sessions', 'users-dlx-plus' ),
		'users-dlx-plus-appearance' => __( 'Appearance', 'users-dlx-plus' ),
	);
}

/** Menu. */
function users_dlx_plus_menu(): void {
	add_menu_page(
		users_dlx_plus_plugin_name(),
		users_dlx_plus_plugin_name(),
		'manage_options',
		USERS_DLX_PLUS_MENU,
		'users_dlx_plus_screen_home',
		'dashicons-groups',
		71
	);

	$callbacks = array(
		'users-dlx-plus'            => 'users_dlx_plus_screen_home',
		'users-dlx-plus-fields'     => 'users_dlx_plus_screen_fields',
		'users-dlx-plus-account'    => 'users_dlx_plus_screen_account',
		'users-dlx-plus-login'      => 'users_dlx_plus_screen_login',
		'users-dlx-plus-social'     => 'users_dlx_plus_screen_social',
		'users-dlx-plus-sessions'   => 'users_dlx_plus_screen_sessions',
		'users-dlx-plus-appearance' => 'users_dlx_plus_screen_appearance',
	);

	foreach ( users_dlx_plus_screens() as $slug => $title ) {
		add_submenu_page( USERS_DLX_PLUS_MENU, $title, $title, 'manage_options', $slug, $callbacks[ $slug ] );
	}
}
add_action( 'admin_menu', 'users_dlx_plus_menu' );

/** La URL de una pantalla del plugin, con los argumentos que haga falta. */
/**
 * @param array<string, mixed> $args
 */
function users_dlx_plus_admin_url( string $screen, array $args = array() ): string {
	return add_query_arg( array_merge( array( 'page' => $screen ), $args ), admin_url( 'admin.php' ) );
}

/**
 * La solapa activa dentro de una pantalla.
 *
 * @param array<string, string> $tabs
 */
function users_dlx_plus_tab( array $tabs ): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

	return isset( $tabs[ $tab ] ) ? $tab : (string) array_key_first( $tabs );
}

/**
 * Las solapas de una pantalla.
 *
 * @param array<string, string> $tabs
 * @param array<string, mixed>  $extra
 */
function users_dlx_plus_tabs( string $screen, array $tabs, string $current, array $extra = array() ): void {
	if ( count( $tabs ) < 2 ) {
		return;
	}

	echo '<nav class="nav-tab-wrapper wp-clearfix">';

	foreach ( $tabs as $slug => $title ) {
		printf(
			'<a class="nav-tab%1$s" href="%2$s">%3$s</a>',
			$slug === $current ? ' nav-tab-active' : '',
			esc_url( users_dlx_plus_admin_url( $screen, array_merge( $extra, array( 'tab' => $slug ) ) ) ),
			esc_html( $title )
		);
	}

	echo '</nav>';
}

/** La cabecera común: título y, si hay, solapas. */
/**
 * @param array<string, mixed> $tabs
 */
/**
 * @param array<string, mixed> $extra
 * @param array<string, mixed> $tabs
 */
function users_dlx_plus_screen_open( string $title, string $screen = '', array $tabs = array(), string $current = '', array $extra = array() ): void {
	echo '<div class="wrap users-dlx-plus-admin">';
	printf( '<h1>%s</h1>', esc_html( users_dlx_plus_screen_title( $title ) ) );

	if ( array() !== $tabs ) {
		users_dlx_plus_tabs( $screen, $tabs, $current, $extra );
	}
}

/** Screen close. */
function users_dlx_plus_screen_close(): void {
	echo '</div>';
}

/** Un aviso corto arriba de la pantalla. */
function users_dlx_plus_notice( string $text, string $type = 'success' ): void {
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
function users_dlx_plus_forzado_aviso( string $key ): void {
	if ( ! users_dlx_plus_option_forced( $key ) ) {
		return;
	}

	$quienes = users_dlx_plus_option_forced_by();
	?>
	<div class="users-dlx-plus-forzado">
		<p><?php esc_html_e( 'This site fixes this from code: whatever is chosen here, it stays as it is.', 'users-dlx-plus' ); ?></p>

		<?php if ( array() !== $quienes ) : ?>
			<p><?php esc_html_e( 'It is filtered here — open the file to change it or take it out:', 'users-dlx-plus' ); ?></p>
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
function users_dlx_plus_intro( string $text ): void {
	printf( '<p class="users-dlx-plus-admin__intro">%s</p>', esc_html( $text ) );
}

/** Los estilos del admin del plugin. */
function users_dlx_plus_admin_styles( string $hook ): void {
	if ( false === strpos( $hook, 'users-dlx-plus' ) ) {
		return;
	}

	wp_enqueue_style( 'users-dlx-plus-admin', USERS_DLX_PLUS_URL . 'assets/users-dlx-plus-admin.css', array(), users_dlx_plus_asset_version( 'assets/users-dlx-plus-admin.css' ) );
	wp_enqueue_script( 'users-dlx-plus-admin', USERS_DLX_PLUS_URL . 'assets/users-dlx-plus-admin.js', array(), users_dlx_plus_asset_version( 'assets/users-dlx-plus-admin.js' ), true );

	// La vista previa de los botones usa la hoja de verdad, la misma que el
	// sitio: previsualizar con otra sería previsualizar otra cosa.
	users_dlx_plus_sso_enqueue_button_styles();
}
add_action( 'admin_enqueue_scripts', 'users_dlx_plus_admin_styles' );
