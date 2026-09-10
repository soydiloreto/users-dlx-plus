<?php
/**
 * La foto de perfil.
 *
 * Variables: $user, $has, $error.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;
?>
<?php upfw_panel_open( __( 'Your photo', 'users-plus-for-wordpress' ), true, 'upfw-avatar' ); ?>

	<?php if ( '' !== $error ) : ?>
		<p class="upfw-notice upfw-notice--error"><?php echo esc_html( $error ); ?></p>
	<?php endif; ?>

	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="upfw-avatar__form">
		<input type="hidden" name="action" value="upfw_avatar">
		<?php wp_nonce_field( 'upfw_avatar' ); ?>

		<span class="upfw-avatar__ahora"><?php echo get_avatar( $user->ID, 88 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado de WordPress. ?></span>

		<div class="upfw-avatar__acciones">
			<label class="upfw-button upfw-button--soft" for="upfw-avatar-file">
				<?php esc_html_e( 'Choose a photo', 'users-plus-for-wordpress' ); ?>
				<input type="file" id="upfw-avatar-file" name="upfw_avatar_file" accept="image/jpeg,image/png,image/gif,image/webp">
			</label>

			<button type="submit" class="upfw-button"><?php esc_html_e( 'Save', 'users-plus-for-wordpress' ); ?></button>

			<?php if ( $has ) : ?>
				<button type="submit" name="upfw_avatar_remove" value="1" class="upfw-button upfw-button--soft"><?php esc_html_e( 'Remove it', 'users-plus-for-wordpress' ); ?></button>
			<?php endif; ?>
		</div>

		<p class="upfw-note">
			<?php
			echo esc_html(
				sprintf(
				/* translators: %s: tamaño máximo ya formateado */
					__( 'JPG, PNG, GIF or WebP, up to %s.', 'users-plus-for-wordpress' ),
					size_format( max( 1, (int) upfw_option( 'upfw_avatar_max_kb' ) ) * KB_IN_BYTES )
				)
			);
			?>
		</p>
	</form>
<?php upfw_panel_close(); ?>
