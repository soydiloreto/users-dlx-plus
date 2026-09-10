<?php
/**
 * El área de cuenta: el registro de secciones y las URL.
 *
 * «Mi cuenta» no es de este sitio ni de este diseño: es de cualquier WordPress
 * con gente adentro. Por eso vive acá y no en el plugin del sitio, y por eso
 * las secciones son un registro y no un `switch` — LifterLMS agrega las suyas,
 * el sitio agrega las suyas, y ninguno tiene que editar este archivo.
 *
 * Lo que decide qué se ve y en qué orden es la suma de tres cosas:
 *   1. lo que registra cada quien con upfw_register_section(),
 *   2. lo que quien administra prendió, apagó, renombró o reordenó,
 *   3. las secciones propias que agregó desde el admin.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

/** El endpoint de reescritura: /cuenta/<seccion>/ */
const UPFW_ACCOUNT_VAR = 'upfw_section';

/**
 * Registra una sección del área de cuenta.
 *
 * @param string               $id   Identificador. Es lo que va en la URL.
 * @param array<string, mixed> $args label, render, position, capability, source.
 */
function upfw_register_section( string $id, array $args ): void {
	global $upfw_sections;

	$upfw_sections = is_array( $upfw_sections ) ? $upfw_sections : array();

	$upfw_sections[ $id ] = wp_parse_args(
		$args,
		array(
			'label'      => $id,
			// Función que pinta la sección. Recibe el WP_User.
			'render'     => '',
			// Menor va primero. Deja huecos de 10 para poder intercalar.
			'position'   => 50,
			// Vacío: la ve cualquiera con sesión iniciada.
			'capability' => '',
			// De dónde salió, para que la pantalla de admin lo diga.
			'source'     => __( 'System', 'users-plus-for-wordpress' ),
			// Las propias del sitio se pueden borrar; las de código, no.
			'custom'     => false,
			// Contenido escrito desde el admin. En una sección propia es todo lo
			// que hay; en una del sistema se suma a lo que pinta el código.
			'content'    => '',
			// Dónde va ese contenido respecto del que pinta el código:
			// 'before', 'after' o 'replace'. Sin código propio da igual.
			'placement'  => 'after',
			// Roles que la ven. Vacío: la ve cualquiera con sesión iniciada.
			'roles'      => array(),
			// Opcional: una función que dice si la sección tiene sentido hoy.
			// «Cuentas vinculadas» sin ninguna red prendida no tiene nada que
			// mostrar, y una sección vacía es peor que una sección que no está.
			'available'  => '',
			// Por qué no se está mostrando, en castellano, para el admin.
			'why'        => '',
			// Opcional: una función que devuelve una tarjeta para la portada de
			// la cuenta. Así el resumen lo arma cada sección con lo que sabe, en
			// vez de una portada que tenga que conocer a todas.
			'summary'    => '',
			// Lo que va en la URL. Por defecto el identificador, pero se cambia
			// desde el admin: el identificador es de código y va en inglés; la
			// dirección la lee la gente y va en el idioma del sitio.
			'slug'       => $id,
		)
	);
}

/** Lo que guardó quien administra para una sección. */
function upfw_section_config( string $id ): array {
	$all = (array) upfw_option( 'upfw_account_sections' );

	return isset( $all[ $id ] ) && is_array( $all[ $id ] ) ? $all[ $id ] : array();
}

/**
 * Todas las secciones, ya ordenadas y con lo que decidió quien administra.
 *
 * @param bool $all true para incluir las apagadas (lo necesita el admin).
 * @return array<string, array<string, mixed>>
 */
