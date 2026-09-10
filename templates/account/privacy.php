<?php
/**
 * Privacidad: bajar tus datos o pedir que se borre la cuenta.
 *
 * @var bool                $can_erase
 * @var array<int, WP_Post> $erasures
 * @var array<int, WP_Post> $exports
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige el mensaje.
$users_dlx_plus_aviso   = isset( $_GET['users-dlx-plus'] ) ? sanitize_key( wp_unslash( $_GET['users-dlx-plus'] ) ) : '';
$users_dlx_plus_estados = users_dlx_plus_data_states();
$users_dlx_plus_correo  = users_dlx_plus_data_mail_ready();

/** Pinta una tabla de solicitudes. */
$users_dlx_plus_tabla = static function ( array $pedidos ) use ( $users_dlx_plus_estados ): void {
	if ( array() === $pedidos ) {
		return;
	}
	?>
	<table class="users-dlx-plus-tabla users-dlx-plus-pedidos">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Asked on', 'users-dlx-plus' ); ?></th>
				<th><?php esc_html_e( 'Status', 'users-dlx-plus' ); ?></th>
				<th class="users-dlx-plus-pedidos__accion"></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $pedidos as $users_dlx_plus_pedido ) :
				[ $users_dlx_plus_tono, $users_dlx_plus_texto ] = $users_dlx_plus_estados[ $users_dlx_plus_pedido->post_status ] ?? array( 'off', $users_dlx_plus_pedido->post_status );
				$users_dlx_plus_archivo                         = users_dlx_plus_data_file( $users_dlx_plus_pedido );
				?>
				<tr>
					<td><?php echo esc_html( (string) wp_date( 'j M Y, H:i', (int) get_post_timestamp( $users_dlx_plus_pedido ) ) ); ?></td>
					<td><span class="users-dlx-plus-pill users-dlx-plus-pill--<?php echo esc_attr( $users_dlx_plus_tono ); ?>"><?php echo esc_html( $users_dlx_plus_texto ); ?></span></td>
					<td class="users-dlx-plus-pedidos__accion">
						<?php if ( '' !== $users_dlx_plus_archivo ) : ?>
							<a class="users-dlx-plus-button" href="<?php echo esc_url( $users_dlx_plus_archivo ); ?>" download><?php esc_html_e( 'Download', 'users-dlx-plus' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
};
?>
<p><?php esc_html_e( 'Everything this site knows about you is yours: you can take it with you, and you can ask us to erase it.', 'users-dlx-plus' ); ?></p>

<?php if ( 'requested' === $users_dlx_plus_aviso ) : ?>
	<p class="users-dlx-plus-notice users-dlx-plus-notice--ok"><?php esc_html_e( 'We sent you an email to confirm it. Nothing happens until you click that link.', 'users-dlx-plus' ); ?></p>
<?php elseif ( 'admin' === $users_dlx_plus_aviso ) : ?>
	<p class="users-dlx-plus-notice users-dlx-plus-notice--error"><?php esc_html_e( 'An account with admin permissions cannot ask for its own deletion.', 'users-dlx-plus' ); ?></p>
<?php elseif ( 'error' === $users_dlx_plus_aviso ) : ?>
	<p class="users-dlx-plus-notice users-dlx-plus-notice--error"><?php esc_html_e( 'We could not create the request. There may already be one waiting.', 'users-dlx-plus' ); ?></p>
<?php endif; ?>

<?php if ( ! $users_dlx_plus_correo && current_user_can( 'manage_options' ) ) : ?>
	<p class="users-dlx-plus-notice users-dlx-plus-notice--error">
		<?php esc_html_e( 'Heads up, this only shows to administrators: the site has no outgoing mail set up, so the confirmation email never arrives and every request stays waiting forever.', 'users-dlx-plus' ); ?>
	</p>
<?php endif; ?>

<?php if ( users_dlx_plus_option( 'users_dlx_plus_privacy_export' ) ) : ?>
	<?php users_dlx_plus_panel_open( __( 'Download your data', 'users-dlx-plus' ), true ); ?>
	<p><?php esc_html_e( 'A file with everything: your details, your courses, what you wrote in the forums and in the comments. You get an email to confirm; once you do, we prepare it and it shows up here to download.', 'users-dlx-plus' ); ?></p>

	<?php $users_dlx_plus_tabla( $exports ); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="users_dlx_plus_data_request">
		<input type="hidden" name="users_dlx_plus_request" value="export">
		<?php wp_nonce_field( 'users_dlx_plus_data_request' ); ?>
		<button type="submit" class="users-dlx-plus-button">
			<?php
			echo array() === $exports
				? esc_html__( 'Ask for my data', 'users-dlx-plus' )
				: esc_html__( 'Ask for it again, up to date', 'users-dlx-plus' );
			?>
		</button>
	</form>
	<?php users_dlx_plus_panel_close(); ?>
<?php endif; ?>

<?php if ( users_dlx_plus_option( 'users_dlx_plus_privacy_delete' ) ) : ?>
	<?php
	/*
	 * La primera caja se abre. Si la de arriba no está, ésta pasa a ser la
	 * primera y la que se abre es ésta.
	 */
	?>
	<?php users_dlx_plus_panel_open( __( 'Delete your account', 'users-dlx-plus' ), ! users_dlx_plus_option( 'users_dlx_plus_privacy_export' ), 'users-dlx-plus-panel--peligro' ); ?>

	<?php if ( ! $can_erase ) : ?>
		<p class="users-dlx-plus-notice users-dlx-plus-notice--info">
			<?php esc_html_e( 'This account administers the site, so it cannot delete itself: the site would be left with nobody in charge. Another administrator has to lower its role first, and then it can ask.', 'users-dlx-plus' ); ?>
		</p>
	<?php else : ?>
		<p><strong><?php esc_html_e( 'This cannot be undone.', 'users-dlx-plus' ); ?></strong></p>
		<ul class="users-dlx-plus-lista">
			<li><?php esc_html_e( 'Your details, your progress and your certificates are erased.', 'users-dlx-plus' ); ?></li>
			<li><?php esc_html_e( 'What you wrote in public stays, with no name on it.', 'users-dlx-plus' ); ?></li>
			<li><?php esc_html_e( 'You stop being able to sign in, and nothing can be recovered afterwards — not by you and not by us.', 'users-dlx-plus' ); ?></li>
		</ul>
		<p><?php esc_html_e( 'If you want a copy of anything, download your data first.', 'users-dlx-plus' ); ?></p>
		<p class="users-dlx-plus-note"><?php esc_html_e( 'Asking is not deleting: we send you an email and nothing happens until you click the link in it. That is what stops somebody who borrowed your screen for a minute.', 'users-dlx-plus' ); ?></p>

		<?php $users_dlx_plus_tabla( $erasures ); ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			onsubmit="return confirm( '<?php echo esc_js( __( 'Ask to delete your account? You still have to confirm it by email, and after that there is no going back.', 'users-dlx-plus' ) ); ?>' );">
			<input type="hidden" name="action" value="users_dlx_plus_data_request">
			<input type="hidden" name="users_dlx_plus_request" value="erase">
			<?php wp_nonce_field( 'users_dlx_plus_data_request' ); ?>
			<button type="submit" class="users-dlx-plus-button users-dlx-plus-button--peligro"><?php esc_html_e( 'Ask to delete my account', 'users-dlx-plus' ); ?></button>
		</form>
	<?php endif; ?>
	<?php users_dlx_plus_panel_close(); ?>
<?php endif; ?>
