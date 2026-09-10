<?php
/**
 * El nombre público.
 *
 * @var bool   $can
 * @var string $error
 * @var int    $next
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="users-dlx-plus-panel users-dlx-plus-handle">
	<h3><?php esc_html_e( 'Your public name', 'users-dlx-plus' ); ?></h3>
	<p><?php esc_html_e( 'This is how people see you, and what goes in the address of your profile. Your email is how you get in, and nobody sees it.', 'users-dlx-plus' ); ?></p>

	<?php if ( '' !== $error ) : ?>
		<p class="users-dlx-plus-notice users-dlx-plus-notice--error"><?php echo esc_html( $error ); ?></p>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="users-dlx-plus-form">
		<input type="hidden" name="action" value="users_dlx_plus_handle">
		<?php wp_nonce_field( 'users_dlx_plus_handle' ); ?>

		<?php echo users_dlx_plus_handle_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plantilla, ya escapada. ?>

		<?php if ( $can ) : ?>
			<button type="submit" class="users-dlx-plus-button"><?php esc_html_e( 'Save', 'users-dlx-plus' ); ?></button>
		<?php else : ?>
			<p class="users-dlx-plus-nota">
				<?php
				echo esc_html(
					sprintf(
					/* translators: %s: fecha a partir de la cual se puede cambiar */
						__( 'You changed it recently. You can change it again on %s.', 'users-dlx-plus' ),
						wp_date( 'j M Y', $next )
					)
				);
				?>
			</p>
		<?php endif; ?>
	</form>
</div>
