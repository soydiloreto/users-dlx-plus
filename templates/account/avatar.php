<?php
/**
 * La foto de perfil.
 *
 * @var string  $error
 * @var bool    $has
 * @var WP_User $user
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;
?>
<?php users_dlx_plus_panel_open( __( 'Your photo', 'users-dlx-plus' ), true, 'users-dlx-plus-avatar' ); ?>

	<?php if ( '' !== $error ) : ?>
		<p class="users-dlx-plus-notice users-dlx-plus-notice--error"><?php echo esc_html( $error ); ?></p>
	<?php endif; ?>

	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="users-dlx-plus-avatar__form">
		<input type="hidden" name="action" value="users_dlx_plus_avatar">
		<?php wp_nonce_field( 'users_dlx_plus_avatar' ); ?>

		<span class="users-dlx-plus-avatar__ahora"><?php echo get_avatar( $user->ID, 88 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado de WordPress. ?></span>

		<div class="users-dlx-plus-avatar__acciones">
			<label class="users-dlx-plus-button users-dlx-plus-button--soft" for="users-dlx-plus-avatar-file">
				<?php esc_html_e( 'Choose a photo', 'users-dlx-plus' ); ?>
				<input type="file" id="users-dlx-plus-avatar-file" name="users_dlx_plus_avatar_file" accept="image/jpeg,image/png,image/gif,image/webp">
			</label>

			<button type="submit" class="users-dlx-plus-button"><?php esc_html_e( 'Save', 'users-dlx-plus' ); ?></button>

			<?php if ( $has ) : ?>
				<button type="submit" name="users_dlx_plus_avatar_remove" value="1" class="users-dlx-plus-button users-dlx-plus-button--soft"><?php esc_html_e( 'Remove it', 'users-dlx-plus' ); ?></button>
			<?php endif; ?>
		</div>

		<p class="users-dlx-plus-note">
			<?php
			echo esc_html(
				sprintf(
				/* translators: %s: tamaño máximo ya formateado */
					__( 'JPG, PNG, GIF or WebP, up to %s.', 'users-dlx-plus' ),
					size_format( max( 1, (int) users_dlx_plus_option( 'users_dlx_plus_avatar_max_kb' ) ) * KB_IN_BYTES )
				)
			);
			?>
		</p>
	</form>
<?php users_dlx_plus_panel_close(); ?>
