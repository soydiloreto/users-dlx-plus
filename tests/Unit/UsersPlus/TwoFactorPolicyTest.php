<?php
/**
 * La política del segundo factor.
 *
 * Es la parte donde un error no se ve: si la condición está mal, o se le pide
 * a todo el mundo cuando no correspondía, o —peor— no se le pide a nadie y el
 * sitio cree que está protegido.
 */

namespace Tests\Unit\UsersPlus;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

class TwoFactorPolicyTest extends TestCase {

	private const USUARIO = 7;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		require_once USERS_PLUS_DIR . 'includes/options.php';
		require_once USERS_PLUS_DIR . 'includes/auth-totp.php';
		require_once USERS_PLUS_DIR . 'includes/auth-email.php';
		require_once USERS_PLUS_DIR . 'includes/auth.php';
		require_once USERS_PLUS_DIR . 'includes/login.php';

		$GLOBALS['cst_test_user_meta'] = array();
		$GLOBALS['_test_wp_options']   = array();
		$GLOBALS['_test_wp_users']     = array();

		$this->ajustes( array() );
		$this->persona();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/** Deja los ajustes en un estado conocido. */
	private function ajustes( array $valores ): void {
		$base = array(
			'users_plus_2fa_mode'          => 'required',
			'users_plus_2fa_methods'       => array( 'email' ),
			'users_plus_2fa_roles'         => array(),
			'users_plus_2fa_link'          => 'auto',
			'users_plus_2fa_remember_days' => 0,
		);

		foreach ( array_merge( $base, $valores ) as $clave => $valor ) {
			update_option( $clave, $valor );
		}
	}

	/** Alguien con el rol que se le pase. */
	private function persona( string $rol = 'subscriber' ): void {
		$GLOBALS['_test_wp_users'][ self::USUARIO ] = new \WP_User( self::USUARIO, array( $rol ) );
	}

	public function test_apagado_no_pide_nunca(): void {
		$this->ajustes( array( 'users_plus_2fa_mode' => 'off' ) );

		$this->assertFalse( users_plus_2fa_required( self::USUARIO, 'password' ) );
	}

	public function test_obligatorio_pide_a_todos(): void {
		$this->assertTrue( users_plus_2fa_required( self::USUARIO, 'password' ) );
	}

	public function test_sin_metodos_no_puede_pedir_nada(): void {
		// Exigir algo que la persona no puede dar la dejaría afuera del sitio.
		$this->ajustes( array( 'users_plus_2fa_methods' => array() ) );

		$this->assertFalse( users_plus_2fa_required( self::USUARIO, 'password' ) );
	}

	public function test_con_el_correo_como_unico_segundo_paso_el_enlace_no_lo_pide(): void {
		// Un código al mismo buzón que la persona acaba de abrir para seguir el
		// enlace no prueba nada que el enlace no haya probado ya.
		$this->assertFalse( users_plus_2fa_required( self::USUARIO, 'link' ) );
	}

	public function test_con_una_aplicacion_disponible_el_enlace_si_lo_pide(): void {
		$this->ajustes( array( 'users_plus_2fa_methods' => array( 'email', 'totp' ) ) );
		update_user_meta( self::USUARIO, 'users_plus_totp', 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ' );

		$this->assertTrue( users_plus_2fa_required( self::USUARIO, 'link' ) );
	}

	public function test_el_sitio_puede_forzar_las_dos_respuestas(): void {
		$this->ajustes( array( 'users_plus_2fa_link' => 'always' ) );
		$this->assertTrue( users_plus_2fa_required( self::USUARIO, 'link' ) );

		$this->ajustes( array( 'users_plus_2fa_link' => 'never', 'users_plus_2fa_methods' => array( 'email', 'totp' ) ) );
		update_user_meta( self::USUARIO, 'users_plus_totp', 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ' );
		$this->assertFalse( users_plus_2fa_required( self::USUARIO, 'link' ) );
	}

	public function test_con_roles_elegidos_solo_a_esos(): void {
		$this->ajustes( array( 'users_plus_2fa_roles' => array( 'administrator' ) ) );

		$this->persona( 'subscriber' );
		$this->assertFalse( users_plus_2fa_required( self::USUARIO, 'password' ) );

		$this->persona( 'administrator' );
		$this->assertTrue( users_plus_2fa_required( self::USUARIO, 'password' ) );
	}

	public function test_opcional_solo_a_quien_lo_prendio(): void {
		$this->ajustes( array( 'users_plus_2fa_mode' => 'optional' ) );

		$this->assertFalse( users_plus_2fa_required( self::USUARIO, 'password' ) );

		update_user_meta( self::USUARIO, 'users_plus_2fa_on', 1 );
		$this->assertTrue( users_plus_2fa_required( self::USUARIO, 'password' ) );
	}

	public function test_con_el_enlace_como_unica_puerta_no_se_pide_en_ninguna(): void {
		// Es el caso en el que la pantalla dice «no se te está pidiendo»: tiene
		// que ser cierto, y sólo lo es cuando no hay ninguna otra puerta.
		$this->ajustes( array( 'users_plus_login_method' => 'link' ) );

		$this->assertSame( array( 'link' ), array_keys( users_plus_2fa_ways( self::USUARIO ) ) );
		$this->assertSame( array(), users_plus_2fa_ways_asked( self::USUARIO ) );
	}

	public function test_con_contrasena_prendida_el_enlace_se_saltea_pero_la_contrasena_no(): void {
		$this->ajustes( array( 'users_plus_login_method' => 'both' ) );

		$vias = users_plus_2fa_ways( self::USUARIO );

		$this->assertFalse( $vias['link']['asked'] );
		$this->assertTrue( $vias['password']['asked'] );
		$this->assertSame( array( 'your password' ), users_plus_2fa_ways_asked( self::USUARIO ) );
	}

	public function test_sin_dias_de_memoria_ningun_navegador_es_de_confianza(): void {
		$this->assertFalse( users_plus_2fa_trusted( self::USUARIO ) );
	}

	public function test_los_codigos_de_respaldo_no_se_guardan_en_claro(): void {
		$codigos = users_plus_backup_generate( self::USUARIO, 4 );

		$this->assertCount( 4, $codigos );
		$this->assertSame( 4, users_plus_backup_left( self::USUARIO ) );

		foreach ( $GLOBALS['cst_test_user_meta'][ self::USUARIO ]['users_plus_backup_codes'] as $guardado ) {
			$this->assertNotContains( $guardado, $codigos );
		}
	}

	public function test_un_codigo_de_respaldo_sirve_una_sola_vez(): void {
		$codigos = users_plus_backup_generate( self::USUARIO, 3 );

		$this->assertTrue( users_plus_backup_use( self::USUARIO, $codigos[1] ) );
		$this->assertSame( 2, users_plus_backup_left( self::USUARIO ) );
		$this->assertFalse( users_plus_backup_use( self::USUARIO, $codigos[1] ) );
	}

	public function test_un_codigo_de_respaldo_inventado_no_sirve(): void {
		users_plus_backup_generate( self::USUARIO, 3 );

		$this->assertFalse( users_plus_backup_use( self::USUARIO, 'noexiste00' ) );
		$this->assertSame( 3, users_plus_backup_left( self::USUARIO ) );
	}
}
