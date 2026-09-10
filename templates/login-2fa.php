<?php
/**
 * El segundo paso del ingreso.
 *
 * @var string                              $key
 * @var string                              $method
 * @var array<string, array<string, mixed>> $methods
 * @var string                              $state
 * @var int                                 $user_id
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

$upfw_actual = $methods[ $method ] ?? array(
	'label' => '',
	'help'  => '',
);
?>
<div class="upfw upfw-login upfw-login--2fa">
	<h2 class="upfw-login__title"><?php esc_html_e( 'One more step', 'users-plus-for-wordpress' ); ?></h2>
	<p><?php echo esc_html( (string) $upfw_actual['help'] ); ?></p>

	<?php if ( 'code' === $state ) : ?>
		<p class="upfw-notice upfw-notice--error"><?php esc_html_e( 'That code is not right, or it expired. Try the next one.', 'users-plus-for-wordpress' ); ?></p>
	<?php elseif ( 'sent' === $state ) : ?>
		<p class="upfw-notice upfw-notice--ok"><?php esc_html_e( 'Sent. Check your email.', 'users-plus-for-wordpress' ); ?></p>
	<?php endif; ?>

	<form class="upfw-form" method="post" action="">
		<input type="hidden" name="upfw_2fa_user" value="<?php echo esc_attr( (string) $user_id ); ?>">
		<input type="hidden" name="upfw_2fa_key" value="<?php echo esc_attr( $key ); ?>">
		<input type="hidden" name="upfw_2fa_method" value="<?php echo esc_attr( $method ); ?>">

		<label for="upfw-2fa-code"><?php esc_html_e( 'The code', 'users-plus-for-wordpress' ); ?></label>
		<input type="text" id="upfw-2fa-code" name="upfw_2fa_code" inputmode="numeric" autocomplete="one-time-code"
			maxlength="20" autofocus required>

		<?php if ( (int) upfw_option( 'upfw_2fa_remember_days' ) > 0 ) : ?>
			<label class="upfw-check">
				<input type="checkbox" name="upfw_2fa_trust" value="1">
				<span>
					<?php
					echo esc_html(
						sprintf(
						/* translators: %d: días */
							__( 'Do not ask again on this browser for %d days', 'users-plus-for-wordpress' ),
							(int) upfw_option( 'upfw_2fa_remember_days' )
						)
					);
					?>
				</span>
			</label>
		<?php endif; ?>

		<button type="submit" class="upfw-button"><?php esc_html_e( 'Confirm', 'users-plus-for-wordpress' ); ?></button>

		<?php if ( isset( $methods[ $method ]['send'] ) ) : ?>
			<button type="submit" name="upfw_2fa_resend" value="1" class="upfw-button upfw-button--soft"><?php esc_html_e( 'Send it again', 'users-plus-for-wordpress' ); ?></button>
		<?php endif; ?>
	</form>

	<?php if ( count( $methods ) > 1 ) : ?>
		<p class="upfw-note">
			<?php esc_html_e( 'Or use:', 'users-plus-for-wordpress' ); ?>
			<?php foreach ( $methods as $upfw_id => $upfw_m ) : ?>
				<?php if ( $upfw_id !== $method ) : ?>
					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'upfw_2fa'    => $user_id,
								'upfw_key'    => $key,
								'upfw_method' => $upfw_id,
							),
							upfw_login_url()
						)
					);
					?>
								"><?php echo esc_html( $upfw_m['label'] ); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>
		</p>
	<?php endif; ?>

	<p class="upfw-note"><?php esc_html_e( 'Lost the phone and the email? Use one of your backup codes: they go in the same box.', 'users-plus-for-wordpress' ); ?></p>
</div>