function upfw_sections( bool $all = false ): array {
	global $upfw_sections;

	// El registro se llena en un hook para que quien agrega una sección no
	// dependa de en qué orden se cargaron los plugins.
	do_action( 'upfw_register_sections' );

	$sections = is_array( $upfw_sections ) ? $upfw_sections : array();

	// Las secciones propias no están en ningún código: viven en la option.
	foreach ( (array) upfw_option( 'upfw_account_sections' ) as $id => $config ) {
		if ( ! isset( $sections[ $id ] ) && ! empty( $config['custom'] ) ) {
			upfw_register_section(
				(string) $id,
				array(
					'label'   => (string) ( $config['label'] ?? $id ),
					'source'  => __( 'Yours', 'users-plus-for-wordpress' ),
					'custom'  => true,
					'content' => (string) ( $config['content'] ?? '' ),
				)
			);
		}
	}

	$sections = is_array( $upfw_sections ) ? $upfw_sections : array();

	foreach ( $sections as $id => $section ) {
		$config = upfw_section_config( $id );

		if ( isset( $config['label'] ) && '' !== $config['label'] ) {
			$sections[ $id ]['label'] = (string) $config['label'];
		}

		if ( isset( $config['position'] ) && '' !== $config['position'] ) {
			$sections[ $id ]['position'] = (int) $config['position'];
		}

		if ( isset( $config['content'] ) ) {
			$sections[ $id ]['content'] = (string) $config['content'];
		}

		if ( isset( $config['placement'] ) && in_array( $config['placement'], array( 'before', 'after', 'replace' ), true ) ) {
			$sections[ $id ]['placement'] = (string) $config['placement'];
		}

		if ( isset( $config['roles'] ) ) {
			$sections[ $id ]['roles'] = array_values( array_filter( array_map( 'sanitize_key', (array) $config['roles'] ) ) );
		}

		if ( isset( $config['slug'] ) && '' !== $config['slug'] ) {
			$sections[ $id ]['slug'] = sanitize_title( (string) $config['slug'] );
		}

		$sections[ $id ]['enabled'] = ! isset( $config['enabled'] ) || (bool) $config['enabled'];
	}

	if ( ! $all ) {
		$sections = array_filter(
			$sections,
			static fn( array $s ): bool => $s['enabled']
				&& upfw_section_available( $s )
				&& ( '' === $s['capability'] || current_user_can( $s['capability'] ) )
				&& upfw_section_role_ok( $s )
		);
	}

	uasort( $sections, static fn( array $a, array $b ): int => $a['position'] <=> $b['position'] );

	/**
	 * Filtra las secciones del área de cuenta, ya ordenadas.
	 *
	 * @param array<string, array<string, mixed>> $sections
	 * @param bool                                $all
	 */
	return apply_filters( 'upfw_sections', $sections, $all );
}

/**
 * ¿Esta sección tiene algo que mostrar hoy?
 *
 * Es la regla que hace que lo que se apaga en el escritorio desaparezca del
 * frente sin que haya que acordarse de apagar también la sección: si el sitio
 * no tiene ninguna red social prendida, «Cuentas vinculadas» no existe.
 *
 * @param array<string, mixed> $section
 */
function upfw_section_available( array $section ): bool {
	$check = $section['available'] ?? '';

	return '' === $check || ! is_callable( $check ) || (bool) call_user_func( $check );
}

/**
 * ¿El rol de quien mira alcanza para ver esta sección?
 *
 * La lista vacía es «la ve cualquiera con sesión»: es lo que corresponde para
 * casi todo lo que hay en una cuenta —tus datos, tu seguridad— y hace que
 * elegir roles sea una decisión y no un trámite de alta.
 *
 * @param array<string, mixed> $section
 */
function upfw_section_role_ok( array $section ): bool {
	$roles = (array) ( $section['roles'] ?? array() );

	if ( array() === $roles ) {
		return true;
	}

	$user = wp_get_current_user();

	return $user instanceof WP_User && array() !== array_intersect( $roles, (array) $user->roles );
}

/**
 * ¿Esta sección la pinta el código, o es sólo lo que se escribió en el admin?
 *
 * De eso depende que tenga sentido preguntar dónde va el contenido propio: si
 * no hay nada del código, no hay un «antes» ni un «después» de nada.
 *
 * @param array<string, mixed> $section
 */
function upfw_section_has_code( array $section ): bool {
	return is_callable( $section['render'] ?? '' );
}

/**
 * Lo que se pinta adentro de una sección.
 *
 * Dos cosas pueden convivir: lo que sabe pintar el código —los datos, la
 * seguridad, los cursos de otro plugin— y lo que se escribió desde el admin.
 * Antes lo segundo pisaba a lo primero, que es la única combinación que nadie
 * pide: quien escribe un párrafo arriba de sus datos no quiere quedarse sin
 * los datos. Ahora se elige, y reemplazar sigue estando para quien lo quiera.
 *
 * @param array<string, mixed> $section
 */
