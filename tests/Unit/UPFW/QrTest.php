<?php
/**
 * El generador de códigos QR.
 *
 * Un QR mal armado no se ve mal: se ve igual. Por eso lo que se prueba acá es
 * la estructura —tamaño, patrones de posición, franjas de sincronismo, módulo
 * fijo— y no que «devuelva algo». La prueba de que además se lee la hace un
 * lector de verdad, fuera de esta suite.
 */

namespace Tests\Unit\UPFW;

use PHPUnit\Framework\TestCase;

class QrTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		require_once UPFW_DIR . 'includes/qr.php';
	}

	public function test_la_version_crece_con_el_texto(): void {
		// El lado es 17 + 4 × versión.
		$this->assertCount( 21, upfw_qr_matrix( 'hola' ) );
		$this->assertCount( 45, upfw_qr_matrix( str_repeat( 'a', 150 ) ) );
		$this->assertCount( 57, upfw_qr_matrix( str_repeat( 'a', 260 ) ) );
	}

	public function test_lo_que_no_entra_devuelve_null(): void {
		$this->assertNull( upfw_qr_matrix( str_repeat( 'a', 500 ) ) );
	}

	public function test_los_tres_patrones_de_posicion_estan(): void {
		$m    = upfw_qr_matrix( 'otpauth://totp/x?secret=ABCDEFGHIJKLMNOP' );
		$lado = count( $m );

		foreach ( array( array( 0, 0 ), array( 0, $lado - 7 ), array( $lado - 7, 0 ) ) as [$fila, $col] ) {
			// El anillo exterior va negro y el borde de adentro, blanco.
			$this->assertTrue( $m[ $fila ][ $col ] );
			$this->assertTrue( $m[ $fila ][ $col + 6 ] );
			$this->assertTrue( $m[ $fila + 6 ][ $col ] );
			$this->assertFalse( $m[ $fila + 1 ][ $col + 1 ] );
			$this->assertTrue( $m[ $fila + 3 ][ $col + 3 ] );
		}
	}

	public function test_las_franjas_de_sincronismo_alternan(): void {
		$m    = upfw_qr_matrix( 'hola' );
		$lado = count( $m );

		for ( $i = 8; $i < $lado - 8; $i++ ) {
			$this->assertSame( 0 === $i % 2, $m[6][ $i ] );
			$this->assertSame( 0 === $i % 2, $m[ $i ][6] );
		}
	}

	public function test_el_modulo_fijo_esta_negro(): void {
		$m = upfw_qr_matrix( 'hola' );

		$this->assertTrue( $m[ count( $m ) - 8 ][8] );
	}

	public function test_el_svg_trae_el_margen_que_pide_el_estandar(): void {
		$svg = upfw_qr_svg( 'hola', 200 );

		// 21 módulos + 4 de margen a cada lado.
		$this->assertStringContainsString( 'viewBox="0 0 29 29"', $svg );
		$this->assertStringContainsString( 'width="200"', $svg );
	}

	public function test_sin_espacio_no_devuelve_svg(): void {
		$this->assertSame( '', upfw_qr_svg( str_repeat( 'a', 500 ) ) );
	}
}
