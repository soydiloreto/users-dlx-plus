<?php
/**
 * Lo que hay que arreglar cuando el plugin cambia de versión.
 *
 * Por ahora hay una sola cosa, y es el prefijo. El plugin se llamó «User &
 * Subscription Manager» con `usmw_`, después «Users Plus for WordPress» con
 * `upfw_` y «Users Plus» con `users_plus_`, antes de quedar en «Users+» con
 * `users_dlx_plus_`. Los datos guardados llevan el prefijo de su época —las
 * options del sitio y la user meta de cada persona— y renombrar el código sin
 * renombrar los datos deja un sitio que arranca vacío: sin campos, sin passkeys, sin segundo factor y sin
 * las redes vinculadas de nadie.
 *
 * Así que se renombran los datos también, de cualquiera de los prefijos
 * viejos al de ahora, y se anota que ya se hizo. Corre en `admin_init` y no
 * en la activación porque una actualización por FTP o por git no dispara la
 * activación.
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;

/** La marca de que la mudanza ya se hizo en este sitio. */
const USERS_DLX_PLUS_MIGRATED = 'users_dlx_plus_migrated';

/** El prefijo actual, escrito una sola vez. */
const USERS_DLX_PLUS_PREFIJO = 'users_dlx_plus_';

/**
 * Los prefijos que este plugin usó antes, del más viejo al más nuevo.
 *
 * Un sitio puede venir de cualquiera de los dos: del original, o de la vuelta
 * intermedia. Los dos casos son el mismo trabajo.
 *
 * @return array<int, string>
 */
function users_dlx_plus_old_prefixes(): array {
	return array( 'usmw_', 'upfw_', 'users_plus_' );
}

/**
 * Suelta del caché sólo lo que la migración tocó.
 *
 * `wp_cache_flush()` sería una línea y vacía el caché de TODO el sitio: los
 * transients de los demás plugins incluidos. Uno de ellos guarda ahí que su
 * licencia está validada, y al perderlo volvió a pedir la clave. Un plugin no
 * tiene por qué tirar abajo el caché de un sitio entero para arreglar lo suyo.
 *
 * @param array<int, string> $options  Las options viejas, con su nombre anterior.
 * @param array<int, string> $usuarios Los ids cuya meta se renombró.
 * @param string             $viejo    El prefijo del que se viene.
 */
function users_dlx_plus_migration_forget_cache( array $options, array $usuarios, string $viejo ): void {
	// La lista completa de options la cachea WordPress en un solo bulto.
	wp_cache_delete( 'alloptions', 'options' );
	wp_cache_delete( 'notoptions', 'options' );

	foreach ( $options as $vieja ) {
		wp_cache_delete( (string) $vieja, 'options' );
		wp_cache_delete( USERS_DLX_PLUS_PREFIJO . substr( (string) $vieja, strlen( $viejo ) ), 'options' );
	}

	foreach ( $usuarios as $user_id ) {
		wp_cache_delete( (int) $user_id, 'user_meta' );
	}
}

/**
 * Renombra los datos que quedaron con un prefijo viejo.
 *
 * Se hace con SQL y no con la API de options y meta por una razón práctica:
 * hay una fila por persona y por clave, son 25.000 personas en el sitio que
 * originó esto, y leer y reescribir cada una de a una tarda minutos y se
 * corta a la mitad.
 */
