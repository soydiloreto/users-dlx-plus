<?php
/**
 * Los avisos que trae el plugin por su cuenta.
 *
 * La sección de notificaciones existía y llegaba vacía a una instalación
 * limpia: lo que se ofrecía ahí lo tenía que registrar el sitio. Esto es la
 * garantía de que eso no vuelva a pasar.
 */

namespace Tests\Unit\UPFW;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

class NotificationsTest extends TestCase {

	private const USUARIO = 11;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		require_once UPFW_DIR . 'includes/options.php';
		require_once UPFW_DIR . 'includes/notify.php';
		require_once UPFW_DIR . 'includes/account-sections.php';

		$GLOBALS['cst_test_user_meta'] = array();
		$GLOBALS['_test_wp_options']   = array();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_el_plugin_trae_sus_propios_avisos(): void {
		$propios = upfw_default_notifications();

		$this->assertArrayHasKey( 'upfw_notify_login', $propios );
		$this->assertArrayHasKey( 'upfw_notify_security', $propios );
	}

	public function test_los_avisos_propios_vienen_prendidos(): void {
		// Un aviso de seguridad que hay que ir a prender no lo prende nadie, y
		// el que lo necesita es justamente quien no entró a mirar.
		foreach ( upfw_default_notifications() as $pref ) {
			$this->assertSame( '1', $pref['default'] );
		}
	}

	public function test_sin_haber_elegido_nada_se_toma_el_valor_por_defecto(): void {
		$this->assertTrue( upfw_wants( self::USUARIO, 'upfw_notify_login' ) );
	}

	public function test_quien_lo_apago_deja_de_recibirlo(): void {
		update_user_meta( self::USUARIO, 'upfw_notify_login', '0' );

		$this->assertFalse( upfw_wants( self::USUARIO, 'upfw_notify_login' ) );
	}

	public function test_un_aviso_que_nadie_registro_no_se_manda(): void {
		$this->assertFalse( upfw_wants( self::USUARIO, 'upfw_notify_inventado' ) );
	}

	public function test_el_mismo_navegador_da_el_mismo_identificador(): void {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh) Chrome/140';
		$uno = upfw_device_id();

		$this->assertSame( $uno, upfw_device_id() );

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone) Safari/605';
		$this->assertNotSame( $uno, upfw_device_id() );
	}
}
