<?php
/**
 * Lo que ve alguien sin sesión en la página de cuenta.
 *
 * @var string $url
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="users-dlx-plus-account users-dlx-plus-account--invitado">
	<h2><?php esc_html_e( 'This is your account', 'users-dlx-plus' ); ?></h2>
	<p><?php esc_html_e( 'Sign in to see it.', 'users-dlx-plus' ); ?></p>
	<p><a class="users-dlx-plus-button" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Sign in', 'users-dlx-plus' ); ?></a></p>
</div>