function users_dlx_plus_migrate_prefix( string $viejo ): void {
	global $wpdb;

	$largo = strlen( $viejo );
	$desde = $largo + 1;
	$like  = str_replace( '_', '\\_', $viejo ) . '%';

	// Cuánto ocupa el prefijo actual, para recortar por él en SQL. Sale de la
	// constante y no de un número escrito a mano: el prefijo ya cambió tres
	// veces y un largo hardcodeado sobrevive callado a la siguiente.
	$desde_nuevo = strlen( USERS_DLX_PLUS_PREFIJO ) + 1;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- es una migración: una vez, sin entrada de nadie, y suelta del caché sólo lo suyo al final.

	// Las options tienen la clave única. Si el plugin ya sembró la suya —al
	// activarse, que pasa antes de esto— el UPDATE choca con esa fila y falla
	// ENTERO: no migra ninguna, y el sitio arranca vacío sin decir por qué.
	// Así que primero se saca la recién sembrada: la que vale es la vieja,
	// que tiene lo que el sitio configuró.
	$viejas = $wpdb->get_col(
		$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
	);

	if ( array() === (array) $viejas ) {
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return;
	}

	foreach ( (array) $viejas as $vieja ) {
		delete_option( USERS_DLX_PLUS_PREFIJO . substr( (string) $vieja, $largo ) );
	}

	// SUBSTRING desde el largo del prefijo y no REPLACE: REPLACE cambiaría
	// también un `usmw_` que apareciera en el medio de la clave, y acá lo que
	// se muda es el prefijo, no todas las apariciones.
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->options}
			    SET option_name = CONCAT( %s, SUBSTRING( option_name, %d ) )
			  WHERE option_name LIKE %s",
			USERS_DLX_PLUS_PREFIJO,
			$desde,
			$like
		)
	);

	$afectados = $wpdb->get_col(
		$wpdb->prepare( "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $like )
	);

	// La user meta no tiene clave única, pero el problema es el mismo: una
	// persona podría quedar con la vieja y la nueva, y `get_user_meta()`
	// devolvería cualquiera de las dos.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE nueva FROM {$wpdb->usermeta} nueva
			   INNER JOIN {$wpdb->usermeta} vieja
			           ON vieja.user_id = nueva.user_id
			          AND vieja.meta_key = CONCAT( %s, SUBSTRING( nueva.meta_key, %d ) )
			        WHERE nueva.meta_key LIKE %s",
			$viejo,
			$desde_nuevo,
			$wpdb->esc_like( USERS_DLX_PLUS_PREFIJO ) . '%'
		)
	);

	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->usermeta}
			    SET meta_key = CONCAT( %s, SUBSTRING( meta_key, %d ) )
			  WHERE meta_key LIKE %s",
			USERS_DLX_PLUS_PREFIJO,
			$desde,
			$like
		)
	);

	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	users_dlx_plus_migration_forget_cache( (array) $viejas, (array) $afectados, $viejo );

	// Las claves de los campos viajan además adentro de la definición, y son
	// las mismas con las que se guardó el valor de cada persona: si no se
	// mudan las dos, los campos quedan mirando a una meta que ya no existe.
	$fields = get_option( 'users_dlx_plus_fields', false );

	if ( is_array( $fields ) ) {
		foreach ( $fields as $i => $field ) {
			if ( isset( $field['key'] ) && 0 === strpos( (string) $field['key'], $viejo ) ) {
				$fields[ $i ]['key'] = USERS_DLX_PLUS_PREFIJO . substr( (string) $field['key'], $largo );
			}
		}

		update_option( 'users_dlx_plus_fields', $fields );
	}

	// Y los shortcodes escritos adentro de las páginas.
	users_dlx_plus_migrate_shortcodes( $viejo );
}

/**
 * Los shortcodes que quedaron escritos en el contenido de una página.
 *
 * `[usmw_account]` no lo pinta nadie después del renombre: sale el texto tal
 * cual, y quien mira la página de su cuenta ve el corchete.
 */
function users_dlx_plus_migrate_shortcodes( string $viejo ): void {
	global $wpdb;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ídem.
	$ids = $wpdb->get_col(
		$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE %s", '%[' . $wpdb->esc_like( $viejo ) . '%' )
	);

	if ( array() === (array) $ids ) {
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return;
	}

	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->posts}
			    SET post_content = REPLACE( post_content, %s, %s )
			  WHERE post_content LIKE %s",
			'[' . $viejo,
			'[' . USERS_DLX_PLUS_PREFIJO,
			'%[' . $wpdb->esc_like( $viejo ) . '%'
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	// Sin esto WordPress sigue sirviendo el contenido viejo desde el caché de
	// objetos, y la página muestra el shortcode escrito en vez de la cuenta.
	foreach ( (array) $ids as $id ) {
		clean_post_cache( (int) $id );
	}
}

/** Corre la mudanza una sola vez, desde cualquier prefijo anterior. */
function users_dlx_plus_migrate(): void {
	if ( get_option( USERS_DLX_PLUS_MIGRATED ) ) {
		return;
	}

	foreach ( users_dlx_plus_old_prefixes() as $viejo ) {
		users_dlx_plus_migrate_prefix( $viejo );
	}

	update_option( USERS_DLX_PLUS_MIGRATED, 1 );
}
add_action( 'admin_init', 'users_dlx_plus_migrate', 0 );
add_action( 'wp_initialize_site', 'users_dlx_plus_migrate' );
