<?php
/**
 * Las redes sociales vinculadas a la cuenta.
 *
 * Reemplazable desde el tema en:
 *   wp-content/themes/<tu-tema>/users-plus-for-wordpress/accounts.php
 *
 * @var array<string, array<string, mixed>> $providers Redes disponibles.
 * @var array<int, string>                  $linked    IDs ya vinculados.
 * @var string                              $state
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="upfw upfw-accounts">

	<?php if ( 'linked' === $state ) : ?>
		<p class="upfw-notice upfw-notice--ok"><?php esc_html_e( 'Account linked.', 'users-plus-for-wordpress' ); ?></p>
	<?php endif; ?>

	<?php if ( array() === $providers ) : ?>
		<p class="upfw-note">
			<?php
			if ( 'linked' === ( $only ?? '' ) ) {
				esc_html_e( 'None yet. Link one below and it opens this same account.', 'users-plus-for-wordpress' );
			} elseif ( 'available' === ( $only ?? '' ) ) {
				esc_html_e( 'You already have them all linked.', 'users-plus-for-wordpress' );
			} else {
				esc_html_e( 'No provider has been set up yet.', 'users-plus-for-wordpress' );
			}
			?>
		</p>
	<?php else : ?>

	<ul class="upfw-linked">
		<?php
		foreach ( $providers as $upfw_id => $upfw_provider ) :
			$upfw_is_linked = in_array( $upfw_id, $linked, true );
			?>
			<?php
			/*
			 * Un logo con color propio —el de Google, el de Microsoft— no se
			 * pinta encima: se deja sobre fondo claro, que es lo que piden
			 * sus guías y lo único donde se lee.
			 */
			?>
			<li class="upfw-linked__item <?php echo $upfw_is_linked ? 'is-linked' : ''; ?> <?php echo upfw_sso_icon_is_colored( $upfw_id ) ? 'has-color' : ''; ?>" style="--upfw-brand: <?php echo esc_attr( $upfw_provider['color'] ); ?>">
				<span class="upfw-linked__logo"><?php echo upfw_sso_icon( $upfw_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG propio. ?></span>

				<span class="upfw-linked__who">
					<strong><?php echo esc_html( $upfw_provider['name'] ); ?></strong>
					<span>
						<?php
						echo $upfw_is_linked
							? esc_html__( 'Linked to your account', 'users-plus-for-wordpress' )
							: esc_html__( 'Not linked', 'users-plus-for-wordpress' );
						?>
					</span>
				</span>

				<?php if ( $upfw_is_linked ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="upfw_sso_unlink">
						<input type="hidden" name="upfw_provider" value="<?php echo esc_attr( $upfw_id ); ?>">
						<?php wp_nonce_field( 'upfw_sso_unlink' ); ?>
						<button type="submit" class="upfw-button upfw-button--soft"><?php esc_html_e( 'Unlink', 'users-plus-for-wordpress' ); ?></button>
					</form>
				<?php else : ?>
					<a class="upfw-button upfw-button--soft" href="<?php echo esc_url( upfw_sso_login_url( $upfw_id ) ); ?>">
						<?php esc_html_e( 'Link', 'users-plus-for-wordpress' ); ?>
					</a>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php endif; ?>
</div>