function upfw_account_section_html( array $section, WP_User $user ): string {
	$codigo = '';

	if ( upfw_section_has_code( $section ) ) {
		ob_start();
		call_user_func( $section['render'], $user );
		$codigo = (string) ob_get_clean();
	}

	$propio = '' === (string) $section['content']
		? ''
		: do_shortcode( wp_kses_post( (string) $section['content'] ) );

	if ( '' === $codigo || '' === $propio ) {
		return $codigo . $propio;
	}

	switch ( (string) $section['placement'] ) {
		case 'before':
			return $propio . $codigo;

		case 'replace':
			return $propio;

		default:
			return $codigo . $propio;
	}
}

/** La primera sección que se muestra al entrar sin pedir ninguna. */
function upfw_default_section(): string {
	$sections = upfw_sections();

	return (string) ( array_key_first( $sections ) ?? '' );
}

/** La sección abierta ahora. */
function upfw_current_section(): string {
	$sections = upfw_sections();

	$asked = (string) get_query_var( UPFW_ACCOUNT_VAR, '' );

	if ( '' === $asked ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige qué pintar.
		$asked = isset( $_GET['seccion'] ) ? sanitize_title( wp_unslash( $_GET['seccion'] ) ) : '';
	}

	if ( '' === $asked ) {
		return upfw_default_section();
	}

	// Se busca por dirección, que es lo que escribió el browser. El
	// identificador sirve igual: es la dirección por defecto.
	foreach ( $sections as $id => $section ) {
		if ( $section['slug'] === $asked || $id === $asked ) {
			return (string) $id;
		}
	}

	return upfw_default_section();
}

/** La página de cuenta, o 0 si todavía no se eligió ninguna. */
function upfw_account_page_id(): int {
	return (int) upfw_option( 'upfw_account_page' );
}

/**
 * La URL de una sección.
 *
 * Con enlaces permanentes prendidos queda /cuenta/seguridad/; sin ellos, el
 * parámetro de siempre. No hay nada que configurar: se mira lo que hay.
 */
function upfw_account_url( string $section = '' ): string {
	$page = upfw_account_page_id();

	if ( $page <= 0 ) {
		return home_url( '/' );
	}

	$base = (string) get_permalink( $page );

	if ( '' === $section || $section === upfw_default_section() ) {
		return $base;
	}

	$sections = upfw_sections( true );
	$slug     = isset( $sections[ $section ] ) ? (string) $sections[ $section ]['slug'] : $section;

	if ( '' === (string) get_option( 'permalink_structure' ) ) {
		return add_query_arg( 'seccion', $slug, $base );
	}

	return trailingslashit( $base ) . $slug . '/';
}

/**
 * La regla que hace posible /cuenta/<seccion>/.
 *
 * Una regla propia y no add_rewrite_endpoint(): un endpoint pone su nombre en
 * la URL —quedaría /cuenta/upfw_section/seguridad/— y con uno por sección
 * habría que declarar de antemano cuántas hay, que es justo lo que este
 * registro evita.
 */
function upfw_account_rule(): void {
	$page = upfw_account_page_id();

	if ( $page <= 0 ) {
		return;
	}

	$uri = get_page_uri( $page );

	if ( ! is_string( $uri ) || '' === $uri ) {
		return;
	}

	add_rewrite_rule(
		'^' . preg_quote( $uri, '/' ) . '/([^/]+)/?$',
		'index.php?page_id=' . $page . '&' . UPFW_ACCOUNT_VAR . '=$matches[1]',
		'top'
	);
}
add_action( 'init', 'upfw_account_rule' );

/** Sin esto WordPress descarta el valor que capturó la regla. */
function upfw_account_query_var( array $vars ): array {
	$vars[] = UPFW_ACCOUNT_VAR;

	return $vars;
}
add_filter( 'query_vars', 'upfw_account_query_var' );

/**
 * Las reglas de reescritura se guardan una vez, no en cada pedido.
 *
 * Se regeneran cuando cambia la página de cuenta o cuando el plugin cambia de
 * versión, que son los dos momentos en los que el endpoint puede haber
 * quedado viejo.
 */
function upfw_account_flush_rules(): void {
	if ( get_option( 'upfw_rewrite_version' ) === UPFW_VERSION ) {
		return;
	}

	flush_rewrite_rules( false );
	update_option( 'upfw_rewrite_version', UPFW_VERSION );
}
add_action( 'wp_loaded', 'upfw_account_flush_rules' );

