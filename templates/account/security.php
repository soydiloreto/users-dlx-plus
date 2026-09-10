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
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;

$users_dlx_plus_id      = (int) $user->ID;
$users_dlx_plus_ofrece  = users_dlx_plus_2fa_offered( $users_dlx_plus_id );
$users_dlx_plus_on      = users_dlx_plus_2fa_on( $users_dlx_plus_id );
$users_dlx_plus_listos  = users_dlx_plus_2fa_available( $users_dlx_plus_id );
$users_dlx_plus_frescos = users_dlx_plus_backup_fresh( $users_dlx_plus_id );
$users_dlx_plus_piden   = users_dlx_plus_2fa_ways_asked( $users_dlx_plus_id );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige el mensaje.
$users_dlx_plus_aviso = isset( $_GET['users-dlx-plus'] ) ? sanitize_key( wp_unslash( $_GET['users-dlx-plus'] ) ) : '';

$users_dlx_plus_avisos = array(
	'on'          => array( 'ok', __( 'Two-step verification is on.', 'users-dlx-plus' ) ),
	'off'         => array( 'ok', __( 'Two-step verification is off.', 'users-dlx-plus' ) ),
	'totp'        => array( 'ok', __( 'Your authenticator app is set up.', 'users-dlx-plus' ) ),
	'totpoff'     => array( 'ok', __( 'The authenticator app was removed.', 'users-dlx-plus' ) ),
	'backup'      => array( 'ok', __( 'New backup codes. The old ones no longer work.', 'users-dlx-plus' ) ),
	'badcode'     => array( 'error', __( 'That code is not right. Check the app and try again — they change every thirty seconds.', 'users-dlx-plus' ) ),
	'nomethod'    => array( 'error', __( 'First set up a way to receive the second step.', 'users-dlx-plus' ) ),
	'required'    => array( 'error', __( 'This site requires two-step verification: it cannot be turned off.', 'users-dlx-plus' ) ),
	'passkeyoff'  => array( 'ok', __( 'The passkey was removed.', 'users-dlx-plus' ) ),
	'passkeyname' => array( 'ok', __( 'The passkey has a new name.', 'users-dlx-plus' ) ),
);

if ( users_dlx_plus_passkeys_enabled() ) {
	users_dlx_plus_passkeys_enqueue();
}
?>

<?php if ( isset( $users_dlx_plus_avisos[ $users_dlx_plus_aviso ] ) ) : ?>
	<p class="users-dlx-plus-notice users-dlx-plus-notice--<?php echo esc_attr( $users_dlx_plus_avisos[ $users_dlx_plus_aviso ][0] ); ?>"><?php echo esc_html( $users_dlx_plus_avisos[ $users_dlx_plus_aviso ][1] ); ?></p>
<?php endif; ?>

<?php users_dlx_plus_panel_open( __( 'How you get in', 'users-dlx-plus' ), true ); ?>
	<dl class="users-dlx-plus-datos">
		<dt><?php esc_html_e( 'Email', 'users-dlx-plus' ); ?></dt>
		<dd><?php echo esc_html( $user->user_email ); ?></dd>
		<dt><?php esc_html_e( 'Password', 'users-dlx-plus' ); ?></dt>
		<dd>
			<?php
			echo users_dlx_plus_login_has_password()
				? esc_html__( 'The one on your WordPress account.', 'users-dlx-plus' )
				: esc_html__( 'You do not have one. You get in with a link sent to your email, or with a social account.', 'users-dlx-plus' );
			?>
		</dd>
	</dl>
<?php users_dlx_plus_panel_close(); ?>

<?php
/*
 * Las passkeys van antes que el segundo factor porque son la mejor
 * respuesta al mismo problema, no un accesorio de la respuesta anterior:
 * quien pueda usarlas no necesita nada de lo que viene abajo.
 */
