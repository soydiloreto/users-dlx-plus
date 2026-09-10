<?php
/**
 * Las redes sociales vinculadas a la cuenta.
 *
 * Reemplazable desde el tema en:
 *   wp-content/themes/<tu-tema>/users-plus/accounts.php
 *
 * @var array<string, array<string, mixed>> $providers Redes disponibles.
 * @var array<int, string>                  $linked    IDs ya vinculados.
 * @var string                              $state
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="users-plus users-plus-accounts">

	<?php if ( 'linked' === $state ) : ?>
		<p class="users-plus-notice users-plus-notice--ok"><?php esc_html_e( 'Account linked.', 'users-plus' ); ?></p>
	<?php endif; ?>

	<?php if ( array() === $providers ) : ?>
		<p class="users-plus-note">
			<?php
			if ( 'linked' === ( $only ?? '' ) ) {
				esc_html_e( 'None yet. Link one below and it opens this same account.', 'users-plus' );
			} elseif ( 'available' === ( $only ?? '' ) ) {
				esc_html_e( 'You already have them all linked.', 'users-plus' );
			} else {
				esc_html_e( 'No provider has been set up yet.', 'users-plus' );
			}
			?>
		</p>
	<?php else : ?>

	<ul class="users-plus-linked">
		<?php
		foreach ( $providers as $users_plus_id => $users_plus_provider ) :
			$users_plus_is_linked = in_array( $users_plus_id, $linked, true );
			?>
			<?php
			/*
			 * Un logo con color propio —el de Google, el de Microsoft— no se
			 * pinta encima: se deja sobre fondo claro, que es lo que piden
			 * sus guías y lo único donde se lee.
			 */
			?>
			<li class="users-plus-linked__item <?php echo $users_plus_is_linked ? 'is-linked' : ''; ?> <?php echo users_plus_sso_icon_is_colored( $users_plus_id ) ? 'has-color' : ''; ?>" style="--users-plus-brand: <?php echo esc_attr( $users_plus_provider['color'] ); ?>">
				<span class="users-plus-linked__logo"><?php echo users_plus_sso_icon( $users_plus_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG propio. ?></span>

				<span class="users-plus-linked__who">
					<strong><?php echo esc_html( $users_plus_provider['name'] ); ?></strong>
					<span>
						<?php
						echo $users_plus_is_linked
							? esc_html__( 'Linked to your account', 'users-plus' )
							: esc_html__( 'Not linked', 'users-plus' );
						?>
					</span>
				</span>

				<?php if ( $users_plus_is_linked ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="users_plus_sso_unlink">
						<input type="hidden" name="users_plus_provider" value="<?php echo esc_attr( $users_plus_id ); ?>">
						<?php wp_nonce_field( 'users_plus_sso_unlink' ); ?>
						<button type="submit" class="users-plus-button users-plus-button--soft"><?php esc_html_e( 'Unlink', 'users-plus' ); ?></button>
					</form>
				<?php else : ?>
					<a class="users-plus-button users-plus-button--soft" href="<?php echo esc_url( users_plus_sso_login_url( $users_plus_id ) ); ?>">
						<?php esc_html_e( 'Link', 'users-plus' ); ?>
					</a>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php endif; ?>
</div>
