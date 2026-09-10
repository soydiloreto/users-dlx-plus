<?php
/**
 * Notificaciones.
 *
 * @var array<string, array<string, string>> $prefs
 * @var WP_User                              $user
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;
?>

<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige el mensaje. ?>
<?php if ( isset( $_GET['users-plus'] ) && 'saved' === sanitize_key( wp_unslash( $_GET['users-plus'] ) ) ) : ?>
	<p class="users-plus-notice users-plus-notice--ok"><?php esc_html_e( 'Saved.', 'users-plus' ); ?></p>
<?php endif; ?>

<?php if ( array() === $prefs ) : ?>
	<p><?php esc_html_e( 'This site does not send any notifications you can turn off.', 'users-plus' ); ?></p>
<?php else : ?>
	<?php users_plus_panel_open( __( 'What we email you about', 'users-plus' ), true ); ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="users-plus-form">
		<input type="hidden" name="action" value="users_plus_notifications">
		<?php wp_nonce_field( 'users_plus_notifications' ); ?>

		<?php
		foreach ( $prefs as $users_plus_key => $users_plus_pref ) :
			$users_plus_saved = get_user_meta( $user->ID, $users_plus_key, true );
			$users_plus_on    = '' === (string) $users_plus_saved ? ! empty( $users_plus_pref['default'] ) : (bool) $users_plus_saved;
			?>
			<label class="users-plus-check">
				<input type="checkbox" name="<?php echo esc_attr( $users_plus_key ); ?>" value="1" <?php checked( $users_plus_on ); ?>>
				<span>
					<?php echo esc_html( $users_plus_pref['label'] ); ?>
					<?php if ( ! empty( $users_plus_pref['help'] ) ) : ?>
						<small><?php echo esc_html( $users_plus_pref['help'] ); ?></small>
					<?php endif; ?>
				</span>
			</label>
		<?php endforeach; ?>

		<button type="submit" class="users-plus-button"><?php esc_html_e( 'Save', 'users-plus' ); ?></button>
	</form>
	<?php users_plus_panel_close(); ?>
<?php endif; ?>
