<?php
/**
 * Las sesiones abiertas de la persona.
 *
 * Reemplazable desde el tema en:
 *   wp-content/themes/<tu-tema>/users-dlx-plus/sesiones.php
 *
 * @var array<int, array<string, mixed>> $sessions
 * @var bool                             $can_close_one Si se puede cerrar una suelta.
 * @var string                           $state
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="users-dlx-plus users-dlx-plus-sessions">

	<?php if ( 'sessions' === $state ) : ?>
		<p class="users-dlx-plus-notice users-dlx-plus-notice--ok"><?php esc_html_e( 'Done.', 'users-dlx-plus' ); ?></p>
	<?php endif; ?>

	<?php if ( array() === $sessions ) : ?>
		<p class="users-dlx-plus-note"><?php esc_html_e( 'There are no open sessions.', 'users-dlx-plus' ); ?></p>
	<?php else : ?>

	<ul class="users-dlx-plus-sessions__list">
		<?php foreach ( $sessions as $users_dlx_plus_session ) : ?>
			<li class="users-dlx-plus-session<?php echo $users_dlx_plus_session['current'] ? ' users-dlx-plus-session--current' : ''; ?>">
				<div class="users-dlx-plus-session__what">
					<strong>
						<?php echo esc_html( $users_dlx_plus_session['browser'] ); ?>
						<?php if ( '' !== $users_dlx_plus_session['os'] ) : ?>
							· <?php echo esc_html( $users_dlx_plus_session['os'] ); ?>
						<?php endif; ?>
					</strong>
					<span>
						<?php echo esc_html( $users_dlx_plus_session['device'] ); ?>
						<?php if ( '' !== $users_dlx_plus_session['ip'] ) : ?>
							· <?php echo esc_html( $users_dlx_plus_session['ip'] ); ?>
						<?php endif; ?>
						<?php if ( $users_dlx_plus_session['started'] ) : ?>
							· 
							<?php
							printf(
								/* translators: %s: hace cuánto empezó la sesión */
								esc_html__( 'started %s ago', 'users-dlx-plus' ),
								esc_html( human_time_diff( $users_dlx_plus_session['started'] ) )
							);
							?>
						<?php endif; ?>
					</span>
				</div>

				<div class="users-dlx-plus-session__action">
					<?php if ( $users_dlx_plus_session['current'] ) : ?>
						<span class="users-dlx-plus-chip"><?php esc_html_e( 'This session', 'users-dlx-plus' ); ?></span>
					<?php elseif ( $can_close_one ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="users_dlx_plus_sessions">
							<input type="hidden" name="users_dlx_plus_session" value="<?php echo esc_attr( $users_dlx_plus_session['id'] ); ?>">
							<?php wp_nonce_field( 'users_dlx_plus_sessions' ); ?>
							<button type="submit" class="users-dlx-plus-button users-dlx-plus-button--soft"><?php esc_html_e( 'Close', 'users-dlx-plus' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php endif; ?>

	<?php if ( count( $sessions ) > 1 ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="users-dlx-plus-sessions__all">
			<input type="hidden" name="action" value="users_dlx_plus_sessions">
			<?php wp_nonce_field( 'users_dlx_plus_sessions' ); ?>
			<button type="submit" class="users-dlx-plus-button users-dlx-plus-button--soft"><?php esc_html_e( 'Close the others', 'users-dlx-plus' ); ?></button>
		</form>
	<?php endif; ?>
</div>
