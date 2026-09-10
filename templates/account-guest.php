<?php
/**
 * Lo que ve alguien sin sesión en la página de cuenta.
 *
 * @var string $url
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="users-plus-account users-plus-account--invitado">
	<h2><?php esc_html_e( 'This is your account', 'users-plus' ); ?></h2>
	<p><?php esc_html_e( 'Sign in to see it.', 'users-plus' ); ?></p>
	<p><a class="users-plus-button" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Sign in', 'users-plus' ); ?></a></p>
</div>
