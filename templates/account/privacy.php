<?php
/**
 * Privacidad: bajar tus datos o pedir que se borre la cuenta.
 *
 * @var bool                $can_erase
 * @var array<int, WP_Post> $erasures
 * @var array<int, WP_Post> $exports
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige el mensaje.
$users_plus_aviso   = isset( $_GET['users-plus'] ) ? sanitize_key( wp_unslash( $_GET['users-plus'] ) ) : '';
$users_plus_estados = users_plus_data_states();
$users_plus_correo  = users_plus_data_mail_ready();

/** Pinta una tabla de solicitudes. */
$users_plus_tabla = static function ( array $pedidos ) use ( $users_plus_estados ): void {
	if ( array() === $pedidos ) {
		return;
	}
	?>
	<table class="users-plus-tabla users-plus-pedidos">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Asked on', 'users-plus' ); ?></th>
				<th><?php esc_html_e( 'Status', 'users-plus' ); ?></th>
				<th class="users-plus-pedidos__accion"></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $pedidos as $users_plus_pedido ) :
				[ $users_plus_tono, $users_plus_texto ] = $users_plus_estados[ $users_plus_pedido->post_status ] ?? array( 'off', $users_plus_pedido->post_status );
				$users_plus_archivo                     = users_plus_data_file( $users_plus_pedido );
				?>
				<tr>
					<td><?php echo esc_html( (string) wp_date( 'j M Y, H:i', (int) get_post_timestamp( $users_plus_pedido ) ) ); ?></td>
					<td><span class="users-plus-pill users-plus-pill--<?php echo esc_attr( $users_plus_tono ); ?>"><?php echo esc_html( $users_plus_texto ); ?></span></td>
					<td class="users-plus-pedidos__accion">
						<?php if ( '' !== $users_plus_archivo ) : ?>
							<a class="users-plus-button" href="<?php echo esc_url( $users_plus_archivo ); ?>" download><?php esc_html_e( 'Download', 'users-plus' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
};
?>
<p><?php esc_html_e( 'Everything this site knows about you is yours: you can take it with you, and you can ask us to erase it.', 'users-plus' ); ?></p>

<?php if ( 'requested' === $users_plus_aviso ) : ?>
	<p class="users-plus-notice users-plus-notice--ok"><?php esc_html_e( 'We sent you an email to confirm it. Nothing happens until you click that link.', 'users-plus' ); ?></p>
<?php elseif ( 'admin' === $users_plus_aviso ) : ?>
	<p class="users-plus-notice users-plus-notice--error"><?php esc_html_e( 'An account with admin permissions cannot ask for its own deletion.', 'users-plus' ); ?></p>
<?php elseif ( 'error' === $users_plus_aviso ) : ?>
	<p class="users-plus-notice users-plus-notice--error"><?php esc_html_e( 'We could not create the request. There may already be one waiting.', 'users-plus' ); ?></p>
<?php endif; ?>

<?php if ( ! $users_plus_correo && current_user_can( 'manage_options' ) ) : ?>
	<p class="users-plus-notice users-plus-notice--error">
		<?php esc_html_e( 'Heads up, this only shows to administrators: the site has no outgoing mail set up, so the confirmation email never arrives and every request stays waiting forever.', 'users-plus' ); ?>
	</p>
<?php endif; ?>

<?php if ( users_plus_option( 'users_plus_privacy_export' ) ) : ?>
	<?php users_plus_panel_open( __( 'Download your data', 'users-plus' ), true ); ?>
	<p><?php esc_html_e( 'A file with everything: your details, your courses, what you wrote in the forums and in the comments. You get an email to confirm; once you do, we prepare it and it shows up here to download.', 'users-plus' ); ?></p>

	<?php $users_plus_tabla( $exports ); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="users_plus_data_request">
		<input type="hidden" name="users_plus_request" value="export">
		<?php wp_nonce_field( 'users_plus_data_request' ); ?>
		<button type="submit" class="users-plus-button">
			<?php
			echo array() === $exports
				? esc_html__( 'Ask for my data', 'users-plus' )
				: esc_html__( 'Ask for it again, up to date', 'users-plus' );
			?>
		</button>
	</form>
	<?php users_plus_panel_close(); ?>
<?php endif; ?>

<?php if ( users_plus_option( 'users_plus_privacy_delete' ) ) : ?>
	<?php
	/*
	 * La primera caja se abre. Si la de arriba no está, ésta pasa a ser la
	 * primera y la que se abre es ésta.
	 */
	?>
	<?php users_plus_panel_open( __( 'Delete your account', 'users-plus' ), ! users_plus_option( 'users_plus_privacy_export' ), 'users-plus-panel--peligro' ); ?>

	<?php if ( ! $can_erase ) : ?>
		<p class="users-plus-notice users-plus-notice--info">
			<?php esc_html_e( 'This account administers the site, so it cannot delete itself: the site would be left with nobody in charge. Another administrator has to lower its role first, and then it can ask.', 'users-plus' ); ?>
		</p>
	<?php else : ?>
		<p><strong><?php esc_html_e( 'This cannot be undone.', 'users-plus' ); ?></strong></p>
		<ul class="users-plus-lista">
			<li><?php esc_html_e( 'Your details, your progress and your certificates are erased.', 'users-plus' ); ?></li>
			<li><?php esc_html_e( 'What you wrote in public stays, with no name on it.', 'users-plus' ); ?></li>
			<li><?php esc_html_e( 'You stop being able to sign in, and nothing can be recovered afterwards — not by you and not by us.', 'users-plus' ); ?></li>
		</ul>
		<p><?php esc_html_e( 'If you want a copy of anything, download your data first.', 'users-plus' ); ?></p>
		<p class="users-plus-note"><?php esc_html_e( 'Asking is not deleting: we send you an email and nothing happens until you click the link in it. That is what stops somebody who borrowed your screen for a minute.', 'users-plus' ); ?></p>

		<?php $users_plus_tabla( $erasures ); ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			onsubmit="return confirm( '<?php echo esc_js( __( 'Ask to delete your account? You still have to confirm it by email, and after that there is no going back.', 'users-plus' ) ); ?>' );">
			<input type="hidden" name="action" value="users_plus_data_request">
			<input type="hidden" name="users_plus_request" value="erase">
			<?php wp_nonce_field( 'users_plus_data_request' ); ?>
			<button type="submit" class="users-plus-button users-plus-button--peligro"><?php esc_html_e( 'Ask to delete my account', 'users-plus' ); ?></button>
		</form>
	<?php endif; ?>
	<?php users_plus_panel_close(); ?>
<?php endif; ?>
