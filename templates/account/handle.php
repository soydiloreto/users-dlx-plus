<?php
/**
 * El nombre público.
 *
 * @var bool   $can
 * @var string $error
 * @var int    $next
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="users-plus-panel users-plus-handle">
	<h3><?php esc_html_e( 'Your public name', 'users-plus' ); ?></h3>
	<p><?php esc_html_e( 'This is how people see you, and what goes in the address of your profile. Your email is how you get in, and nobody sees it.', 'users-plus' ); ?></p>

	<?php if ( '' !== $error ) : ?>
		<p class="users-plus-notice users-plus-notice--error"><?php echo esc_html( $error ); ?></p>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="users-plus-form">
		<input type="hidden" name="action" value="users_plus_handle">
		<?php wp_nonce_field( 'users_plus_handle' ); ?>

		<?php echo users_plus_handle_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plantilla, ya escapada. ?>

		<?php if ( $can ) : ?>
			<button type="submit" class="users-plus-button"><?php esc_html_e( 'Save', 'users-plus' ); ?></button>
		<?php else : ?>
			<p class="users-plus-nota">
				<?php
				echo esc_html(
					sprintf(
					/* translators: %s: fecha a partir de la cual se puede cambiar */
						__( 'You changed it recently. You can change it again on %s.', 'users-plus' ),
						wp_date( 'j M Y', $next )
					)
				);
				?>
			</p>
		<?php endif; ?>
	</form>
</div>
