<?php
/**
 * La IP de la sesión es lo que se mira cuando alguien dice «no reconozco esa
 * entrada». Si detrás de un proxy guarda siempre la del proxy, la pantalla de
 * sesiones no sirve para nada; y si le cree a cualquier cabecera, cualquiera
 * puede decir que es quien quiera. Estos tests fijan las dos cosas.
 */

namespace Tests\Unit\UPFW;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

class ClientIpTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		require_once UPFW_DIR . 'includes/client-ip.php';
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_sin_proxy_manda_remote_addr(): void {
		$this->assertSame( '190.15.219.128', upfw_client_ip( array(
			'REMOTE_ADDR' => '190.15.219.128',
		) ) );
	}

	public function test_una_ip_publica_no_le_cree_a_las_cabeceras(): void {
		// El servidor está expuesto directo a internet: la cabecera la escribió
		// el cliente y podría decir cualquier cosa.
		$this->assertSame( '190.15.219.128', upfw_client_ip( array(
			'REMOTE_ADDR'          => '190.15.219.128',
			'HTTP_X_FORWARDED_FOR' => '8.8.8.8',
		) ) );
	}

	public function test_detras_de_un_proxy_interno_usa_la_cabecera(): void {
		$this->assertSame( '181.45.184.48', upfw_client_ip( array(
			'REMOTE_ADDR'          => '172.20.0.1',
			'HTTP_X_FORWARDED_FOR' => '181.45.184.48',
		) ) );
	}

	public function test_de_una_cadena_de_proxies_se_queda_con_el_cliente(): void {
		// X-Forwarded-For es "cliente, proxy1, proxy2": el primero es quien pidió.
		$this->assertSame( '181.45.184.48', upfw_client_ip( array(
			'REMOTE_ADDR'          => '10.0.0.5',
			'HTTP_X_FORWARDED_FOR' => '181.45.184.48, 10.0.0.9, 10.0.0.5',
		) ) );
	}

	public function test_le_saca_el_puerto_que_pega_azure(): void {
		// App Service escribe "ip:puerto" y eso no es una IP.
		$this->assertSame( '79.159.158.249', upfw_client_ip( array(
			'REMOTE_ADDR'          => '127.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '79.159.158.249:49391',
		) ) );
	}

	public function test_cloudflare_le_gana_a_x_forwarded_for(): void {
		$this->assertSame( '200.1.2.3', upfw_client_ip( array(
			'REMOTE_ADDR'           => '172.16.0.1',
			'HTTP_CF_CONNECTING_IP' => '200.1.2.3',
			'HTTP_X_FORWARDED_FOR'  => '8.8.8.8',
		) ) );
	}

	public function test_una_cabecera_basura_no_rompe_nada(): void {
		$this->assertSame( '10.0.0.5', upfw_client_ip( array(
			'REMOTE_ADDR'          => '10.0.0.5',
			'HTTP_X_FORWARDED_FOR' => 'no-soy-una-ip',
		) ) );
	}

	public function test_ipv6_con_corchetes_y_puerto(): void {
		$this->assertSame( '2803:9800:a::1', upfw_client_ip( array(
			'REMOTE_ADDR'          => '::1',
			'HTTP_X_FORWARDED_FOR' => '[2803:9800:a::1]:51234',
		) ) );
	}

	public function test_una_sesion_vieja_sin_nuestra_ip_usa_la_de_wordpress(): void {
		$this->assertSame( '79.159.158.249', upfw_session_ip_of( array( 'ip' => '79.159.158.249:49391' ) ) );
	}

	public function test_una_sesion_con_nuestra_ip_la_prefiere(): void {
		$this->assertSame( '181.45.184.48', upfw_session_ip_of( array(
			'ip'      => '172.20.0.1',
			'upfw_ip' => '181.45.184.48',
		) ) );
	}
}
