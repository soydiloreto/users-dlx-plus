<?php
/**
 * Que el plugin arranque de verdad en un WordPress limpio.
 *
 * Los tests unitarios corren contra stubs y no ven esto: que el archivo
 * principal cargue sus veintitantos includes sin chocar, que las opciones se
 * siembren solas, y que el área de cuenta exista antes de que nadie la
 * configure. Es el piso de «funciona en una instalación nueva».
 */

namespace Tests\Integration;

class PluginTest extends IntegrationTestCase {

	public function test_el_plugin_esta_cargado(): void {
		$this->assertTrue( defined( 'UPFW_VERSION' ) );
		$this->assertTrue( function_exists( 'upfw_option' ) );
	}

	public function test_las_opciones_tienen_valor_por_defecto(): void {
		// Sin nada guardado, cada ajuste tiene que contestar algo razonable:
		// un plugin recién instalado no puede depender de que alguien pase
		// por todas sus pantallas antes de que el sitio funcione.
		$this->assertSame( 'both', upfw_option( 'upfw_login_method' ) );
		$this->assertSame( 'optional', upfw_option( 'upfw_2fa_mode' ) );
		$this->assertSame( 1, (int) upfw_option( 'upfw_privacy_export' ) );
	}

	public function test_el_area_de_cuenta_trae_sus_secciones(): void {
		$secciones = upfw_sections( true );

		foreach ( array( 'home', 'details', 'accounts', 'security', 'privacy', 'notifications' ) as $id ) {
			$this->assertArrayHasKey( $id, $secciones, "falta la sección $id" );
		}
	}

	public function test_sin_redes_sociales_no_hay_seccion_de_cuentas_vinculadas(): void {
		// La regla que sostiene la coherencia del plugin: lo que no está
		// configurado no aparece, sin que haya que apagar nada a mano.
		wp_set_current_user( $this->alguien() );

		$this->assertArrayNotHasKey( 'accounts', upfw_sections() );
	}

	public function test_los_campos_de_wordpress_estan_entre_los_campos(): void {
		upfw_seed_fields();

		$claves = wp_list_pluck( upfw_fields(), 'key' );

		$this->assertContains( 'first_name', $claves );
		$this->assertContains( 'last_name', $claves );
	}

	public function test_el_plugin_trae_sus_propias_notificaciones(): void {
		$this->assertArrayHasKey( 'upfw_notify_login', upfw_notification_prefs() );
		$this->assertArrayHasKey( 'upfw_notify_security', upfw_notification_prefs() );
	}
}
