<?php
/**
 * Los ajustes del plugin, con sus valores por defecto en un solo lugar.
 *
 * Todo lo que en otros plugins es una constante o un número escrito en el
 * código vive acá y se edita desde el admin.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

/**
 * Valores por defecto. La clave es el nombre de la option, con prefijo.
 *
 * @return array<string, mixed>
 */
function upfw_option_defaults(): array {
	return array(
		// ── Acceso sin contraseña ─────────────────────────────────────
		// Cómo entra la gente a este sitio:
		// 'link'     sólo el enlace por correo; se cierra el formulario de
		// usuario y contraseña de wp-login.php.
		// 'password' sólo usuario y contraseña, el de WordPress de siempre.
		// 'both'     las dos cosas, una debajo de la otra.
		'upfw_login_method'       => 'both',
		// Página con el formulario de acceso (el shortcode [upfw_login] o el
		// que ponga el sitio). En 0 se usa wp-login.php.
		'upfw_login_page'         => 0,
		// Minutos que vale el enlace del correo.
		'upfw_login_expiry'       => 15,
		// Segundos entre dos pedidos para el mismo correo.
		'upfw_login_throttle'     => 60,
		// Crear la cuenta si el correo no existe. Apagado, el enlace sólo
		// sirve para quien ya está registrado.
		'upfw_login_register'     => 1,
		// Rol de las cuentas creadas así.
		'upfw_login_role'         => 'subscriber',
		'upfw_login_subject'      => '',
		'upfw_login_body'         => '',

		// ── Duración de la sesión ─────────────────────────────────────
		'upfw_session_long_days'  => 30,  // Con "recordarme".
		'upfw_session_short_days' => 2,   // Sin "recordarme".

		// ── Login social ──────────────────────────────────────────────
		// Si el correo que devuelve la red ya existe en el sitio, esa cuenta
		// es de la misma persona y se vinculan. Es lo que hace que entrar con
		// Google hoy y con GitHub mañana sea la misma cuenta.
		'upfw_sso_link_by_email'  => 1,
		// Crear una cuenta nueva cuando el correo no existe.
		'upfw_sso_register'       => 1,
		// Exigir que el proveedor diga que el correo está verificado.
		'upfw_sso_verified_only'  => 0,
		// Roles que no pueden entrar con una red social. Va como lista porque
		// son varios; el guardado de abajo la trata aparte.
		'upfw_sso_blocked_roles'  => array(),

		/* Cómo se ven los botones de las redes. */
		'upfw_sso_button_skin'    => 'brand',
		'upfw_sso_button_shape'   => 'rounded',
		'upfw_sso_button_show'    => 'icon-text',
		// Vacío significa el texto por defecto, que además se traduce solo.
		'upfw_sso_button_text'    => '',
		'upfw_sso_button_columns' => 2,

		// ── El área de cuenta ─────────────────────────────────────────
		// La página que tiene el shortcode [upfw_account]. Con eso declarado
		// en un solo lugar, todo el que necesite mandar a alguien a «mi
		// cuenta» —LifterLMS, bbPress, un certificado— apunta bien.
		'upfw_account_page'       => 0,
		// Dónde va la navegación: arriba, al costado, o en ningún lado
		// porque la pone el sitio con [upfw_account_nav].
		'upfw_account_layout'     => 'tabs',
		// La portada con avatar, nombre y desde cuándo.
		'upfw_account_header'     => 1,
		// La configuración de cada sección: si está prendida, cómo se llama,
		// en qué orden va, y las secciones propias que agregó el sitio. Es
		// una lista porque el guardado la trata aparte.
		'upfw_account_sections'   => array(),

		// ── El nombre público ─────────────────────────────────────────
		// El correo es la identidad y no se elige; esto es el nombre corto
		// con el que la persona aparece y que va en la URL de su perfil.
		'upfw_handle_enabled'     => 0,
		// Además del correo, se puede pedir el enlace de acceso escribiendo
		// el nombre público. El enlace igual sale al correo de la cuenta.
		'upfw_handle_login'       => 0,
		'upfw_handle_min'         => 3,
		'upfw_handle_max'         => 30,
		// 'strict' = a-z 0-9 . _ - ; 'unicode' acepta acentos y ñ.
		'upfw_handle_charset'     => 'strict',
		// Qué hacer con los espacios: 'dash' los cambia por guiones —una
		// dirección no puede tener espacios— o 'reject' los rechaza y avisa.
		'upfw_handle_spaces'      => 'dash',
		// Días que hay que esperar entre un cambio y el siguiente. 0 = sin
		// espera; un nombre que cambia todos los días no identifica a nadie.
		'upfw_handle_cooldown'    => 30,
		'upfw_handle_reserved'    => '',

		// ── La foto de perfil ─────────────────────────────────────────
		// Dejar que la persona suba la suya.
		'upfw_avatar_upload'      => 1,
		// Si no subió ninguna, ir a buscarla a Gravatar. Apagado, no se hace
		// ninguna petición a un tercero con el hash del correo de nadie.
		'upfw_avatar_gravatar'    => 1,
		// Y si no hay ni una ni otra: las iniciales sobre el color de acento.
		'upfw_avatar_initials'    => 1,
		'upfw_avatar_max_kb'      => 2048,

		// ── Apariencia ────────────────────────────────────────────────
		// La hoja del plugin. Apagada, el sitio estila las clases upfw-*.
		'upfw_styles'             => 1,
		// Los dos valores que cambian todo lo demás, porque el resto de la
		// hoja sale de ellos. Vacío = los que trae la hoja.
		'upfw_style_accent'       => '',
		'upfw_style_radius'       => '',

		// ── Segundo factor ────────────────────────────────────────────
		// 'off' no se pide nunca; 'optional' sólo a quien lo prendió;
		// 'required' a todo el mundo que pueda usarlo.
		'upfw_2fa_mode'           => 'optional',
		// Los métodos que este sitio ofrece. Vacío equivale a apagado.
		'upfw_2fa_methods'        => array( 'totp', 'email' ),
		// Roles a los que se les pide. Vacío = a todos.
		'upfw_2fa_roles'          => array(),
		// Qué hacer cuando alguien entra por el enlace de correo:
		// 'auto'   pedirlo sólo si el segundo paso NO es otro correo. Un
		// código al mismo buzón que ya se abrió no prueba nada
		// nuevo; una aplicación autenticadora sí.
		// 'always' pedirlo siempre.
		// 'never'  no pedirlo nunca.
		'upfw_2fa_link'           => 'auto',
		// Días que se recuerda un navegador que ya pasó el desafío. 0 = nunca.
		'upfw_2fa_remember_days'  => 30,

		// ── Passkeys ──────────────────────────────────────────────────
		// ── Privacidad ──────────────────────────────────────────────
		// Qué puede hacer alguien con sus datos sin pedirle nada a nadie.
		// Los dos vienen prendidos: es lo que corresponde, y un sitio que
		// prefiera atender esos pedidos a mano los apaga.
		'upfw_privacy_export'     => 1,
		'upfw_privacy_delete'     => 1,

		// ── Sesiones ────────────────────────────────────────────────
		// Si cada persona ve dónde tiene la sesión abierta y puede cerrarlas.
		'upfw_sessions_show'      => 1,

		'upfw_passkey_enabled'    => 0,
		// 'device' sólo la llave del aparato que se está usando; 'any' también
		// las de afuera —una llave USB, o el teléfono escaneando un QR—.
		'upfw_passkey_where'      => 'any',
		// Exigir que además se verifique quién es: huella, cara o PIN.
		'upfw_passkey_verify'     => 1,

		// ── El registro nativo de WordPress ───────────────────────────
		// 'site' respeta lo que diga Ajustes → Generales; 'on' y 'off' lo
		// fuerzan desde acá, que es donde se administran las cuentas.
		'upfw_wp_registration'    => 'site',

		// Qué pasa cuando alguien abre el perfil del escritorio de WordPress:
		// 'allow' nada, 'redirect' lo manda al área de cuenta del sitio,
		// 'block' le dice que no. Nunca alcanza a quien administra.
		'upfw_wp_profile'         => 'allow',
	);
}

