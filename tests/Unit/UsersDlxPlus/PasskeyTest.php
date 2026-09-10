<?php
/**
 * La verificación de una passkey.
 *
 * Acá un error no rompe nada visible: simplemente deja entrar a quien no
 * debería. Se prueban las tres comprobaciones que hacen que una passkey sirva
 * —el origen, el dominio y la presencia de la persona— y que el desafío no se
 * pueda usar dos veces.
 */

namespace Tests\Unit\UsersDlxPlus;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

class PasskeyTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		require_once USERS_DLX_PLUS_DIR . 'includes/options.php';
		require_once USERS_DLX_PLUS_DIR . 'includes/auth-passkeys.php';

		$GLOBALS['_test_wp_options']    = array();
		$GLOBALS['_test_wp_transients'] = array();

		update_option( 'users_dlx_plus_passkey_verify', 0 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/** El `authenticatorData` que devolvería un autenticador. */
	private function auth_data( string $rp_id, int $flags = 0x01, int $counter = 0 ): string {
		return hash( 'sha256', $rp_id, true ) . chr( $flags ) . pack( 'N', $counter );
	}

	public function test_base64url_va_y_vuelve(): void {
		$bytes = random_bytes( 64 );

		$this->assertSame( $bytes, users_dlx_plus_b64url_decode( users_dlx_plus_b64url_encode( $bytes ) ) );
	}

	public function test_base64url_no_lleva_relleno_ni_caracteres_de_url(): void {
		$texto = users_dlx_plus_b64url_encode( random_bytes( 31 ) );

		$this->assertSame( 0, preg_match( '/[+\/=]/', $texto ) );
	}

	public function test_acepta_los_datos_del_dominio_correcto(): void {
		$this->assertNotNull( users_dlx_plus_passkey_auth_data( $this->auth_data( users_dlx_plus_passkey_rp_id() ) ) );
	}

	public function test_rechaza_los_de_otro_dominio(): void {
		// Es lo que impide que una firma hecha para otro sitio sirva acá.
		$this->assertNull( users_dlx_plus_passkey_auth_data( $this->auth_data( 'otro-sitio.com' ) ) );
	}

	public function test_rechaza_si_nadie_estuvo_presente(): void {
		// Sin esa marca, la firma la pudo haber pedido un proceso solo.
		$this->assertNull( users_dlx_plus_passkey_auth_data( $this->auth_data( users_dlx_plus_passkey_rp_id(), 0x00 ) ) );
	}

	public function test_rechaza_algo_mas_corto_de_lo_posible(): void {
		$this->assertNull( users_dlx_plus_passkey_auth_data( 'corto' ) );
	}

	public function test_exige_verificacion_de_la_persona_cuando_el_sitio_la_pide(): void {
		update_option( 'users_dlx_plus_passkey_verify', 1 );

		// Sólo presencia: no alcanza.
		$this->assertNull( users_dlx_plus_passkey_auth_data( $this->auth_data( users_dlx_plus_passkey_rp_id(), 0x01 ) ) );

		// Presencia y verificación.
		$this->assertNotNull( users_dlx_plus_passkey_auth_data( $this->auth_data( users_dlx_plus_passkey_rp_id(), 0x05 ) ) );
	}

	public function test_devuelve_el_contador(): void {
		$datos = users_dlx_plus_passkey_auth_data( $this->auth_data( users_dlx_plus_passkey_rp_id(), 0x01, 42 ) );

		$this->assertSame( 42, $datos['counter'] );
	}

	public function test_un_desafio_sirve_una_sola_vez(): void {
		$reto = users_dlx_plus_passkey_challenge_new( 'log' );

		$this->assertTrue( users_dlx_plus_passkey_challenge_use( 'log', $reto ) );
		$this->assertFalse( users_dlx_plus_passkey_challenge_use( 'log', $reto ) );
	}

	public function test_un_desafio_de_alta_no_sirve_para_entrar(): void {
		// Si sirviera, alguien podría reusar el de una pantalla en la otra.
		$reto = users_dlx_plus_passkey_challenge_new( 'reg' );

		$this->assertFalse( users_dlx_plus_passkey_challenge_use( 'log', $reto ) );
	}

	public function test_un_desafio_inventado_no_sirve(): void {
		$this->assertFalse( users_dlx_plus_passkey_challenge_use( 'log', 'cualquier-cosa' ) );
	}

	public function test_acepta_los_datos_del_cliente_bien_formados(): void {
		$reto = users_dlx_plus_passkey_challenge_new( 'log' );

		$json = wp_json_encode( array(
			'type'      => 'webauthn.get',
			'challenge' => $reto,
			'origin'    => users_dlx_plus_passkey_origin(),
		) );

		$this->assertNotNull( users_dlx_plus_passkey_client_data( $json, 'webauthn.get', 'log' ) );
	}

	public function test_rechaza_otro_origen(): void {
		// Es lo que impide que un sitio clonado use la passkey de éste.
		$reto = users_dlx_plus_passkey_challenge_new( 'log' );

		$json = wp_json_encode( array(
			'type'      => 'webauthn.get',
			'challenge' => $reto,
			'origin'    => 'https://sitio-falso.example',
		) );

		$this->assertNull( users_dlx_plus_passkey_client_data( $json, 'webauthn.get', 'log' ) );
	}

	public function test_rechaza_la_operacion_equivocada(): void {
		$reto = users_dlx_plus_passkey_challenge_new( 'log' );

		$json = wp_json_encode( array(
			'type'      => 'webauthn.create',
			'challenge' => $reto,
			'origin'    => users_dlx_plus_passkey_origin(),
		) );

		$this->assertNull( users_dlx_plus_passkey_client_data( $json, 'webauthn.get', 'log' ) );
	}

	public function test_rechaza_un_desafio_que_no_emitimos(): void {
		$json = wp_json_encode( array(
			'type'      => 'webauthn.get',
			'challenge' => users_dlx_plus_b64url_encode( random_bytes( 32 ) ),
			'origin'    => users_dlx_plus_passkey_origin(),
		) );

		$this->assertNull( users_dlx_plus_passkey_client_data( $json, 'webauthn.get', 'log' ) );
	}

	public function test_rechaza_una_firma_de_un_algoritmo_que_no_verifica(): void {
		$this->assertFalse( users_dlx_plus_passkey_signature_ok( 'x', -37, 'a', 'b', 'c' ) );
	}
}