?>
<?php if ( users_dlx_plus_passkeys_enabled() ) : ?>
	<?php users_dlx_plus_panel_open( __( 'Passkeys', 'users-dlx-plus' ) ); ?>
		<p><?php esc_html_e( 'The way in with no password and nothing to type: the fingerprint, the face or the PIN of your own device. The key never leaves it, there is nothing on our side worth stealing, and it cannot be used on a fake site pretending to be this one.', 'users-dlx-plus' ); ?></p>

		<p class="users-dlx-plus-note"><?php esc_html_e( 'When you get in with a passkey we do not ask for a second step: the passkey already is two of them in one — the device you have, and the fingerprint, face or PIN that unlocks it.', 'users-dlx-plus' ); ?></p>

		<p class="users-dlx-plus-notice" data-users-dlx-plus-passkey-aviso hidden></p>

		<?php $users_dlx_plus_llaves = users_dlx_plus_passkeys( $users_dlx_plus_id ); ?>

		<?php if ( array() !== $users_dlx_plus_llaves ) : ?>
			<ul class="users-dlx-plus-llaves">
				<?php foreach ( $users_dlx_plus_llaves as $users_dlx_plus_n => $users_dlx_plus_llave ) : ?>
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
						<details class="users-dlx-plus-llave">
							<summary class="users-dlx-plus-llave__fila">
								<span class="users-dlx-plus-llave__logo" aria-hidden="true">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2a8 8 0 0 0-7.7 10.2L2 16.5V22h5.5v-2.5H10V17h2.5l1.3-1.3A8 8 0 1 0 14 2m3 6.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3"/></svg>
								</span>

								<span class="users-dlx-plus-llave__quien">
									<strong><?php echo esc_html( (string) $users_dlx_plus_llave['label'] ); ?></strong>
									<span>
										<?php
										echo esc_html(
											sprintf(
											/* translators: %s: fecha de alta */
												__( 'Added on %s', 'users-dlx-plus' ),
												wp_date( 'j M Y', (int) $users_dlx_plus_llave['created'] )
											)
										);

										if ( (int) $users_dlx_plus_llave['used'] > 0 ) {
											echo ' · ' . esc_html(
												sprintf(
												/* translators: %s: hace cuánto se usó */
													__( 'used %s ago', 'users-dlx-plus' ),
													human_time_diff( (int) $users_dlx_plus_llave['used'] )
												)
											);
										}
										?>
									</span>
								</span>

								<span class="users-dlx-plus-llave__flecha" aria-hidden="true"></span>
							</summary>

							<form class="users-dlx-plus-llave__editar" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="users_dlx_plus_passkey">
								<input type="hidden" name="users_dlx_plus_passkey" value="<?php echo esc_attr( (string) $users_dlx_plus_llave['id'] ); ?>">
								<?php wp_nonce_field( 'users_dlx_plus_passkey' ); ?>

								<label for="users-dlx-plus-llave-<?php echo esc_attr( (string) $users_dlx_plus_n ); ?>"><?php esc_html_e( 'Name of this passkey', 'users-dlx-plus' ); ?></label>
								<input type="text" id="users-dlx-plus-llave-<?php echo esc_attr( (string) $users_dlx_plus_n ); ?>" name="users_dlx_plus_passkey_label" value="<?php echo esc_attr( (string) $users_dlx_plus_llave['label'] ); ?>" maxlength="60">

								<span class="users-dlx-plus-llave__acciones">
									<button type="submit" name="users_dlx_plus_passkey_do" value="rename" class="users-dlx-plus-button"><?php esc_html_e( 'Save name', 'users-dlx-plus' ); ?></button>
									<button type="submit" name="users_dlx_plus_passkey_do" value="delete" class="users-dlx-plus-button users-dlx-plus-button--soft"><?php esc_html_e( 'Remove this passkey', 'users-dlx-plus' ); ?></button>
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
		<p class="users-dlx-plus-llave-alta">
			<span class="users-dlx-plus-llave__campo">
				<label for="users-dlx-plus-llave-nueva"><?php esc_html_e( 'Name it, so you recognise it later', 'users-dlx-plus' ); ?></label>
				<input type="text" id="users-dlx-plus-llave-nueva" data-users-dlx-plus-passkey-label maxlength="60" placeholder="<?php echo esc_attr( users_dlx_plus_passkey_label() ); ?>">
			</span>
			<button type="button" class="users-dlx-plus-button" data-users-dlx-plus-passkey="register"><?php esc_html_e( 'Add a passkey', 'users-dlx-plus' ); ?></button>
		</p>
	<?php users_dlx_plus_panel_close(); ?>
