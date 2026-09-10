<?php
/**
 * El formulario de acceso.
 *
 * Reemplazable desde el tema en:
 *   wp-content/themes/<tu-tema>/users-dlx-plus/acceso.php
 *
 * @var string                            $state      Qué pasó ('sent', 'expired', 'email', 'error', 'social').
 * @var string                            $email      Correo al que se mandó el enlace.
 * @var array<string, array<string,mixed>> $providers Redes disponibles.
 * @var int                               $minutes     Vigencia del enlace.
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="users-dlx-plus users-dlx-plus-login">

	<?php if ( 'sent' === $state ) : ?>

		<h2 class="users-dlx-plus-login__title"><?php esc_html_e( 'Check your email', 'users-dlx-plus' ); ?></h2>
		<p><?php esc_html_e( 'We sent a sign-in link to', 'users-dlx-plus' ); ?></p>
		<p class="users-dlx-plus-login__email"><strong><?php echo esc_html( $email ); ?></strong></p>
		<p class="users-dlx-plus-note">
			<?php
			printf(
				/* translators: %d: minutos de vigencia */
				esc_html__( 'Click the link and you are in. It expires in %d minutes and works once.', 'users-dlx-plus' ),
				(int) $minutes
			);
			?>
		</p>
		<p class="users-dlx-plus-note"><?php esc_html_e( 'Did not arrive? Check your spam or promotions folder.', 'users-dlx-plus' ); ?></p>

	<?php else : ?>

		<h2 class="users-dlx-plus-login__title"><?php esc_html_e( 'Sign in', 'users-dlx-plus' ); ?></h2>

		<?php if ( 'expired' === $state ) : ?>
			<p class="users-dlx-plus-notice users-dlx-plus-notice--error"><?php esc_html_e( 'That link expired or was already used. Ask for a new one.', 'users-dlx-plus' ); ?></p>
		<?php elseif ( 'email' === $state ) : ?>
			<p class="users-dlx-plus-notice users-dlx-plus-notice--error"><?php esc_html_e( 'That email address does not look valid.', 'users-dlx-plus' ); ?></p>
		<?php elseif ( 'social' === $state ) : ?>
			<p class="users-dlx-plus-notice users-dlx-plus-notice--error"><?php esc_html_e( 'We could not finish signing you in with that provider. Try again or use your email.', 'users-dlx-plus' ); ?></p>
		<?php elseif ( 'error' === $state ) : ?>
			<p class="users-dlx-plus-notice users-dlx-plus-notice--error"><?php esc_html_e( 'Something went wrong. Try again.', 'users-dlx-plus' ); ?></p>
		<?php endif; ?>

		<?php if ( users_dlx_plus_passkeys_enabled() ) : ?>
			<?php users_dlx_plus_passkeys_enqueue(); ?>
			<p class="users-dlx-plus-notice" data-users-dlx-plus-passkey-aviso hidden></p>
			<p><button type="button" class="users-dlx-plus-button users-dlx-plus-button--ancho" data-users-dlx-plus-passkey="login"><?php esc_html_e( 'Sign in with a passkey', 'users-dlx-plus' ); ?></button></p>
			<p class="users-dlx-plus-divider"><span><?php esc_html_e( 'or', 'users-dlx-plus' ); ?></span></p>
		<?php endif; ?>

		<?php if ( array() !== $providers ) : ?>
			<?php echo users_dlx_plus_sso_buttons( $providers ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio, ya escapado. ?>

			<p class="users-dlx-plus-divider">
				<span>
					<?php
					echo users_dlx_plus_login_has_link()
						? esc_html__( 'or with your email', 'users-dlx-plus' )
						: esc_html__( 'or with your password', 'users-dlx-plus' );
					?>
				</span>
			</p>
		<?php endif; ?>

		<?php if ( users_dlx_plus_login_has_link() ) : ?>
			<form class="users-dlx-plus-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="users_dlx_plus_login">
				<?php wp_nonce_field( 'users_dlx_plus_login', 'users_dlx_plus_nonce' ); ?>

				<label for="users-dlx-plus-email"><?php esc_html_e( 'Email address', 'users-dlx-plus' ); ?></label>
				<input type="email" id="users-dlx-plus-email" name="users_dlx_plus_email" required autocomplete="email" placeholder="vos@ejemplo.com">

				<button type="submit" class="users-dlx-plus-button"><?php esc_html_e( 'Send me the sign-in link', 'users-dlx-plus' ); ?></button>
			</form>

			<p class="users-dlx-plus-note"><?php esc_html_e( 'You get an email with a link. Click it and you are in: no password to choose or type.', 'users-dlx-plus' ); ?></p>
		<?php endif; ?>

		<?php if ( users_dlx_plus_login_has_password() ) : ?>
			<?php if ( users_dlx_plus_login_has_link() ) : ?>
				<p class="users-dlx-plus-divider"><span><?php esc_html_e( 'or with your password', 'users-dlx-plus' ); ?></span></p>
			<?php endif; ?>

			<?php
			// El formulario de WordPress, no uno propio: ya trae el
			// «recordarme», el redirect y el nonce, y es la parte que menos
			// falta hace tocar.
			wp_login_form(
				array(
					'redirect'       => (string) apply_filters( 'users_dlx_plus_login_redirect', home_url( '/' ), 0 ),
					'label_username' => __( 'Email or username', 'users-dlx-plus' ),
					'label_password' => __( 'Password', 'users-dlx-plus' ),
					'label_log_in'   => __( 'Sign in', 'users-dlx-plus' ),
				)
			);
			?>

			<p class="users-dlx-plus-note">
				<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'I forgot my password', 'users-dlx-plus' ); ?></a>
			</p>
		<?php endif; ?>

	<?php endif; ?>
</div>
