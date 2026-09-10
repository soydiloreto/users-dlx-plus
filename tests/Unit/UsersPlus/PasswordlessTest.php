<?php
/**
 * El acceso sin contraseña es una decisión de producto, no un detalle visual:
 * si wp-login.php queda abierto, existe una segunda forma de entrar que la
 * pantalla de acceso no muestra. Estos tests fijan qué se cierra y qué no.
 */

namespace Tests\Unit\UsersPlus;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

class PasswordlessTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// El módulo registra hooks al cargarse, así que se carga con Brain
		// Monkey activo. Los hooks no se stubean en wordpress-stubs.php
		// justamente para no tapar los suyos.
		Monkey\setUp();
		require_once USERS_PLUS_DIR . 'includes/options.php';
		require_once USERS_PLUS_DIR . 'includes/passwordless.php';
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @dataProvider accionesQueSeCierran
	 */
	public function test_las_acciones_con_contrasena_se_redirigen(string $accion): void {
		$this->assertTrue(
			users_plus_should_redirect($accion),
			"La acción '{$accion}' tendría que ir a la página de acceso"
		);
	}

	public static function accionesQueSeCierran(): array {
		return [
			'login por defecto'   => [''],
			'login explícito'     => ['login'],
			'registro nativo'     => ['register'],
			'olvidé mi clave'     => ['lostpassword'],
			'acción desconocida'  => ['algo-que-no-existe'],
		];
	}

	/**
	 * @dataProvider accionesQueSiguenAbiertas
	 */
	public function test_los_flujos_que_no_son_login_siguen_funcionando(string $accion): void {
		$this->assertFalse(
			users_plus_should_redirect($accion),
			"La acción '{$accion}' no puede redirigirse: rompe un flujo que WordPress necesita"
		);
	}

	public static function accionesQueSiguenAbiertas(): array {
		return [
			'cerrar sesión'            => ['logout'],
			'contraseña de entrada'    => ['postpass'],
			'reseteo iniciado por admin' => ['rp'],
			'reseteo, segundo paso'    => ['resetpass'],
			'confirmación RGPD'        => ['confirmaction'],
		];
	}

	public function test_la_salida_de_emergencia_deja_ver_el_formulario_nativo(): void {
		$this->assertFalse(
			users_plus_should_redirect('login', ['users-plus-admin' => '1']),
			'Sin esta salida, un fallo del correo o del proveedor social deja a todos afuera del sitio'
		);
	}

	public function test_el_login_intersticial_del_escritorio_no_se_redirige(): void {
		// Es el modal que aparece dentro de wp-admin cuando vence la sesión:
		// redirigirlo rompería la pantalla que lo abrió.
		$this->assertFalse(
			users_plus_should_redirect('login', ['interim-login' => '1'])
		);
	}

	public function test_la_salida_de_emergencia_no_reabre_el_registro_nativo(): void {
		// users-plus-admin existe para que un administrador pueda entrar con su
		// contraseña, no para volver a habilitar altas por wp-login.php.
		// Este test falla si alguien mueve la comprobación de `register`
		// antes que la de `users-plus-admin`.
		$this->assertFalse(
			users_plus_should_redirect('register', ['users-plus-admin' => '1']),
			'El flujo de emergencia muestra el formulario nativo; el registro nativo está apagado por option_users_can_register'
		);
	}
}
