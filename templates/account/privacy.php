<?php
/**
 * Privacidad: bajar tus datos o pedir que se borre la cuenta.
 *
 * @var bool                $can_erase
 * @var array<int, WP_Post> $erasures
 * @var array<int, WP_Post> $exports
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige el mensaje.
$upfw_aviso   = isset( $_GET['upfw'] ) ? sanitize_key( wp_unslash( $_GET['upfw'] ) ) : '';
$upfw_estados = upfw_data_states();
$upfw_correo  = upfw_data_mail_ready();

/** Pinta una tabla de solicitudes. */
$upfw_tabla = static function ( array $pedidos ) use ( $upfw_estados ): void {
	if ( array() === $pedidos ) {
		return;
	}
	?>
	<table class="upfw-tabla upfw-pedidos">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Asked on', 'users-plus-for-wordpress' ); ?></th>
				<th><?php esc_html_e( 'Status', 'users-plus-for-wordpress' ); ?></th>
				<th class="upfw-pedidos__accion"></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $pedidos as $upfw_pedido ) :
				[ $upfw_tono, $upfw_texto ] = $upfw_estados[ $upfw_pedido->post_status ] ?? array( 'off', $upfw_pedido->post_status );
				$upfw_archivo               = upfw_data_file( $upfw_pedido );
				?>
				<tr>
					<td><?php echo esc_html( (string) wp_date( 'j M Y, H:i', (int) get_post_timestamp( $upfw_pedido ) ) ); ?></td>
					<td><span class="upfw-pill upfw-pill--<?php echo esc_attr( $upfw_tono ); ?>"><?php echo esc_html( $upfw_texto ); ?></span></td>
					<td class="upfw-pedidos__accion">
						<?php if ( '' !== $upfw_archivo ) : ?>
							<a class="upfw-button" href="<?php echo esc_url( $upfw_archivo ); ?>" download><?php esc_html_e( 'Download', 'users-plus-for-wordpress' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
};
?>
<p><?php esc_html_e( 'Everything this site knows about you is yours: you can take it with you, and you can ask us to erase it.', 'users-plus-for-wordpress' ); ?></p>

<?php if ( 'requested' === $upfw_aviso ) : ?>
	<p class="upfw-notice upfw-notice--ok"><?php esc_html_e( 'We sent you an email to confirm it. Nothing happens until you click that link.', 'users-plus-for-wordpress' ); ?></p>
<?php elseif ( 'admin' === $upfw_aviso ) : ?>
	<p class="upfw-notice upfw-notice--error"><?php esc_html_e( 'An account with admin permissions cannot ask for its own deletion.', 'users-plus-for-wordpress' ); ?></p>
<?php elseif ( 'error' === $upfw_aviso ) : ?>
	<p class="upfw-notice upfw-notice--error"><?php esc_html_e( 'We could not create the request. There may already be one waiting.', 'users-plus-for-wordpress' ); ?></p>
<?php endif; ?>

<?php if ( ! $upfw_correo && current_user_can( 'manage_options' ) ) : ?>
	<p class="upfw-notice upfw-notice--error">
		<?php esc_html_e( 'Heads up, this only shows to administrators: the site has no outgoing mail set up, so the confirmation email never arrives and every request stays waiting forever.', 'users-plus-for-wordpress' ); ?>
	</p>
<?php endif; ?>

<?php if ( upfw_option( 'upfw_privacy_export' ) ) : ?>
	<?php upfw_panel_open( __( 'Download your data', 'users-plus-for-wordpress' ), true ); ?>
	<p><?php esc_html_e( 'A file with everything: your details, your courses, what you wrote in the forums and in the comments. You get an email to confirm; once you do, we prepare it and it shows up here to download.', 'users-plus-for-wordpress' ); ?></p>

	<?php $upfw_tabla( $exports ); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="upfw_data_request">
		<input type="hidden" name="upfw_request" value="export">
		<?php wp_nonce_field( 'upfw_data_request' ); ?>
		<button type="submit" class="upfw-button">
			<?php
			echo array() === $exports
				? esc_html__( 'Ask for my data', 'users-plus-for-wordpress' )
				: esc_html__( 'Ask for it again, up to date', 'users-plus-for-wordpress' );
			?>
		</button>
	</form>
	<?php upfw_panel_close(); ?>
<?php endif; ?>

<?php if ( upfw_option( 'upfw_privacy_delete' ) ) : ?>
	<?php
	/*
	 * La primera caja se abre. Si la de arriba no está, ésta pasa a ser la
	 * primera y la que se abre es ésta.
	 */
	?>
	<?php upfw_panel_open( __( 'Delete your account', 'users-plus-for-wordpress' ), ! upfw_option( 'upfw_privacy_export' ), 'upfw-panel--peligro' ); ?>

	<?php if ( ! $can_erase ) : ?>
		<p class="upfw-notice upfw-notice--info">
			<?php esc_html_e( 'This account administers the site, so it cannot delete itself: the site would be left with nobody in charge. Another administrator has to lower its role first, and then it can ask.', 'users-plus-for-wordpress' ); ?>
		</p>
	<?php else : ?>
		<p><strong><?php esc_html_e( 'This cannot be undone.', 'users-plus-for-wordpress' ); ?></strong></p>
		<ul class="upfw-lista">
			<li><?php esc_html_e( 'Your details, your progress and your certificates are erased.', 'users-plus-for-wordpress' ); ?></li>
			<li><?php esc_html_e( 'What you wrote in public stays, with no name on it.', 'users-plus-for-wordpress' ); ?></li>
			<li><?php esc_html_e( 'You stop being able to sign in, and nothing can be recovered afterwards — not by you and not by us.', 'users-plus-for-wordpress' ); ?></li>
		</ul>
		<p><?php esc_html_e( 'If you want a copy of anything, download your data first.', 'users-plus-for-wordpress' ); ?></p>
		<p class="upfw-note"><?php esc_html_e( 'Asking is not deleting: we send you an email and nothing happens until you click the link in it. That is what stops somebody who borrowed your screen for a minute.', 'users-plus-for-wordpress' ); ?></p>

		<?php $upfw_tabla( $erasures ); ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			onsubmit="return confirm( '<?php echo esc_js( __( 'Ask to delete your account? You still have to confirm it by email, and after that there is no going back.', 'users-plus-for-wordpress' ) ); ?>' );">
			<input type="hidden" name="action" value="upfw_data_request">
			<input type="hidden" name="upfw_request" value="erase">
			<?php wp_nonce_field( 'upfw_data_request' ); ?>
			<button type="submit" class="upfw-button upfw-button--peligro"><?php esc_html_e( 'Ask to delete my account', 'users-plus-for-wordpress' ); ?></button>
		</form>
	<?php endif; ?>
	<?php upfw_panel_close(); ?>
<?php endif; ?>
