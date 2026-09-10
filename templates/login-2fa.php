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
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;

$users_plus_actual = $methods[ $method ] ?? array(
	'label' => '',
	'help'  => '',
);
?>
<div class="users-plus users-plus-login users-plus-login--2fa">
	<h2 class="users-plus-login__title"><?php esc_html_e( 'One more step', 'users-plus' ); ?></h2>
	<p><?php echo esc_html( (string) $users_plus_actual['help'] ); ?></p>

	<?php if ( 'code' === $state ) : ?>
		<p class="users-plus-notice users-plus-notice--error"><?php esc_html_e( 'That code is not right, or it expired. Try the next one.', 'users-plus' ); ?></p>
	<?php elseif ( 'sent' === $state ) : ?>
		<p class="users-plus-notice users-plus-notice--ok"><?php esc_html_e( 'Sent. Check your email.', 'users-plus' ); ?></p>
	<?php endif; ?>

	<form class="users-plus-form" method="post" action="">
		<input type="hidden" name="users_plus_2fa_user" value="<?php echo esc_attr( (string) $user_id ); ?>">
		<input type="hidden" name="users_plus_2fa_key" value="<?php echo esc_attr( $key ); ?>">
		<input type="hidden" name="users_plus_2fa_method" value="<?php echo esc_attr( $method ); ?>">

		<label for="users-plus-2fa-code"><?php esc_html_e( 'The code', 'users-plus' ); ?></label>
		<input type="text" id="users-plus-2fa-code" name="users_plus_2fa_code" inputmode="numeric" autocomplete="one-time-code"
			maxlength="20" autofocus required>

		<?php if ( (int) users_plus_option( 'users_plus_2fa_remember_days' ) > 0 ) : ?>
			<label class="users-plus-check">
				<input type="checkbox" name="users_plus_2fa_trust" value="1">
				<span>
					<?php
					echo esc_html(
						sprintf(
						/* translators: %d: días */
							__( 'Do not ask again on this browser for %d days', 'users-plus' ),
							(int) users_plus_option( 'users_plus_2fa_remember_days' )
						)
					);
					?>
				</span>
			</label>
		<?php endif; ?>

		<button type="submit" class="users-plus-button"><?php esc_html_e( 'Confirm', 'users-plus' ); ?></button>

		<?php if ( isset( $methods[ $method ]['send'] ) ) : ?>
			<button type="submit" name="users_plus_2fa_resend" value="1" class="users-plus-button users-plus-button--soft"><?php esc_html_e( 'Send it again', 'users-plus' ); ?></button>
		<?php endif; ?>
	</form>

	<?php if ( count( $methods ) > 1 ) : ?>
		<p class="users-plus-note">
			<?php esc_html_e( 'Or use:', 'users-plus' ); ?>
			<?php foreach ( $methods as $users_plus_id => $users_plus_m ) : ?>
				<?php if ( $users_plus_id !== $method ) : ?>
					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'users_plus_2fa'    => $user_id,
								'users_plus_key'    => $key,
								'users_plus_method' => $users_plus_id,
							),
							users_plus_login_url()
						)
					);
					?>
								"><?php echo esc_html( $users_plus_m['label'] ); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>
		</p>
	<?php endif; ?>

	<p class="users-plus-note"><?php esc_html_e( 'Lost the phone and the email? Use one of your backup codes: they go in the same box.', 'users-plus' ); ?></p>
</div>
