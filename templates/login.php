<?php
/**
 * El formulario de acceso.
 *
 * Reemplazable desde el tema en:
 *   wp-content/themes/<tu-tema>/users-plus-for-wordpress/acceso.php
 *
 * @var string                            $state      Qué pasó ('sent', 'expired', 'email', 'error', 'social').
 * @var string                            $email      Correo al que se mandó el enlace.
 * @var array<string, array<string,mixed>> $providers Redes disponibles.
 * @var int                               $minutes     Vigencia del enlace.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="upfw upfw-login">

	<?php if ( 'sent' === $state ) : ?>

		<h2 class="upfw-login__title"><?php esc_html_e( 'Check your email', 'users-plus-for-wordpress' ); ?></h2>
		<p><?php esc_html_e( 'We sent a sign-in link to', 'users-plus-for-wordpress' ); ?></p>
		<p class="upfw-login__email"><strong><?php echo esc_html( $email ); ?></strong></p>
		<p class="upfw-note">
			<?php
			printf(
				/* translators: %d: minutos de vigencia */
				esc_html__( 'Click the link and you are in. It expires in %d minutes and works once.', 'users-plus-for-wordpress' ),
				(int) $minutes
			);
			?>
		</p>
		<p class="upfw-note"><?php esc_html_e( 'Did not arrive? Check your spam or promotions folder.', 'users-plus-for-wordpress' ); ?></p>

	<?php else : ?>

		<h2 class="upfw-login__title"><?php esc_html_e( 'Sign in', 'users-plus-for-wordpress' ); ?></h2>

		<?php if ( 'expired' === $state ) : ?>
			<p class="upfw-notice upfw-notice--error"><?php esc_html_e( 'That link expired or was already used. Ask for a new one.', 'users-plus-for-wordpress' ); ?></p>
		<?php elseif ( 'email' === $state ) : ?>
			<p class="upfw-notice upfw-notice--error"><?php esc_html_e( 'That email address does not look valid.', 'users-plus-for-wordpress' ); ?></p>
		<?php elseif ( 'social' === $state ) : ?>
			<p class="upfw-notice upfw-notice--error"><?php esc_html_e( 'We could not finish signing you in with that provider. Try again or use your email.', 'users-plus-for-wordpress' ); ?></p>
		<?php elseif ( 'error' === $state ) : ?>
			<p class="upfw-notice upfw-notice--error"><?php esc_html_e( 'Something went wrong. Try again.', 'users-plus-for-wordpress' ); ?></p>
		<?php endif; ?>

		<?php if ( upfw_passkeys_enabled() ) : ?>
			<?php upfw_passkeys_enqueue(); ?>
			<p class="upfw-notice" data-upfw-passkey-aviso hidden></p>
			<p><button type="button" class="upfw-button upfw-button--ancho" data-upfw-passkey="login"><?php esc_html_e( 'Sign in with a passkey', 'users-plus-for-wordpress' ); ?></button></p>
			<p class="upfw-divider"><span><?php esc_html_e( 'or', 'users-plus-for-wordpress' ); ?></span></p>
		<?php endif; ?>

		<?php if ( array() !== $providers ) : ?>
			<?php echo upfw_sso_buttons( $providers ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio, ya escapado. ?>

			<p class="upfw-divider">
				<span>
					<?php
					echo upfw_login_has_link()
						? esc_html__( 'or with your email', 'users-plus-for-wordpress' )
						: esc_html__( 'or with your password', 'users-plus-for-wordpress' );
					?>
				</span>
			</p>
		<?php endif; ?>

		<?php if ( upfw_login_has_link() ) : ?>
			<form class="upfw-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="upfw_login">
				<?php wp_nonce_field( 'upfw_login', 'upfw_nonce' ); ?>

				<label for="upfw-email"><?php esc_html_e( 'Email address', 'users-plus-for-wordpress' ); ?></label>
				<input type="email" id="upfw-email" name="upfw_email" required autocomplete="email" placeholder="vos@ejemplo.com">

				<button type="submit" class="upfw-button"><?php esc_html_e( 'Send me the sign-in link', 'users-plus-for-wordpress' ); ?></button>
			</form>

			<p class="upfw-note"><?php esc_html_e( 'You get an email with a link. Click it and you are in: no password to choose or type.', 'users-plus-for-wordpress' ); ?></p>
		<?php endif; ?>

		<?php if ( upfw_login_has_password() ) : ?>
			<?php if ( upfw_login_has_link() ) : ?>
				<p class="upfw-divider"><span><?php esc_html_e( 'or with your password', 'users-plus-for-wordpress' ); ?></span></p>
			<?php endif; ?>

			<?php
			// El formulario de WordPress, no uno propio: ya trae el
			// «recordarme», el redirect y el nonce, y es la parte que menos
			// falta hace tocar.
			wp_login_form(
				array(
					'redirect'       => (string) apply_filters( 'upfw_login_redirect', home_url( '/' ), 0 ),
					'label_username' => __( 'Email or username', 'users-plus-for-wordpress' ),
					'label_password' => __( 'Password', 'users-plus-for-wordpress' ),
					'label_log_in'   => __( 'Sign in', 'users-plus-for-wordpress' ),
				)
			);
			?>

			<p class="upfw-note">
				<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'I forgot my password', 'users-plus-for-wordpress' ); ?></a>
			</p>
		<?php endif; ?>

	<?php endif; ?>
</div>
