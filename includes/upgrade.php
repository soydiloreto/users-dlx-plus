<?php
/**
 * Lo que hay que arreglar cuando el plugin cambia de versión.
 *
 * Por ahora hay una sola cosa: el plugin se llamaba «User & Subscription
 * Manager» y su prefijo era `usmw_`. Los datos guardados llevan ese prefijo
 * —las options del sitio y las user meta de cada persona— y renombrar el
 * código sin renombrar los datos deja un sitio que arranca vacío: sin campos,
 * sin passkeys, sin segundo factor y sin las redes vinculadas de nadie.
 *
 * Así que se renombran los datos también, una sola vez, y se anota que ya se
 * hizo. Corre en `admin_init` y no en la activación porque una actualización
 * por FTP o por git no dispara la activación.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

/** La marca de que la mudanza ya se hizo en este sitio. */
const UPFW_MIGRATED = 'upfw_migrated_from_usmw';

/**
 * Suelta del caché sólo lo que la migración tocó.
 *
 * `wp_cache_flush()` sería una línea y vacía el caché de TODO el sitio: los
 * transients de los demás plugins incluidos. Uno de ellos guarda ahí que su
 * licencia está validada, y al perderlo volvió a pedir la clave. Un plugin no
 * tiene por qué tirar abajo el caché de un sitio entero para arreglar lo suyo.
 *
 * @param array<int, string> $options   Las options viejas, con su nombre anterior.
 * @param array<int, string> $usuarios  Los ids cuya meta se renombró.
 */
function upfw_migration_forget_cache( array $options, array $usuarios ): void {
	// La lista completa de options la cachea WordPress en un solo bulto.
	wp_cache_delete( 'alloptions', 'options' );
	wp_cache_delete( 'notoptions', 'options' );

	foreach ( $options as $vieja ) {
		wp_cache_delete( (string) $vieja, 'options' );
		wp_cache_delete( 'upfw_' . substr( (string) $vieja, 5 ), 'options' );
	}

	foreach ( $usuarios as $user_id ) {
		wp_cache_delete( (int) $user_id, 'user_meta' );
	}
}

/**
 * Renombra los datos que quedaron con el prefijo viejo.
 *
 * Se hace con SQL y no con la API de options y meta por una razón práctica:
 * hay una fila por persona y por clave, son 25.000 personas en el sitio que
 * originó esto, y leer y reescribir cada una de a una tarda minutos y se
 * corta a la mitad.
 */
function upfw_migrate_from_usmw(): void {
	global $wpdb;

	if ( get_option( UPFW_MIGRATED ) ) {
		return;
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- es una migración: una vez, sin entrada de nadie, y suelta del caché sólo lo suyo al final.

	// Las options tienen la clave única. Si el plugin ya sembró la suya —al
	// activarse, que pasa antes de esto— el UPDATE choca con esa fila y falla
	// ENTERO: no migra ninguna, y el sitio arranca vacío sin decir por qué.
	// Así que primero se saca la recién sembrada: la que vale es la vieja,
	// que tiene lo que el sitio configuró.
	$viejas = $wpdb->get_col(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'usmw\\_%'"
	);

	foreach ( (array) $viejas as $vieja ) {
		delete_option( 'upfw_' . substr( (string) $vieja, 5 ) );
	}

	// SUBSTRING desde el 6 y no REPLACE: REPLACE cambiaría también un `usmw_`
	// que apareciera en el medio de la clave, y acá lo que se muda es el
	// prefijo, no todas las apariciones.
	$wpdb->query(
		"UPDATE {$wpdb->options}
		    SET option_name = CONCAT( 'upfw_', SUBSTRING( option_name, 6 ) )
		  WHERE option_name LIKE 'usmw\\_%'"
	);

	$afectados = $wpdb->get_col(
		"SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key LIKE 'usmw\\_%'"
	);

	// La user meta no tiene clave única, pero el problema es el mismo: una
	// persona podría quedar con la vieja y la nueva, y `get_user_meta()`
	// devolvería cualquiera de las dos.
	$wpdb->query(
		"DELETE nueva FROM {$wpdb->usermeta} nueva
		   INNER JOIN {$wpdb->usermeta} vieja
		           ON vieja.user_id = nueva.user_id
		          AND vieja.meta_key = CONCAT( 'usmw_', SUBSTRING( nueva.meta_key, 6 ) )
		        WHERE nueva.meta_key LIKE 'upfw\\_%'"
	);

	$wpdb->query(
		"UPDATE {$wpdb->usermeta}
		    SET meta_key = CONCAT( 'upfw_', SUBSTRING( meta_key, 6 ) )
		  WHERE meta_key LIKE 'usmw\\_%'"
	);

	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	upfw_migration_forget_cache( (array) $viejas, (array) $afectados );

	// Las claves de los campos viajan además adentro de la definición, y son
	// las mismas con las que se guardó el valor de cada persona: si no se
	// mudan las dos, los campos quedan mirando a una meta que ya no existe.
	$fields = get_option( 'upfw_fields', false );

	if ( is_array( $fields ) ) {
		foreach ( $fields as $i => $field ) {
			if ( isset( $field['key'] ) && 0 === strpos( (string) $field['key'], 'usmw_' ) ) {
				$fields[ $i ]['key'] = 'upfw_' . substr( (string) $field['key'], 5 );
			}
		}

		update_option( 'upfw_fields', $fields );
	}

	update_option( UPFW_MIGRATED, 1 );
}
add_action( 'admin_init', 'upfw_migrate_from_usmw', 0 );
add_action( 'wp_initialize_site', 'upfw_migrate_from_usmw' );
