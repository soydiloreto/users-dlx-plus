<?php
/**
 * El segundo factor de la aplicación autenticadora.
 *
 * Se prueba contra los vectores del RFC 6238, que son los mismos con los que
 * se prueban todas las implementaciones del mundo: si estos pasan, el código
 * que muestre cualquier aplicación va a coincidir con el que espera el sitio.
 * Sin esto, un error de un bit se descubre el día que alguien no puede entrar.
 */

namespace Tests\Unit\UsersDlxPlus;

use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase {

	/** El secreto de los vectores del RFC: "12345678901234567890" en base32. */
	private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

	protected function setUp(): void {
		parent::setUp();
		require_once USERS_DLX_PLUS_DIR . 'includes/auth-totp.php';
	}

	/**
	 * @dataProvider vectores
	 */
	public function test_vectores_del_rfc( int $momento, string $esperado ): void {
		$this->assertSame( $esperado, users_dlx_plus_totp_code( self::SECRET, $momento ) );
	}

	/** @return array<int, array{int, string}> */
	public static function vectores(): array {
		return array(
			array( 59, '287082' ),
			array( 1111111109, '081804' ),
			array( 1111111111, '050471' ),
			array( 1234567890, '005924' ),
			array( 2000000000, '279037' ),
		);
	}

	public function test_base32_decodifica_lo_que_dice_el_rfc(): void {
		$this->assertSame( '12345678901234567890', users_dlx_plus_base32_decode( self::SECRET ) );
	}

	public function test_base32_ignora_espacios_y_minusculas(): void {
		// La clave se muestra en grupos de cuatro para poder tipearla; hay que
		// aceptarla de vuelta tal como la copia una persona.
		$this->assertSame(
			users_dlx_plus_base32_decode( self::SECRET ),
			users_dlx_plus_base32_decode( strtolower( trim( chunk_split( self::SECRET, 4, ' ' ) ) ) )
		);
	}

	public function test_acepta_el_codigo_de_ahora(): void {
		$this->assertTrue( users_dlx_plus_totp_check( self::SECRET, users_dlx_plus_totp_code( self::SECRET ) ) );
	}

	public function test_acepta_una_ventana_de_desfasaje(): void {
		// Un reloj corrido treinta segundos es lo más común del mundo.
		$this->assertTrue( users_dlx_plus_totp_check( self::SECRET, users_dlx_plus_totp_code( self::SECRET, time() - 30 ) ) );
		$this->assertTrue( users_dlx_plus_totp_check( self::SECRET, users_dlx_plus_totp_code( self::SECRET, time() + 30 ) ) );
	}

	public function test_rechaza_mas_alla_de_la_ventana(): void {
		$this->assertFalse( users_dlx_plus_totp_check( self::SECRET, users_dlx_plus_totp_code( self::SECRET, time() - 300 ) ) );
	}

	public function test_rechaza_cualquier_cosa(): void {
		$this->assertFalse( users_dlx_plus_totp_check( self::SECRET, '000000' ) );
		$this->assertFalse( users_dlx_plus_totp_check( self::SECRET, '12345' ) );
		$this->assertFalse( users_dlx_plus_totp_check( self::SECRET, '' ) );
		$this->assertFalse( users_dlx_plus_totp_check( self::SECRET, 'abcdef' ) );
	}

	public function test_un_secreto_nuevo_es_base32_del_largo_pedido(): void {
		$secreto = users_dlx_plus_totp_secret_new( 32 );

		$this->assertSame( 32, strlen( $secreto ) );
		$this->assertSame( 1, preg_match( '/^[A-Z2-7]+$/', $secreto ) );
	}

	public function test_dos_secretos_nuevos_no_son_iguales(): void {
		$this->assertNotSame( users_dlx_plus_totp_secret_new(), users_dlx_plus_totp_secret_new() );
	}
}
