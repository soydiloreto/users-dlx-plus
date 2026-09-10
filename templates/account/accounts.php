<?php
/**
 * Cuentas vinculadas.
 *
 * Variables: $user.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;
?>

<?php upfw_panel_open( __( 'Networks you can sign in with', 'users-plus-for-wordpress' ), true ); ?>
	<p><?php esc_html_e( 'Any of these opens this same account. Unlink the ones you do not use.', 'users-plus-for-wordpress' ); ?></p>
	<?php echo do_shortcode( '[upfw_accounts only="linked"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode propio. ?>
<?php upfw_panel_close(); ?>

<?php upfw_panel_open( __( 'Networks you can link', 'users-plus-for-wordpress' ) ); ?>
	<p><?php esc_html_e( 'Add one and from then on it opens this account too. Nothing gets duplicated: it is the same account with another way in.', 'users-plus-for-wordpress' ); ?></p>
	<?php echo do_shortcode( '[upfw_accounts only="available"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode propio. ?>
<?php upfw_panel_close(); ?>
