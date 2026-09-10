<?php
/**
 * El enlace de acceso es la única credencial del sitio: si falla, o entra
 * cualquiera o no entra nadie. Estos tests fijan las cuatro propiedades que lo
 * hacen seguro.
 */

namespace Tests\Unit\UsersPlus;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

class LoginTest extends TestCase {

	private const USUARIO = 4242;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		require_once USERS_PLUS_DIR . 'includes/options.php';
		require_once USERS_PLUS_DIR . 'includes/login.php';
		$GLOBALS['cst_test_user_meta'] = array();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_un_token_recien_creado_es_valido(): void {
		$token = users_plus_token_create( self::USUARIO );

		$this->assertTrue( users_plus_token_valid( self::USUARIO, $token ) );
	}

	public function test_el_token_no_se_guarda_en_claro(): void {
		// Si se guardara tal cual, cualquiera con acceso a la base podría entrar
		// como cualquier persona sin siquiera tocar su correo.
		$token = users_plus_token_create( self::USUARIO );

		$this->assertNotSame(
			$token,
			$GLOBALS['cst_test_user_meta'][ self::USUARIO ][ USERS_PLUS_META_HASH ],
			'En la base tiene que quedar el hash, nunca el token'
		);
	}

	public function test_un_token_ajeno_no_sirve(): void {
		users_plus_token_create( self::USUARIO );

		$this->assertFalse( users_plus_token_valid( self::USUARIO, 'token-inventado' ) );
	}

	public function test_el_token_de_otra_persona_no_sirve(): void {
		$mio = users_plus_token_create( self::USUARIO );
		users_plus_token_create( 9999 );

		$this->assertFalse(
			users_plus_token_valid( 9999, $mio ),
			'Un enlace válido no puede servir para entrar a otra cuenta'
		);
	}

	public function test_el_token_vence(): void {
		$token = users_plus_token_create( self::USUARIO );

		// Se adelanta el reloj poniendo el vencimiento en el pasado.
		$GLOBALS['cst_test_user_meta'][ self::USUARIO ][ USERS_PLUS_META_EXPIRES ] = time() - 1;

		$this->assertFalse( users_plus_token_valid( self::USUARIO, $token ) );
	}

	public function test_el_token_sirve_una_sola_vez(): void {
		$token = users_plus_token_create( self::USUARIO );
		$this->assertTrue( users_plus_token_valid( self::USUARIO, $token ) );

		users_plus_token_burn( self::USUARIO );

		$this->assertFalse(
			users_plus_token_valid( self::USUARIO, $token ),
			'Un enlace reenviado o filtrado no puede volver a abrir la sesión'
		);
	}

	public function test_sin_token_guardado_nada_valida(): void {
		// El caso de quien nunca pidió un enlace: no hay hash contra qué comparar.
		$this->assertFalse( users_plus_token_valid( self::USUARIO, 'cualquier-cosa' ) );
	}

	public function test_cada_token_es_distinto(): void {
		$a = users_plus_token_create( self::USUARIO );
		$b = users_plus_token_create( self::USUARIO );

		$this->assertNotSame( $a, $b );
		$this->assertFalse(
			users_plus_token_valid( self::USUARIO, $a ),
			'Pedir un enlace nuevo tiene que invalidar el anterior'
		);
	}
}
