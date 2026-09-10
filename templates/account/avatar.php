<?php
/**
 * La foto de perfil.
 *
 * @var string  $error
 * @var bool    $has
 * @var WP_User $user
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;
?>
<?php users_plus_panel_open( __( 'Your photo', 'users-plus' ), true, 'users-plus-avatar' ); ?>

	<?php if ( '' !== $error ) : ?>
		<p class="users-plus-notice users-plus-notice--error"><?php echo esc_html( $error ); ?></p>
	<?php endif; ?>

	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="users-plus-avatar__form">
		<input type="hidden" name="action" value="users_plus_avatar">
		<?php wp_nonce_field( 'users_plus_avatar' ); ?>

		<span class="users-plus-avatar__ahora"><?php echo get_avatar( $user->ID, 88 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado de WordPress. ?></span>

		<div class="users-plus-avatar__acciones">
			<label class="users-plus-button users-plus-button--soft" for="users-plus-avatar-file">
				<?php esc_html_e( 'Choose a photo', 'users-plus' ); ?>
				<input type="file" id="users-plus-avatar-file" name="users_plus_avatar_file" accept="image/jpeg,image/png,image/gif,image/webp">
			</label>

			<button type="submit" class="users-plus-button"><?php esc_html_e( 'Save', 'users-plus' ); ?></button>

			<?php if ( $has ) : ?>
				<button type="submit" name="users_plus_avatar_remove" value="1" class="users-plus-button users-plus-button--soft"><?php esc_html_e( 'Remove it', 'users-plus' ); ?></button>
			<?php endif; ?>
		</div>

		<p class="users-plus-note">
			<?php
			echo esc_html(
				sprintf(
				/* translators: %s: tamaño máximo ya formateado */
					__( 'JPG, PNG, GIF or WebP, up to %s.', 'users-plus' ),
					size_format( max( 1, (int) users_plus_option( 'users_plus_avatar_max_kb' ) ) * KB_IN_BYTES )
				)
			);
			?>
		</p>
	</form>
<?php users_plus_panel_close(); ?>
