<?php
/**
 * Seguridad: cómo entra, las passkeys, el segundo factor y dónde tiene la
 * sesión abierta.
 *
 * El orden no es casual. Primero cómo entra hoy; después las passkeys, que son
 * la forma más segura y la que no pide ningún paso extra; después el segundo
 * factor, que es lo que se le suma a las formas de entrar que sí lo necesitan;
 * y al final la aplicación autenticadora, que es un detalle de ese segundo
 * factor y no tiene sentido antes de haberlo prendido.
 *
 * @var WP_User $user
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

$upfw_id      = (int) $user->ID;
$upfw_ofrece  = upfw_2fa_offered( $upfw_id );
$upfw_on      = upfw_2fa_on( $upfw_id );
$upfw_listos  = upfw_2fa_available( $upfw_id );
$upfw_frescos = upfw_backup_fresh( $upfw_id );
$upfw_piden   = upfw_2fa_ways_asked( $upfw_id );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige el mensaje.
$upfw_aviso = isset( $_GET['upfw'] ) ? sanitize_key( wp_unslash( $_GET['upfw'] ) ) : '';

$upfw_avisos = array(
	'on'          => array( 'ok', __( 'Two-step verification is on.', 'users-plus-for-wordpress' ) ),
	'off'         => array( 'ok', __( 'Two-step verification is off.', 'users-plus-for-wordpress' ) ),
	'totp'        => array( 'ok', __( 'Your authenticator app is set up.', 'users-plus-for-wordpress' ) ),
	'totpoff'     => array( 'ok', __( 'The authenticator app was removed.', 'users-plus-for-wordpress' ) ),
	'backup'      => array( 'ok', __( 'New backup codes. The old ones no longer work.', 'users-plus-for-wordpress' ) ),
	'badcode'     => array( 'error', __( 'That code is not right. Check the app and try again — they change every thirty seconds.', 'users-plus-for-wordpress' ) ),
	'nomethod'    => array( 'error', __( 'First set up a way to receive the second step.', 'users-plus-for-wordpress' ) ),
	'required'    => array( 'error', __( 'This site requires two-step verification: it cannot be turned off.', 'users-plus-for-wordpress' ) ),
	'passkeyoff'  => array( 'ok', __( 'The passkey was removed.', 'users-plus-for-wordpress' ) ),
	'passkeyname' => array( 'ok', __( 'The passkey has a new name.', 'users-plus-for-wordpress' ) ),
);

if ( upfw_passkeys_enabled() ) {
	upfw_passkeys_enqueue();
}
?>

<?php if ( isset( $upfw_avisos[ $upfw_aviso ] ) ) : ?>
	<p class="upfw-notice upfw-notice--<?php echo esc_attr( $upfw_avisos[ $upfw_aviso ][0] ); ?>"><?php echo esc_html( $upfw_avisos[ $upfw_aviso ][1] ); ?></p>
<?php endif; ?>

<?php upfw_panel_open( __( 'How you get in', 'users-plus-for-wordpress' ), true ); ?>
	<dl class="upfw-datos">
		<dt><?php esc_html_e( 'Email', 'users-plus-for-wordpress' ); ?></dt>
		<dd><?php echo esc_html( $user->user_email ); ?></dd>
		<dt><?php esc_html_e( 'Password', 'users-plus-for-wordpress' ); ?></dt>
		<dd>
			<?php
			echo upfw_login_has_password()
				? esc_html__( 'The one on your WordPress account.', 'users-plus-for-wordpress' )
				: esc_html__( 'You do not have one. You get in with a link sent to your email, or with a social account.', 'users-plus-for-wordpress' );
			?>
		</dd>
	</dl>
<?php upfw_panel_close(); ?>

<?php
/*
 * Las passkeys van antes que el segundo factor porque son la mejor
 * respuesta al mismo problema, no un accesorio de la respuesta anterior:
 * quien pueda usarlas no necesita nada de lo que viene abajo.
 */
