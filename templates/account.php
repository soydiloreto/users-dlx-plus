<?php
/**
 * El área de cuenta.
 *
 * Variables: $user, $sections, $current, $layout, $header.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="upfw-account upfw-account--<?php echo esc_attr( $layout ); ?>">

	<?php if ( $header ) : ?>
		<div class="upfw-account__header">
			<span class="upfw-account__avatar"><?php echo get_avatar( $user->ID, 64 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado de WordPress. ?></span>
			<div>
				<h1 class="upfw-account__name"><?php echo esc_html( upfw_display_name( $user ) ); ?></h1>
				<p class="upfw-account__since">
					<?php
					echo esc_html(
						sprintf(
						/* translators: %s: mes y año de alta */
							__( 'Member since %s', 'users-plus-for-wordpress' ),
							wp_date( 'F Y', (int) strtotime( $user->user_registered ) )
						)
					);
					?>
				</p>
			</div>
		</div>
	<?php endif; ?>

	<div class="upfw-account__cuerpo">
		<?php if ( 'none' !== $layout ) : ?>
			<?php echo upfw_account_nav( $sections, $current ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio, ya escapado. ?>
		<?php endif; ?>

		<div class="upfw-account__seccion">
			<?php
			$upfw_section = $sections[ $current ];

			echo upfw_account_heading_html( $upfw_section, $current, $user ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado adentro.
			echo upfw_account_section_html( $upfw_section, $user ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- saneado adentro.
			?>
		</div>
	</div>
</div>