/**
 * Un ajuste, con su valor por defecto.
 *
 * @param string $key  Nombre de la option, con prefijo.
 * @param mixed  $fallback  Valor si no hay ni option ni default.
 * @return mixed
 */
function upfw_option( string $key, $fallback = null ) {
	$defaults = upfw_option_defaults();
	$value    = get_option( $key, null );

	if ( null === $value ) {
		$value = $defaults[ $key ] ?? $fallback;
	}

	/**
	 * Filtra un ajuste del plugin.
	 *
	 * @param mixed  $value Valor resuelto.
	 * @param string $key Nombre de la option.
	 */
	return apply_filters( 'upfw_option', $value, $key );
}

/**
 * ¿Este ajuste lo está forzando el sitio desde código?
 *
 * Un plugin como cst-core puede fijar un valor por el filtro `upfw_option`
 * —porque en ese sitio no es una opción sino cómo funciona—. Cuando eso pasa,
 * el control del admin se guarda y no cambia nada, que es exactamente la clase
 * de mentira que hay que evitar en una pantalla de ajustes. Con esto se puede
 * mostrar al lado del control.
 */
function upfw_option_forced( string $key ): bool {
	$defaults = upfw_option_defaults();
	$stored   = get_option( $key, null );
	$stored   = null === $stored ? ( $defaults[ $key ] ?? null ) : $stored;

	return upfw_option( $key ) !== $stored;
}