?>
<?php if ( upfw_passkeys_enabled() ) : ?>
	<?php upfw_panel_open( __( 'Passkeys', 'users-plus-for-wordpress' ) ); ?>
		<p><?php esc_html_e( 'The way in with no password and nothing to type: the fingerprint, the face or the PIN of your own device. The key never leaves it, there is nothing on our side worth stealing, and it cannot be used on a fake site pretending to be this one.', 'users-plus-for-wordpress' ); ?></p>

		<p class="upfw-note"><?php esc_html_e( 'When you get in with a passkey we do not ask for a second step: the passkey already is two of them in one — the device you have, and the fingerprint, face or PIN that unlocks it.', 'users-plus-for-wordpress' ); ?></p>

		<p class="upfw-notice" data-upfw-passkey-aviso hidden></p>

		<?php $upfw_llaves = upfw_passkeys( $upfw_id ); ?>

		<?php if ( array() !== $upfw_llaves ) : ?>
			<ul class="upfw-llaves">
				<?php foreach ( $upfw_llaves as $upfw_n => $upfw_llave ) : ?>
					<li>
						<?php
						/*
						 * El nombre se lee, no se edita: una fila con un
						 * campo de texto siempre abierto parece un
						 * formulario a medio llenar. Se abre cuando se
						 * lo pide, y ahí adentro vive también el quitar,
						 * que es lo que no conviene tener a un clic.
						 */
						?>
						<details class="upfw-llave">
							<summary class="upfw-llave__fila">
								<span class="upfw-llave__logo" aria-hidden="true">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2a8 8 0 0 0-7.7 10.2L2 16.5V22h5.5v-2.5H10V17h2.5l1.3-1.3A8 8 0 1 0 14 2m3 6.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3"/></svg>
								</span>

								<span class="upfw-llave__quien">
									<strong><?php echo esc_html( (string) $upfw_llave['label'] ); ?></strong>
									<span>
										<?php
										echo esc_html(
											sprintf(
											/* translators: %s: fecha de alta */
												__( 'Added on %s', 'users-plus-for-wordpress' ),
												wp_date( 'j M Y', (int) $upfw_llave['created'] )
											)
										);

										if ( (int) $upfw_llave['used'] > 0 ) {
											echo ' · ' . esc_html(
												sprintf(
												/* translators: %s: hace cuánto se usó */
													__( 'used %s ago', 'users-plus-for-wordpress' ),
													human_time_diff( (int) $upfw_llave['used'] )
												)
											);
										}
										?>
									</span>
								</span>

								<span class="upfw-llave__flecha" aria-hidden="true"></span>
							</summary>

							<form class="upfw-llave__editar" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="upfw_passkey">
								<input type="hidden" name="upfw_passkey" value="<?php echo esc_attr( (string) $upfw_llave['id'] ); ?>">
								<?php wp_nonce_field( 'upfw_passkey' ); ?>

								<label for="upfw-llave-<?php echo esc_attr( (string) $upfw_n ); ?>"><?php esc_html_e( 'Name of this passkey', 'users-plus-for-wordpress' ); ?></label>
								<input type="text" id="upfw-llave-<?php echo esc_attr( (string) $upfw_n ); ?>" name="upfw_passkey_label" value="<?php echo esc_attr( (string) $upfw_llave['label'] ); ?>" maxlength="60">

								<span class="upfw-llave__acciones">
									<button type="submit" name="upfw_passkey_do" value="rename" class="upfw-button"><?php esc_html_e( 'Save name', 'users-plus-for-wordpress' ); ?></button>
									<button type="submit" name="upfw_passkey_do" value="delete" class="upfw-button upfw-button--soft"><?php esc_html_e( 'Remove this passkey', 'users-plus-for-wordpress' ); ?></button>
								</span>
							</form>
						</details>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php
		/*
		 * El nombre se pide al dar de alta y no después: con dos o tres
		 * llaves, «Passkey, Passkey, Passkey» no le dice a nadie cuál
		 * sacar cuando pierde el teléfono.
		 */
		?>
		<p class="upfw-llave-alta">
			<span class="upfw-llave__campo">
				<label for="upfw-llave-nueva"><?php esc_html_e( 'Name it, so you recognise it later', 'users-plus-for-wordpress' ); ?></label>
				<input type="text" id="upfw-llave-nueva" data-upfw-passkey-label maxlength="60" placeholder="<?php echo esc_attr( upfw_passkey_label() ); ?>">
			</span>
			<button type="button" class="upfw-button" data-upfw-passkey="register"><?php esc_html_e( 'Add a passkey', 'users-plus-for-wordpress' ); ?></button>
		</p>
	<?php upfw_panel_close(); ?>
