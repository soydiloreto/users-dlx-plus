<?php
/**
 * Cuentas vinculadas.
 *
 * Variables: $user.
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;
?>

<?php users_dlx_plus_panel_open( __( 'Networks you can sign in with', 'users-dlx-plus' ), true ); ?>
	<p><?php esc_html_e( 'Any of these opens this same account. Unlink the ones you do not use.', 'users-dlx-plus' ); ?></p>
	<?php echo do_shortcode( '[users_dlx_plus_accounts only="linked"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode propio. ?>
<?php users_dlx_plus_panel_close(); ?>

<?php users_dlx_plus_panel_open( __( 'Networks you can link', 'users-dlx-plus' ) ); ?>
	<p><?php esc_html_e( 'Add one and from then on it opens this account too. Nothing gets duplicated: it is the same account with another way in.', 'users-dlx-plus' ); ?></p>
	<?php echo do_shortcode( '[users_dlx_plus_accounts only="available"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode propio. ?>
<?php users_dlx_plus_panel_close(); ?>
