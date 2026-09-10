<?php
/**
 * Plugin Name:       Users Plus for WordPress
 * Plugin URI:        https://pablodiloreto.com
 * Description:       Campos de usuario, área de cuenta en el frente, acceso sin contraseña, login social, verificación en dos pasos, passkeys y control de sesiones. Con shortcodes y plantillas sobrescribibles para que entre en cualquier diseño.
 * Version:           0.2.1
 * Author:            Pablo Ariel Di Loreto
 * Author URI:        https://pablodiloreto.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       users-plus-for-wordpress
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 *
 * @package UPFW
 *
 * ---------------------------------------------------------------------------
 * Por qué existe
 *
 * Los datos de una persona, cómo entra y cuánto le dura la sesión son el mismo
 * problema en todos los sitios, y hasta ahora se resolvía de nuevo en cada uno.
 * Acá vive una vez.
 *
 * Qué resuelve: los campos que se le piden a una persona, el área de cuenta
 * en el frente, cómo entra —enlace por correo, contraseña, redes sociales,
 * passkeys—, el segundo factor, sus sesiones y sus datos.
 *
 * Lo que todavía no: suscripciones pagas. Eso arrastra pasarela, cobro
 * recurrente, reintentos y facturación, y va a entrar como complemento aparte
 * (`upfw-subscriptions`) para que un sitio gratuito no cargue con código de
 * cobro que no usa. El prefijo `upfw_` ya es el de la familia, así que las
 * claves de metadatos no se van a mover cuando llegue.
 * ---------------------------------------------------------------------------
 */

defined( 'ABSPATH' ) || exit;

define( 'UPFW_VERSION', '0.2.1' );
define( 'UPFW_DIR', plugin_dir_path( __FILE__ ) );
define( 'UPFW_URL', plugin_dir_url( __FILE__ ) );
define( 'UPFW_FILE', __FILE__ );

/**
 * Las traducciones.
 *
 * Las cadenas del código están en inglés y las traducciones viajan con el
 * plugin, en languages/. WordPress carga solo las de wordpress.org, que acá no
 * existen todavía.
 */
function upfw_load_textdomain(): void {
	load_plugin_textdomain(
		'users-plus-for-wordpress',
		false,
		dirname( plugin_basename( UPFW_FILE ) ) . '/languages'
	);
}
add_action( 'init', 'upfw_load_textdomain' );

/**
 * Cada archivo de includes/ es independiente y sólo registra hooks. Se cargan
 * por orden alfabético a propósito: si alguno necesitara a otro para arrancar,
 * eso sería un acoplamiento que hay que resolver con un hook, no con el orden.
 */
foreach ( (array) glob( UPFW_DIR . 'includes/*.php' ) as $upfw_archivo ) {
	require_once (string) $upfw_archivo;
}


/**
 * Al activar: los campos de arranque.
 *
 * La siembra en sí vive en includes/multisite.php, porque en una red hace
 * falta también cuando nace un sitio nuevo —donde este hook no corre— y no
 * conviene tener dos copias de la misma decisión.
 */
register_activation_hook( __FILE__, 'upfw_seed_fields' );
