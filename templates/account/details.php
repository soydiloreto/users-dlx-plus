<?php
/**
 * Datos personales.
 *
 * Variables: $user.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;
?>
<?php echo do_shortcode( '[upfw_avatar]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode propio. ?>
<?php echo do_shortcode( '[upfw_handle]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode propio. ?>
<?php
echo do_shortcode( '[upfw_fields]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode propio. 
