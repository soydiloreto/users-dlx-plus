<?php
/**
 * Las redes sociales vinculadas a la cuenta.
 *
 * Reemplazable desde el tema en:
 *   wp-content/themes/<tu-tema>/users-dlx-plus/accounts.php
 *
 * @var array<string, array<string, mixed>> $providers Redes disponibles.
 * @var array<int, string>                  $linked    IDs ya vinculados.
 * @var string                              $state
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="users-dlx-plus users-dlx-plus-accounts">

	<?php if ( 'linked' === $state ) : ?>
		<p class="users-dlx-plus-notice users-dlx-plus-notice--ok"><?php esc_html_e( 'Account linked.', 'users-dlx-plus' ); ?></p>
	<?php endif; ?>

	<?php if ( array() === $providers ) : ?>
		<p class="users-dlx-plus-note">
			<?php
			if ( 'linked' === ( $only ?? '' ) ) {
				esc_html_e( 'None yet. Link one below and it opens this same account.', 'users-dlx-plus' );
			} elseif ( 'available' === ( $only ?? '' ) ) {
				esc_html_e( 'You already have them all linked.', 'users-dlx-plus' );
			} else {
				esc_html_e( 'No provider has been set up yet.', 'users-dlx-plus' );
			}
			?>
		</p>
	<?php else : ?>

	<ul class="users-dlx-plus-linked">
		<?php
		foreach ( $providers as $users_dlx_plus_id => $users_dlx_plus_provider ) :
			$users_dlx_plus_is_linked = in_array( $users_dlx_plus_id, $linked, true );
			?>
			<?php
			/*
			 * Un logo con color propio —el de Google, el de Microsoft— no se
			 * pinta encima: se deja sobre fondo claro, que es lo que piden
			 * sus guías y lo único donde se lee.
			 */
			?>
			<li class="users-dlx-plus-linked__item <?php echo $users_dlx_plus_is_linked ? 'is-linked' : ''; ?> <?php echo users_dlx_plus_sso_icon_is_colored( $users_dlx_plus_id ) ? 'has-color' : ''; ?>" style="--users-dlx-plus-brand: <?php echo esc_attr( $users_dlx_plus_provider['color'] ); ?>">
				<span class="users-dlx-plus-linked__logo"><?php echo users_dlx_plus_sso_icon( $users_dlx_plus_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG propio. ?></span>

				<span class="users-dlx-plus-linked__who">
					<strong><?php echo esc_html( $users_dlx_plus_provider['name'] ); ?></strong>
					<span>
						<?php
						echo $users_dlx_plus_is_linked
							? esc_html__( 'Linked to your account', 'users-dlx-plus' )
							: esc_html__( 'Not linked', 'users-dlx-plus' );
						?>
					</span>
				</span>

				<?php if ( $users_dlx_plus_is_linked ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="users_dlx_plus_sso_unlink">
						<input type="hidden" name="users_dlx_plus_provider" value="<?php echo esc_attr( $users_dlx_plus_id ); ?>">
						<?php wp_nonce_field( 'users_dlx_plus_sso_unlink' ); ?>
						<button type="submit" class="users-dlx-plus-button users-dlx-plus-button--soft"><?php esc_html_e( 'Unlink', 'users-dlx-plus' ); ?></button>
					</form>
				<?php else : ?>
					<a class="users-dlx-plus-button users-dlx-plus-button--soft" href="<?php echo esc_url( users_dlx_plus_sso_login_url( $users_dlx_plus_id ) ); ?>">
						<?php esc_html_e( 'Link', 'users-dlx-plus' ); ?>
					</a>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php endif; ?>
</div>
