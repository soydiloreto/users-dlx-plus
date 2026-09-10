<?php
/**
 * Datos personales.
 *
 * Variables: $user.
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;
?>
<?php echo do_shortcode( '[users_dlx_plus_avatar]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode propio. ?>
<?php echo do_shortcode( '[users_dlx_plus_handle]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode propio. ?>
<?php
echo do_shortcode( '[users_dlx_plus_fields]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode propio. 
