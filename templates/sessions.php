<?php
/**
 * Las sesiones abiertas de la persona.
 *
 * Reemplazable desde el tema en:
 *   wp-content/themes/<tu-tema>/users-plus-for-wordpress/sesiones.php
 *
 * @var array<int, array<string, mixed>> $sessions
 * @var bool                             $can_close_one Si se puede cerrar una suelta.
 * @var string                           $state
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="upfw upfw-sessions">

	<?php if ( 'sessions' === $state ) : ?>
		<p class="upfw-notice upfw-notice--ok"><?php esc_html_e( 'Done.', 'users-plus-for-wordpress' ); ?></p>
	<?php endif; ?>

	<?php if ( array() === $sessions ) : ?>
		<p class="upfw-note"><?php esc_html_e( 'There are no open sessions.', 'users-plus-for-wordpress' ); ?></p>
		<?php return; ?>
	<?php endif; ?>

	<ul class="upfw-sessions__list">
		<?php foreach ( $sessions as $session ) : ?>
			<li class="upfw-session<?php echo $session['current'] ? ' upfw-session--current' : ''; ?>">
				<div class="upfw-session__what">
					<strong>
						<?php echo esc_html( $session['browser'] ); ?>
						<?php if ( '' !== $session['os'] ) : ?>
							· <?php echo esc_html( $session['os'] ); ?>
						<?php endif; ?>
					</strong>
					<span>
						<?php echo esc_html( $session['device'] ); ?>
						<?php if ( '' !== $session['ip'] ) : ?>
							· <?php echo esc_html( $session['ip'] ); ?>
						<?php endif; ?>
						<?php if ( $session['started'] ) : ?>
							· 
							<?php
							printf(
								/* translators: %s: hace cuánto empezó la sesión */
								esc_html__( 'started %s ago', 'users-plus-for-wordpress' ),
								esc_html( human_time_diff( $session['started'] ) )
							);
							?>
						<?php endif; ?>
					</span>
				</div>

				<div class="upfw-session__action">
					<?php if ( $session['current'] ) : ?>
						<span class="upfw-chip"><?php esc_html_e( 'This session', 'users-plus-for-wordpress' ); ?></span>
					<?php elseif ( $can_close_one ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="upfw_sessions">
							<input type="hidden" name="upfw_session" value="<?php echo esc_attr( $session['id'] ); ?>">
							<?php wp_nonce_field( 'upfw_sessions' ); ?>
							<button type="submit" class="upfw-button upfw-button--soft"><?php esc_html_e( 'Close', 'users-plus-for-wordpress' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php if ( count( $sessions ) > 1 ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="upfw-sessions__all">
			<input type="hidden" name="action" value="upfw_sessions">
			<?php wp_nonce_field( 'upfw_sessions' ); ?>
			<button type="submit" class="upfw-button upfw-button--soft"><?php esc_html_e( 'Close the others', 'users-plus-for-wordpress' ); ?></button>
		</form>
	<?php endif; ?>
</div>
