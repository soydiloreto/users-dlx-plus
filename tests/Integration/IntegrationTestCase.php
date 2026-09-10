<?php
/**
 * Base de los tests que necesitan un WordPress de verdad cargado.
 *
 * El plugin no tiene tablas propias —sus datos son options y user meta— así
 * que lo único que hay que aislar entre tests son esas dos cosas.
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class IntegrationTestCase extends TestCase {

	/**
	 * Las options del plugin que se limpian entre tests.
	 *
	 * Si mañana se agrega una, va acá: si no, un test ve lo que dejó el
	 * anterior y pasa —o falla— por el motivo equivocado.
	 *
	 * @var array<int, string>
	 */
	protected static array $options = array(
		'users_dlx_plus_fields',
		'users_dlx_plus_account_sections',
		'users_dlx_plus_sso',
		'users_dlx_plus_mail_last',
		'users_dlx_plus_login_method',
		'users_dlx_plus_2fa_mode',
		'users_dlx_plus_passkey_enabled',
	);

	protected function setUp(): void {
		parent::setUp();

		foreach ( self::$options as $option ) {
			delete_option( $option );
		}
	}

	/** Una persona nueva, con el rol que se le pase. */
	protected function alguien( string $rol = 'subscriber' ): int {
		return (int) wp_insert_user(
			array(
				'user_login' => 'users_dlx_plus_' . wp_generate_password( 8, false ),
				'user_email' => wp_generate_password( 8, false ) . '@ejemplo.test',
				'user_pass'  => wp_generate_password( 16 ),
				'role'       => $rol,
			)
		);
	}
}