<?php endif; ?>

<?php if ( $upfw_ofrece ) : ?>

	<?php if ( array() !== $upfw_frescos ) : ?>
		<?php upfw_panel_open( __( 'Write these down now', 'users-plus-for-wordpress' ), true, 'upfw-respaldo' ); ?>
			<p><?php esc_html_e( 'Each one gets you in once, if you lose the phone or the email. They are shown only this time: we keep them scrambled, so nobody —including us— can read them back.', 'users-plus-for-wordpress' ); ?></p>
			<ul class="upfw-respaldo__lista">
				<?php foreach ( $upfw_frescos as $upfw_code ) : ?>
					<li><code><?php echo esc_html( $upfw_code ); ?></code></li>
				<?php endforeach; ?>
			</ul>
		<?php upfw_panel_close(); ?>
	<?php endif; ?>

	<?php upfw_panel_open( __( 'Two-step verification', 'users-plus-for-wordpress' ) ); ?>
		<p><?php esc_html_e( 'One more thing when you sign in: a code that only you have. If somebody gets hold of your email, they still do not get in.', 'users-plus-for-wordpress' ); ?></p>

		<?php
		// Lo que se dice acá tiene que ser lo que va a pasar, y eso cambia
		// según por dónde entre cada quien: la excepción del enlace por correo
		// es sólo del enlace por correo. Con una red social prendida, el
		// segundo paso sí se pide, y decir lo contrario es peor que callarse.
		if ( $upfw_on && ! upfw_2fa_worth_it_on_link( $upfw_id ) && upfw_login_has_link() ) :
			?>
			<p class="upfw-notice upfw-notice--info">
				<?php if ( array() === $upfw_piden ) : ?>
					<?php esc_html_e( 'Right now it is not being asked anywhere: the only way in you have is the link we email you, and the second step would be another code to that same inbox — it would not prove anything new.', 'users-plus-for-wordpress' ); ?>
				<?php else : ?>
					<?php
					echo esc_html(
						sprintf(
						/* translators: %s: lista de formas de entrar, ya separadas por comas */
							__( 'It is not asked when you get in with the link we email you: that link already proves you have the inbox, and the code would go to the same place. It is asked when you get in with %s.', 'users-plus-for-wordpress' ),
							wp_sprintf( '%l', $upfw_piden )
						)
					);
					?>
				<?php endif; ?>

				<?php if ( ! upfw_totp_ready( $upfw_id ) ) : ?>
					<?php esc_html_e( 'Set up the authenticator app below and it starts being asked with the link too.', 'users-plus-for-wordpress' ); ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<p>
			<span class="upfw-chip">
			<?php
				echo $upfw_on
					? esc_html__( 'On', 'users-plus-for-wordpress' )
					: esc_html__( 'Off', 'users-plus-for-wordpress' );
			?>
			</span>
			<?php if ( 'required' === (string) upfw_option( 'upfw_2fa_mode' ) ) : ?>
				<span class="upfw-note"><?php esc_html_e( 'This site requires it.', 'users-plus-for-wordpress' ); ?></span>
			<?php endif; ?>
		</p>

		<?php if ( array() !== $upfw_listos ) : ?>
			<p class="upfw-note">
				<?php
				echo esc_html(
					sprintf(
					/* translators: %s: lista de métodos disponibles */
						__( 'Ready to use: %s.', 'users-plus-for-wordpress' ),
						implode( ', ', wp_list_pluck( $upfw_listos, 'label' ) )
					)
				);
				?>
			</p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="upfw_security">
			<?php wp_nonce_field( 'upfw_security' ); ?>

			<?php if ( ! $upfw_on ) : ?>
				<button type="submit" name="upfw_security" value="on" class="upfw-button"><?php esc_html_e( 'Turn it on', 'users-plus-for-wordpress' ); ?></button>
			<?php elseif ( upfw_2fa_can_turn_off( $upfw_id ) ) : ?>
				<button type="submit" name="upfw_security" value="off" class="upfw-button upfw-button--soft"><?php esc_html_e( 'Turn it off', 'users-plus-for-wordpress' ); ?></button>
			<?php endif; ?>

			<?php if ( $upfw_on ) : ?>
				<button type="submit" name="upfw_security" value="backup" class="upfw-button upfw-button--soft">
					<?php
					echo esc_html(
						sprintf(
						/* translators: %d: códigos que le quedan */
							__( 'New backup codes (%d left)', 'users-plus-for-wordpress' ),
							upfw_backup_left( $upfw_id )
						)
					);
					?>
				</button>
			<?php endif; ?>
		</form>
	<?php upfw_panel_close(); ?>

	<?php if ( isset( upfw_2fa_methods()['totp'] ) ) : ?>
		<?php upfw_panel_open( __( 'Authenticator app', 'users-plus-for-wordpress' ) ); ?>

			<?php if ( upfw_totp_ready( $upfw_id ) ) : ?>
				<p><?php esc_html_e( 'Set up. When you sign in, we ask for the six-digit code from the app.', 'users-plus-for-wordpress' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="upfw_security">
					<?php wp_nonce_field( 'upfw_security' ); ?>
					<button type="submit" name="upfw_security" value="totp_off" class="upfw-button upfw-button--soft"><?php esc_html_e( 'Remove it', 'users-plus-for-wordpress' ); ?></button>
				</form>
				<?php
			else :
				$upfw_secret = upfw_totp_pending( $upfw_id );
				$upfw_uri    = upfw_totp_uri( $upfw_id, $upfw_secret );
				?>
				<p><?php esc_html_e( 'Scan this with Google Authenticator, 1Password, Aegis or whichever app you use, and then write down the code it shows to confirm it.', 'users-plus-for-wordpress' ); ?></p>

				<div class="upfw-totp">
					<div class="upfw-totp__qr"><?php echo upfw_qr_svg( $upfw_uri, 190 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG propio. ?></div>

					<div class="upfw-totp__manual">
						<p class="upfw-note"><?php esc_html_e( 'Cannot scan it? Type this key into the app:', 'users-plus-for-wordpress' ); ?></p>
						<p><code class="upfw-totp__clave"><?php echo esc_html( upfw_totp_readable( $upfw_secret ) ); ?></code></p>

						<form class="upfw-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="upfw_security">
							<input type="hidden" name="upfw_security" value="totp">
							<?php wp_nonce_field( 'upfw_security' ); ?>

							<label for="upfw-totp-code"><?php esc_html_e( 'The code from the app', 'users-plus-for-wordpress' ); ?></label>
							<input type="text" id="upfw-totp-code" name="upfw_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" required>

							<button type="submit" class="upfw-button"><?php esc_html_e( 'Confirm', 'users-plus-for-wordpress' ); ?></button>
						</form>
					</div>
				</div>
			<?php endif; ?>
		<?php upfw_panel_close(); ?>
	<?php endif; ?>

<?php endif; ?>

<?php
/*
 * Va en un panel como todo lo demás de esta pantalla: un título suelto
 * hereda el tamaño de h3 del tema y queda de otro cuerpo que los de
 * arriba. Acá todos los bloques son la misma caja.
 */
?>
<?php if ( upfw_option( 'upfw_sessions_show' ) ) : ?>
	<?php upfw_panel_open( __( 'Where you are signed in', 'users-plus-for-wordpress' ) ); ?>
		<?php echo do_shortcode( '[upfw_sessions]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode propio. ?>
	<?php upfw_panel_close(); ?>
<?php endif; ?>
