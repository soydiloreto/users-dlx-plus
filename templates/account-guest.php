<?php
/**
 * Lo que ve alguien sin sesión en la página de cuenta.
 *
 * Variables: $url.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="upfw-account upfw-account--invitado">
	<h2><?php esc_html_e( 'This is your account', 'users-plus-for-wordpress' ); ?></h2>
	<p><?php esc_html_e( 'Sign in to see it.', 'users-plus-for-wordpress' ); ?></p>
	<p><a class="upfw-button" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Sign in', 'users-plus-for-wordpress' ); ?></a></p>
</div>