<?php endif; ?>

<?php if ( $users_dlx_plus_ofrece ) : ?>

	<?php if ( array() !== $users_dlx_plus_frescos ) : ?>
		<?php users_dlx_plus_panel_open( __( 'Write these down now', 'users-dlx-plus' ), true, 'users-dlx-plus-respaldo' ); ?>
			<p><?php esc_html_e( 'Each one gets you in once, if you lose the phone or the email. They are shown only this time: we keep them scrambled, so nobody —including us— can read them back.', 'users-dlx-plus' ); ?></p>
			<ul class="users-dlx-plus-respaldo__lista">
				<?php foreach ( $users_dlx_plus_frescos as $users_dlx_plus_code ) : ?>
					<li><code><?php echo esc_html( $users_dlx_plus_code ); ?></code></li>
				<?php endforeach; ?>
			</ul>
		<?php users_dlx_plus_panel_close(); ?>
	<?php endif; ?>

	<?php users_dlx_plus_panel_open( __( 'Two-step verification', 'users-dlx-plus' ) ); ?>
		<p><?php esc_html_e( 'One more thing when you sign in: a code that only you have. If somebody gets hold of your email, they still do not get in.', 'users-dlx-plus' ); ?></p>

		<?php
		// Lo que se dice acá tiene que ser lo que va a pasar, y eso cambia
		// según por dónde entre cada quien: la excepción del enlace por correo
		// es sólo del enlace por correo. Con una red social prendida, el
		// segundo paso sí se pide, y decir lo contrario es peor que callarse.
		if ( $users_dlx_plus_on && ! users_dlx_plus_2fa_worth_it_on_link( $users_dlx_plus_id ) && users_dlx_plus_login_has_link() ) :
			?>
			<p class="users-dlx-plus-notice users-dlx-plus-notice--info">
				<?php if ( array() === $users_dlx_plus_piden ) : ?>
					<?php esc_html_e( 'Right now it is not being asked anywhere: the only way in you have is the link we email you, and the second step would be another code to that same inbox — it would not prove anything new.', 'users-dlx-plus' ); ?>
				<?php else : ?>
					<?php
					echo esc_html(
						sprintf(
						/* translators: %s: lista de formas de entrar, ya separadas por comas */
							__( 'It is not asked when you get in with the link we email you: that link already proves you have the inbox, and the code would go to the same place. It is asked when you get in with %s.', 'users-dlx-plus' ),
							wp_sprintf( '%l', $users_dlx_plus_piden )
						)
					);
					?>
				<?php endif; ?>

				<?php if ( ! users_dlx_plus_totp_ready( $users_dlx_plus_id ) ) : ?>
					<?php esc_html_e( 'Set up the authenticator app below and it starts being asked with the link too.', 'users-dlx-plus' ); ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<p>
			<span class="users-dlx-plus-chip">
			<?php
				echo $users_dlx_plus_on
					? esc_html__( 'On', 'users-dlx-plus' )
					: esc_html__( 'Off', 'users-dlx-plus' );
			?>
			</span>
			<?php if ( 'required' === (string) users_dlx_plus_option( 'users_dlx_plus_2fa_mode' ) ) : ?>
				<span class="users-dlx-plus-note"><?php esc_html_e( 'This site requires it.', 'users-dlx-plus' ); ?></span>
			<?php endif; ?>
		</p>

		<?php if ( array() !== $users_dlx_plus_listos ) : ?>
			<p class="users-dlx-plus-note">
				<?php
				echo esc_html(
					sprintf(
					/* translators: %s: lista de métodos disponibles */
						__( 'Ready to use: %s.', 'users-dlx-plus' ),
						implode( ', ', wp_list_pluck( $users_dlx_plus_listos, 'label' ) )
					)
				);
				?>
			</p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="users_dlx_plus_security">
			<?php wp_nonce_field( 'users_dlx_plus_security' ); ?>

			<?php if ( ! $users_dlx_plus_on ) : ?>
				<button type="submit" name="users_dlx_plus_security" value="on" class="users-dlx-plus-button"><?php esc_html_e( 'Turn it on', 'users-dlx-plus' ); ?></button>
			<?php elseif ( users_dlx_plus_2fa_can_turn_off( $users_dlx_plus_id ) ) : ?>
				<button type="submit" name="users_dlx_plus_security" value="off" class="users-dlx-plus-button users-dlx-plus-button--soft"><?php esc_html_e( 'Turn it off', 'users-dlx-plus' ); ?></button>
			<?php endif; ?>

			<?php if ( $users_dlx_plus_on ) : ?>
				<button type="submit" name="users_dlx_plus_security" value="backup" class="users-dlx-plus-button users-dlx-plus-button--soft">
					<?php
					echo esc_html(
						sprintf(
						/* translators: %d: códigos que le quedan */
							__( 'New backup codes (%d left)', 'users-dlx-plus' ),
							users_dlx_plus_backup_left( $users_dlx_plus_id )
						)
					);
					?>
				</button>
			<?php endif; ?>
		</form>
	<?php users_dlx_plus_panel_close(); ?>

	<?php if ( isset( users_dlx_plus_2fa_methods()['totp'] ) ) : ?>
		<?php users_dlx_plus_panel_open( __( 'Authenticator app', 'users-dlx-plus' ) ); ?>

			<?php if ( users_dlx_plus_totp_ready( $users_dlx_plus_id ) ) : ?>
				<p><?php esc_html_e( 'Set up. When you sign in, we ask for the six-digit code from the app.', 'users-dlx-plus' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="users_dlx_plus_security">
					<?php wp_nonce_field( 'users_dlx_plus_security' ); ?>
					<button type="submit" name="users_dlx_plus_security" value="totp_off" class="users-dlx-plus-button users-dlx-plus-button--soft"><?php esc_html_e( 'Remove it', 'users-dlx-plus' ); ?></button>
				</form>
				<?php
			else :
				$users_dlx_plus_secret = users_dlx_plus_totp_pending( $users_dlx_plus_id );
				$users_dlx_plus_uri    = users_dlx_plus_totp_uri( $users_dlx_plus_id, $users_dlx_plus_secret );
				?>
				<p><?php esc_html_e( 'Scan this with Google Authenticator, 1Password, Aegis or whichever app you use, and then write down the code it shows to confirm it.', 'users-dlx-plus' ); ?></p>

				<div class="users-dlx-plus-totp">
					<div class="users-dlx-plus-totp__qr"><?php echo users_dlx_plus_qr_svg( $users_dlx_plus_uri, 190 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG propio. ?></div>

					<div class="users-dlx-plus-totp__manual">
						<p class="users-dlx-plus-note"><?php esc_html_e( 'Cannot scan it? Type this key into the app:', 'users-dlx-plus' ); ?></p>
						<p><code class="users-dlx-plus-totp__clave"><?php echo esc_html( users_dlx_plus_totp_readable( $users_dlx_plus_secret ) ); ?></code></p>

						<form class="users-dlx-plus-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="users_dlx_plus_security">
							<input type="hidden" name="users_dlx_plus_security" value="totp">
							<?php wp_nonce_field( 'users_dlx_plus_security' ); ?>

							<label for="users-dlx-plus-totp-code"><?php esc_html_e( 'The code from the app', 'users-dlx-plus' ); ?></label>
							<input type="text" id="users-dlx-plus-totp-code" name="users_dlx_plus_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" required>

							<button type="submit" class="users-dlx-plus-button"><?php esc_html_e( 'Confirm', 'users-dlx-plus' ); ?></button>
						</form>
					</div>
				</div>
			<?php endif; ?>
		<?php users_dlx_plus_panel_close(); ?>
	<?php endif; ?>

<?php endif; ?>

<?php
/*
 * Va en un panel como todo lo demás de esta pantalla: un título suelto
 * hereda el tamaño de h3 del tema y queda de otro cuerpo que los de
 * arriba. Acá todos los bloques son la misma caja.
 */
?>
<?php if ( users_dlx_plus_option( 'users_dlx_plus_sessions_show' ) ) : ?>
	<?php users_dlx_plus_panel_open( __( 'Where you are signed in', 'users-dlx-plus' ) ); ?>
		<?php echo do_shortcode( '[users_dlx_plus_sessions]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode propio. ?>
	<?php users_dlx_plus_panel_close(); ?>
<?php endif; ?>
