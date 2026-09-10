<?php
/**
 * Las sesiones abiertas de la persona.
 *
 * Reemplazable desde el tema en:
 *   wp-content/themes/<tu-tema>/users-plus/sesiones.php
 *
 * @var array<int, array<string, mixed>> $sessions
 * @var bool                             $can_close_one Si se puede cerrar una suelta.
 * @var string                           $state
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="users-plus users-plus-sessions">

	<?php if ( 'sessions' === $state ) : ?>
		<p class="users-plus-notice users-plus-notice--ok"><?php esc_html_e( 'Done.', 'users-plus' ); ?></p>
	<?php endif; ?>

	<?php if ( array() === $sessions ) : ?>
		<p class="users-plus-note"><?php esc_html_e( 'There are no open sessions.', 'users-plus' ); ?></p>
	<?php else : ?>

	<ul class="users-plus-sessions__list">
		<?php foreach ( $sessions as $users_plus_session ) : ?>
			<li class="users-plus-session<?php echo $users_plus_session['current'] ? ' users-plus-session--current' : ''; ?>">
				<div class="users-plus-session__what">
					<strong>
						<?php echo esc_html( $users_plus_session['browser'] ); ?>
						<?php if ( '' !== $users_plus_session['os'] ) : ?>
							· <?php echo esc_html( $users_plus_session['os'] ); ?>
						<?php endif; ?>
					</strong>
					<span>
						<?php echo esc_html( $users_plus_session['device'] ); ?>
						<?php if ( '' !== $users_plus_session['ip'] ) : ?>
							· <?php echo esc_html( $users_plus_session['ip'] ); ?>
						<?php endif; ?>
						<?php if ( $users_plus_session['started'] ) : ?>
							· 
							<?php
							printf(
								/* translators: %s: hace cuánto empezó la sesión */
								esc_html__( 'started %s ago', 'users-plus' ),
								esc_html( human_time_diff( $users_plus_session['started'] ) )
							);
							?>
						<?php endif; ?>
					</span>
				</div>

				<div class="users-plus-session__action">
					<?php if ( $users_plus_session['current'] ) : ?>
						<span class="users-plus-chip"><?php esc_html_e( 'This session', 'users-plus' ); ?></span>
					<?php elseif ( $can_close_one ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="users_plus_sessions">
							<input type="hidden" name="users_plus_session" value="<?php echo esc_attr( $users_plus_session['id'] ); ?>">
							<?php wp_nonce_field( 'users_plus_sessions' ); ?>
							<button type="submit" class="users-plus-button users-plus-button--soft"><?php esc_html_e( 'Close', 'users-plus' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php endif; ?>

	<?php if ( count( $sessions ) > 1 ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="users-plus-sessions__all">
			<input type="hidden" name="action" value="users_plus_sessions">
			<?php wp_nonce_field( 'users_plus_sessions' ); ?>
			<button type="submit" class="users-plus-button users-plus-button--soft"><?php esc_html_e( 'Close the others', 'users-plus' ); ?></button>
		</form>
	<?php endif; ?>
</div>
