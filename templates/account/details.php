<?php
/**
 * Datos personales.
 *
 * Variables: $user.
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;
?>
<?php echo do_shortcode( '[users_plus_avatar]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode propio. ?>
<?php echo do_shortcode( '[users_plus_handle]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode propio. ?>
<?php
echo do_shortcode( '[users_plus_fields]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode propio. 
