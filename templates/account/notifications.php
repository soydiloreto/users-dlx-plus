<?php
/**
 * Notificaciones.
 *
 * @var array<string, array<string, string>> $prefs
 * @var WP_User                              $user
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;
?>

<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige el mensaje. ?>
<?php if ( isset( $_GET['users-dlx-plus'] ) && 'saved' === sanitize_key( wp_unslash( $_GET['users-dlx-plus'] ) ) ) : ?>
	<p class="users-dlx-plus-notice users-dlx-plus-notice--ok"><?php esc_html_e( 'Saved.', 'users-dlx-plus' ); ?></p>
<?php endif; ?>

<?php if ( array() === $prefs ) : ?>
	<p><?php esc_html_e( 'This site does not send any notifications you can turn off.', 'users-dlx-plus' ); ?></p>
<?php else : ?>
	<?php users_dlx_plus_panel_open( __( 'What we email you about', 'users-dlx-plus' ), true ); ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="users-dlx-plus-form">
		<input type="hidden" name="action" value="users_dlx_plus_notifications">
		<?php wp_nonce_field( 'users_dlx_plus_notifications' ); ?>

		<?php
		foreach ( $prefs as $users_dlx_plus_key => $users_dlx_plus_pref ) :
			$users_dlx_plus_saved = get_user_meta( $user->ID, $users_dlx_plus_key, true );
			$users_dlx_plus_on    = '' === (string) $users_dlx_plus_saved ? ! empty( $users_dlx_plus_pref['default'] ) : (bool) $users_dlx_plus_saved;
			?>
			<label class="users-dlx-plus-check">
				<input type="checkbox" name="<?php echo esc_attr( $users_dlx_plus_key ); ?>" value="1" <?php checked( $users_dlx_plus_on ); ?>>
				<span>
					<?php echo esc_html( $users_dlx_plus_pref['label'] ); ?>
					<?php if ( ! empty( $users_dlx_plus_pref['help'] ) ) : ?>
						<small><?php echo esc_html( $users_dlx_plus_pref['help'] ); ?></small>
					<?php endif; ?>
				</span>
			</label>
		<?php endforeach; ?>

		<button type="submit" class="users-dlx-plus-button"><?php esc_html_e( 'Save', 'users-dlx-plus' ); ?></button>
	</form>
	<?php users_dlx_plus_panel_close(); ?>
<?php endif; ?>