/**
 * Quién está fijando un ajuste desde el código.
 *
 * «Algo del sitio decidió esto» no le sirve a nadie: quien lee eso quiere ir
 * a sacarlo, y no sabe dónde. Acá sale el archivo y la función, que es lo que
 * hace falta para encontrarlo. Se listan todos los enganchados al filtro
 * porque cualquiera de ellos puede ser el que manda; cuál de todos, lo dice
 * abrir el archivo.
 *
 * @return array<int, string>
 */
function upfw_option_forced_by(): array {
	global $wp_filter;

	if ( ! isset( $wp_filter['upfw_option'] ) ) {
		return array();
	}

	$quienes = array();

	foreach ( $wp_filter['upfw_option']->callbacks as $enganchados ) {
		foreach ( $enganchados as $enganche ) {
			$fn = $enganche['function'];

			if ( ! is_string( $fn ) || ! function_exists( $fn ) ) {
				continue;
			}

			try {
				$archivo = (string) ( new ReflectionFunction( $fn ) )->getFileName();
			} catch ( ReflectionException $e ) {
				continue;
			}

			$quienes[] = sprintf(
				'%s() — %s',
				$fn,
				ltrim( str_replace( wp_normalize_path( WP_PLUGIN_DIR ), '', wp_normalize_path( $archivo ) ), '/' )
			);
		}
	}

	return $quienes;
}

/** Los minutos de vigencia del enlace, acotados a algo razonable. */
function upfw_login_expiry(): int {
	return max( 1, min( 1440, (int) upfw_option( 'upfw_login_expiry' ) ) );
}

/**
 * La URL de la pantalla de acceso del sitio.
 *
 * Sin página configurada se cae a wp-login.php, que es donde WordPress espera
 * mandar a alguien que no entró: un plugin no puede dejar un sitio sin puerta.
 */
function upfw_login_url(): string {
	$id  = (int) upfw_option( 'upfw_login_page' );
	$url = $id > 0 ? (string) get_permalink( $id ) : '';

	if ( '' === $url ) {
		$url = wp_login_url();
	}

	/**
	 * Filtra la URL de la pantalla de acceso.
	 *
	 * @param string $url
	 */
	return apply_filters( 'upfw_login_url', $url );
}

/** Guarda los ajustes que llegan de una pantalla del admin. */
function upfw_save_options( array $input ): void {
	$defaults = upfw_option_defaults();

	foreach ( $input as $key => $value ) {
		if ( ! array_key_exists( $key, $defaults ) ) {
			continue;
		}

		$default = $defaults[ $key ];

		if ( is_int( $default ) ) {
			update_option( $key, (int) $value );
			continue;
		}

		// Una lista de claves —los roles bloqueados es la única por ahora—.
		// Se guarda saneada elemento por elemento y sin índices sueltos.
		if ( is_array( $default ) ) {
			update_option( $key, array_values( array_unique( array_map( 'sanitize_key', (array) $value ) ) ) );
			continue;
		}

		update_option( $key, sanitize_textarea_field( (string) $value ) );
	}
}
