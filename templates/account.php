<?php
/**
 * El área de cuenta.
 *
 * @var string                              $current
 * @var bool                                $header
 * @var string                              $layout
 * @var array<string, array<string, mixed>> $sections
 * @var WP_User                             $user
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="users-plus-account users-plus-account--<?php echo esc_attr( $layout ); ?>">

	<?php if ( $header ) : ?>
		<div class="users-plus-account__header">
			<span class="users-plus-account__avatar"><?php echo get_avatar( $user->ID, 64 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado de WordPress. ?></span>
			<div>
				<h1 class="users-plus-account__name"><?php echo esc_html( users_plus_display_name( $user ) ); ?></h1>
				<p class="users-plus-account__since">
					<?php
					echo esc_html(
						sprintf(
						/* translators: %s: mes y año de alta */
							__( 'Member since %s', 'users-plus' ),
							wp_date( 'F Y', (int) strtotime( $user->user_registered ) )
						)
					);
					?>
				</p>
			</div>
		</div>
	<?php endif; ?>

	<div class="users-plus-account__cuerpo">
		<?php if ( 'none' !== $layout ) : ?>
			<?php echo users_plus_account_nav( $sections, $current ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio, ya escapado. ?>
		<?php endif; ?>

		<div class="users-plus-account__seccion">
			<?php
			$users_plus_section = $sections[ $current ];

			echo users_plus_account_heading_html( $users_plus_section, $current, $user ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado adentro.
			echo users_plus_account_section_html( $users_plus_section, $user ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- saneado adentro.
			?>
		</div>
	</div>
</div>
