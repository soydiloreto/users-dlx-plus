<?php
/**
 * El nombre público.
 *
 * Variables: $user, $handle, $can, $next, $error.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="upfw-panel upfw-handle">
	<h3><?php esc_html_e( 'Your public name', 'users-plus-for-wordpress' ); ?></h3>
	<p><?php esc_html_e( 'This is how people see you, and what goes in the address of your profile. Your email is how you get in, and nobody sees it.', 'users-plus-for-wordpress' ); ?></p>

	<?php if ( '' !== $error ) : ?>
		<p class="upfw-notice upfw-notice--error"><?php echo esc_html( $error ); ?></p>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="upfw-form">
		<input type="hidden" name="action" value="upfw_handle">
		<?php wp_nonce_field( 'upfw_handle' ); ?>

		<?php echo upfw_handle_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plantilla, ya escapada. ?>

		<?php if ( $can ) : ?>
			<button type="submit" class="upfw-button"><?php esc_html_e( 'Save', 'users-plus-for-wordpress' ); ?></button>
		<?php else : ?>
			<p class="upfw-nota">
				<?php
				echo esc_html(
					sprintf(
					/* translators: %s: fecha a partir de la cual se puede cambiar */
						__( 'You changed it recently. You can change it again on %s.', 'users-plus-for-wordpress' ),
						wp_date( 'j M Y', $next )
					)
				);
				?>
			</p>
		<?php endif; ?>
	</form>
</div>
