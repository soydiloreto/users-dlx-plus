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
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;

$users_dlx_plus_actual = $methods[ $method ] ?? array(
	'label' => '',
	'help'  => '',
);
?>
<div class="users-dlx-plus users-dlx-plus-login users-dlx-plus-login--2fa">
	<h2 class="users-dlx-plus-login__title"><?php esc_html_e( 'One more step', 'users-dlx-plus' ); ?></h2>
	<p><?php echo esc_html( (string) $users_dlx_plus_actual['help'] ); ?></p>

	<?php if ( 'code' === $state ) : ?>
		<p class="users-dlx-plus-notice users-dlx-plus-notice--error"><?php esc_html_e( 'That code is not right, or it expired. Try the next one.', 'users-dlx-plus' ); ?></p>
	<?php elseif ( 'sent' === $state ) : ?>
		<p class="users-dlx-plus-notice users-dlx-plus-notice--ok"><?php esc_html_e( 'Sent. Check your email.', 'users-dlx-plus' ); ?></p>
	<?php endif; ?>

	<form class="users-dlx-plus-form" method="post" action="">
		<input type="hidden" name="users_dlx_plus_2fa_user" value="<?php echo esc_attr( (string) $user_id ); ?>">
		<input type="hidden" name="users_dlx_plus_2fa_key" value="<?php echo esc_attr( $key ); ?>">
		<input type="hidden" name="users_dlx_plus_2fa_method" value="<?php echo esc_attr( $method ); ?>">

		<label for="users-dlx-plus-2fa-code"><?php esc_html_e( 'The code', 'users-dlx-plus' ); ?></label>
		<input type="text" id="users-dlx-plus-2fa-code" name="users_dlx_plus_2fa_code" inputmode="numeric" autocomplete="one-time-code"
			maxlength="20" autofocus required>

		<?php if ( (int) users_dlx_plus_option( 'users_dlx_plus_2fa_remember_days' ) > 0 ) : ?>
			<label class="users-dlx-plus-check">
				<input type="checkbox" name="users_dlx_plus_2fa_trust" value="1">
				<span>
					<?php
					echo esc_html(
						sprintf(
						/* translators: %d: días */
							__( 'Do not ask again on this browser for %d days', 'users-dlx-plus' ),
							(int) users_dlx_plus_option( 'users_dlx_plus_2fa_remember_days' )
						)
					);
					?>
				</span>
			</label>
		<?php endif; ?>

		<button type="submit" class="users-dlx-plus-button"><?php esc_html_e( 'Confirm', 'users-dlx-plus' ); ?></button>

		<?php if ( isset( $methods[ $method ]['send'] ) ) : ?>
			<button type="submit" name="users_dlx_plus_2fa_resend" value="1" class="users-dlx-plus-button users-dlx-plus-button--soft"><?php esc_html_e( 'Send it again', 'users-dlx-plus' ); ?></button>
		<?php endif; ?>
	</form>

	<?php if ( count( $methods ) > 1 ) : ?>
		<p class="users-dlx-plus-note">
			<?php esc_html_e( 'Or use:', 'users-dlx-plus' ); ?>
			<?php foreach ( $methods as $users_dlx_plus_id => $users_dlx_plus_m ) : ?>
				<?php if ( $users_dlx_plus_id !== $method ) : ?>
					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'users_dlx_plus_2fa'    => $user_id,
								'users_dlx_plus_key'    => $key,
								'users_dlx_plus_method' => $users_dlx_plus_id,
							),
							users_dlx_plus_login_url()
						)
					);
					?>
								"><?php echo esc_html( $users_dlx_plus_m['label'] ); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>
		</p>
	<?php endif; ?>

	<p class="users-dlx-plus-note"><?php esc_html_e( 'Lost the phone and the email? Use one of your backup codes: they go in the same box.', 'users-dlx-plus' ); ?></p>
</div>
