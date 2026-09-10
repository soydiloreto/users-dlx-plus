<?php
/**
 * Notificaciones.
 *
 * Variables: $user, $prefs.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;
?>

<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige el mensaje. ?>
<?php if ( isset( $_GET['upfw'] ) && 'saved' === sanitize_key( wp_unslash( $_GET['upfw'] ) ) ) : ?>
	<p class="upfw-notice upfw-notice--ok"><?php esc_html_e( 'Saved.', 'users-plus-for-wordpress' ); ?></p>
<?php endif; ?>

<?php if ( array() === $prefs ) : ?>
	<p><?php esc_html_e( 'This site does not send any notifications you can turn off.', 'users-plus-for-wordpress' ); ?></p>
<?php else : ?>
	<?php upfw_panel_open( __( 'What we email you about', 'users-plus-for-wordpress' ), true ); ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="upfw-form">
		<input type="hidden" name="action" value="upfw_notifications">
		<?php wp_nonce_field( 'upfw_notifications' ); ?>

		<?php
		foreach ( $prefs as $upfw_key => $upfw_pref ) :
			$upfw_saved = get_user_meta( $user->ID, $upfw_key, true );
			$upfw_on    = '' === (string) $upfw_saved ? ! empty( $upfw_pref['default'] ) : (bool) $upfw_saved;
			?>
			<label class="upfw-check">
				<input type="checkbox" name="<?php echo esc_attr( $upfw_key ); ?>" value="1" <?php checked( $upfw_on ); ?>>
				<span>
					<?php echo esc_html( $upfw_pref['label'] ); ?>
					<?php if ( ! empty( $upfw_pref['help'] ) ) : ?>
						<small><?php echo esc_html( $upfw_pref['help'] ); ?></small>
					<?php endif; ?>
				</span>
			</label>
		<?php endforeach; ?>

		<button type="submit" class="upfw-button"><?php esc_html_e( 'Save', 'users-plus-for-wordpress' ); ?></button>
	</form>
	<?php upfw_panel_close(); ?>
<?php endif; ?>