/**
 * El título de la sección que se está viendo.
 *
 * Lo pinta el área de cuenta y no cada plantilla, para que no haya dos formas
 * de escribir lo mismo: una sección que trae su propio <h2> termina con otra
 * tipografía y otro tamaño que las de al lado, y eso ya pasó. El texto es el
 * rótulo de la sección —el mismo que se lee en el menú, y el que se cambia
 * desde el admin— y lo que quiera decir otra cosa lo dice por el filtro.
 */
function upfw_account_heading( array $section, string $id, WP_User $user ): string {
	/**
	 * Filtra el título de una sección del área de cuenta.
	 *
	 * Devolver '' lo saca.
	 *
	 * @param string  $heading Texto del título.
	 * @param string  $id      Identificador de la sección.
	 * @param WP_User $user    Quién la está viendo.
	 */
	return (string) apply_filters( 'upfw_account_heading', (string) $section['label'], $id, $user );
}

/** El título, ya listo para imprimir. Vacío si la sección no lleva. */
function upfw_account_heading_html( array $section, string $id, WP_User $user ): string {
	$heading = upfw_account_heading( $section, $id, $user );

	return '' === $heading
		? ''
		: sprintf( '<h2 class="upfw-account__titulo">%s</h2>', esc_html( $heading ) );
}

/** ¿Estamos en el área de cuenta? */
function upfw_is_account(): bool {
	$page = upfw_account_page_id();

	return $page > 0 && is_page( $page );
}

/* ── Pintado ───────────────────────────────────────────────────────── */

/** El nombre visible de alguien: el que escribió, o el que haya. */
function upfw_display_name( WP_User $user ): string {
	$full = trim( $user->first_name . ' ' . $user->last_name );

	return '' !== $full ? $full : $user->display_name;
}

/** El nombre de pila, para saludar. */
function upfw_first_name( WP_User $user ): string {
	return '' !== $user->first_name ? $user->first_name : upfw_display_name( $user );
}

/** Las iniciales, para el avatar de letras. */
function upfw_initials( WP_User $user ): string {
	$parts = preg_split( '/\s+/', upfw_display_name( $user ), -1, PREG_SPLIT_NO_EMPTY );
	$parts = is_array( $parts ) ? $parts : array();

	$first = isset( $parts[0] ) ? mb_substr( $parts[0], 0, 1 ) : '';
	$last  = count( $parts ) > 1 ? mb_substr( (string) end( $parts ), 0, 1 ) : '';

	return mb_strtoupper( $first . $last );
}

/**
 * La navegación, aparte del área: el sitio puede querer ponerla en otro lado.
 *
 * @param array<string, array<string, mixed>>|null $sections
 */
function upfw_account_nav( ?array $sections = null, string $current = '' ): string {
	$sections = null === $sections ? upfw_sections() : $sections;
	$current  = '' === $current ? upfw_current_section() : $current;

	if ( array() === $sections ) {
		return '';
	}

	return upfw_render(
		'account-nav',
		array(
			'sections' => $sections,
			'current'  => $current,
		)
	);
}

/** El área de cuenta entera. Shortcode: [upfw_account] */
function upfw_shortcode_account(): string {
	if ( ! is_user_logged_in() ) {
		return upfw_render( 'account-guest', array( 'url' => upfw_login_url() ) );
	}

	$sections = upfw_sections();

	if ( array() === $sections ) {
		return '';
	}

	upfw_enqueue_styles();

	$current = upfw_current_section();

	return upfw_render(
		'account',
		array(
			'user'     => wp_get_current_user(),
			'sections' => $sections,
			'current'  => $current,
			'layout'   => (string) upfw_option( 'upfw_account_layout' ),
			'header'   => (bool) upfw_option( 'upfw_account_header' ),
		)
	);
}
add_shortcode( 'upfw_account', 'upfw_shortcode_account' );

/** Sólo la navegación. Shortcode: [upfw_account_nav] */
function upfw_shortcode_account_nav(): string {
	return is_user_logged_in() ? upfw_account_nav() : '';
}
add_shortcode( 'upfw_account_nav', 'upfw_shortcode_account_nav' );
