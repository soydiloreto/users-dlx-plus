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
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;

$users_plus_id      = (int) $user->ID;
$users_plus_ofrece  = users_plus_2fa_offered( $users_plus_id );
$users_plus_on      = users_plus_2fa_on( $users_plus_id );
$users_plus_listos  = users_plus_2fa_available( $users_plus_id );
$users_plus_frescos = users_plus_backup_fresh( $users_plus_id );
$users_plus_piden   = users_plus_2fa_ways_asked( $users_plus_id );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige el mensaje.
$users_plus_aviso = isset( $_GET['users-plus'] ) ? sanitize_key( wp_unslash( $_GET['users-plus'] ) ) : '';

$users_plus_avisos = array(
	'on'          => array( 'ok', __( 'Two-step verification is on.', 'users-plus' ) ),
	'off'         => array( 'ok', __( 'Two-step verification is off.', 'users-plus' ) ),
	'totp'        => array( 'ok', __( 'Your authenticator app is set up.', 'users-plus' ) ),
	'totpoff'     => array( 'ok', __( 'The authenticator app was removed.', 'users-plus' ) ),
	'backup'      => array( 'ok', __( 'New backup codes. The old ones no longer work.', 'users-plus' ) ),
	'badcode'     => array( 'error', __( 'That code is not right. Check the app and try again — they change every thirty seconds.', 'users-plus' ) ),
	'nomethod'    => array( 'error', __( 'First set up a way to receive the second step.', 'users-plus' ) ),
	'required'    => array( 'error', __( 'This site requires two-step verification: it cannot be turned off.', 'users-plus' ) ),
	'passkeyoff'  => array( 'ok', __( 'The passkey was removed.', 'users-plus' ) ),
	'passkeyname' => array( 'ok', __( 'The passkey has a new name.', 'users-plus' ) ),
);

if ( users_plus_passkeys_enabled() ) {
	users_plus_passkeys_enqueue();
}
?>

<?php if ( isset( $users_plus_avisos[ $users_plus_aviso ] ) ) : ?>
	<p class="users-plus-notice users-plus-notice--<?php echo esc_attr( $users_plus_avisos[ $users_plus_aviso ][0] ); ?>"><?php echo esc_html( $users_plus_avisos[ $users_plus_aviso ][1] ); ?></p>
<?php endif; ?>

<?php users_plus_panel_open( __( 'How you get in', 'users-plus' ), true ); ?>
	<dl class="users-plus-datos">
		<dt><?php esc_html_e( 'Email', 'users-plus' ); ?></dt>
		<dd><?php echo esc_html( $user->user_email ); ?></dd>
		<dt><?php esc_html_e( 'Password', 'users-plus' ); ?></dt>
		<dd>
			<?php
			echo users_plus_login_has_password()
				? esc_html__( 'The one on your WordPress account.', 'users-plus' )
				: esc_html__( 'You do not have one. You get in with a link sent to your email, or with a social account.', 'users-plus' );
			?>
		</dd>
	</dl>
<?php users_plus_panel_close(); ?>

<?php
/*
 * Las passkeys van antes que el segundo factor porque son la mejor
 * respuesta al mismo problema, no un accesorio de la respuesta anterior:
 * quien pueda usarlas no necesita nada de lo que viene abajo.
 */
?>
<?php if ( users_plus_passkeys_enabled() ) : ?>
	<?php users_plus_panel_open( __( 'Passkeys', 'users-plus' ) ); ?>
		<p><?php esc_html_e( 'The way in with no password and nothing to type: the fingerprint, the face or the PIN of your own device. The key never leaves it, there is nothing on our side worth stealing, and it cannot be used on a fake site pretending to be this one.', 'users-plus' ); ?></p>

		<p class="users-plus-note"><?php esc_html_e( 'When you get in with a passkey we do not ask for a second step: the passkey already is two of them in one — the device you have, and the fingerprint, face or PIN that unlocks it.', 'users-plus' ); ?></p>

		<p class="users-plus-notice" data-users-plus-passkey-aviso hidden></p>

		<?php $users_plus_llaves = users_plus_passkeys( $users_plus_id ); ?>

		<?php if ( array() !== $users_plus_llaves ) : ?>
			<ul class="users-plus-llaves">
				<?php foreach ( $users_plus_llaves as $users_plus_n => $users_plus_llave ) : ?>
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
						<details class="users-plus-llave">
							<summary class="users-plus-llave__fila">
								<span class="users-plus-llave__logo" aria-hidden="true">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2a8 8 0 0 0-7.7 10.2L2 16.5V22h5.5v-2.5H10V17h2.5l1.3-1.3A8 8 0 1 0 14 2m3 6.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3"/></svg>
								</span>

								<span class="users-plus-llave__quien">
									<strong><?php echo esc_html( (string) $users_plus_llave['label'] ); ?></strong>
									<span>
										<?php
										echo esc_html(
											sprintf(
											/* translators: %s: fecha de alta */
												__( 'Added on %s', 'users-plus' ),
												wp_date( 'j M Y', (int) $users_plus_llave['created'] )
											)
										);

										if ( (int) $users_plus_llave['used'] > 0 ) {
											echo ' · ' . esc_html(
												sprintf(
												/* translators: %s: hace cuánto se usó */
													__( 'used %s ago', 'users-plus' ),
													human_time_diff( (int) $users_plus_llave['used'] )
												)
											);
										}
										?>
									</span>
								</span>

								<span class="users-plus-llave__flecha" aria-hidden="true"></span>
							</summary>

							<form class="users-plus-llave__editar" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="users_plus_passkey">
								<input type="hidden" name="users_plus_passkey" value="<?php echo esc_attr( (string) $users_plus_llave['id'] ); ?>">
								<?php wp_nonce_field( 'users_plus_passkey' ); ?>

								<label for="users-plus-llave-<?php echo esc_attr( (string) $users_plus_n ); ?>"><?php esc_html_e( 'Name of this passkey', 'users-plus' ); ?></label>
								<input type="text" id="users-plus-llave-<?php echo esc_attr( (string) $users_plus_n ); ?>" name="users_plus_passkey_label" value="<?php echo esc_attr( (string) $users_plus_llave['label'] ); ?>" maxlength="60">

								<span class="users-plus-llave__acciones">
									<button type="submit" name="users_plus_passkey_do" value="rename" class="users-plus-button"><?php esc_html_e( 'Save name', 'users-plus' ); ?></button>
									<button type="submit" name="users_plus_passkey_do" value="delete" class="users-plus-button users-plus-button--soft"><?php esc_html_e( 'Remove this passkey', 'users-plus' ); ?></button>
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
		<p class="users-plus-llave-alta">
			<span class="users-plus-llave__campo">
				<label for="users-plus-llave-nueva"><?php esc_html_e( 'Name it, so you recognise it later', 'users-plus' ); ?></label>
				<input type="text" id="users-plus-llave-nueva" data-users-plus-passkey-label maxlength="60" placeholder="<?php echo esc_attr( users_plus_passkey_label() ); ?>">
			</span>
			<button type="button" class="users-plus-button" data-users-plus-passkey="register"><?php esc_html_e( 'Add a passkey', 'users-plus' ); ?></button>
		</p>
	<?php users_plus_panel_close(); ?>
<?php endif; ?>

<?php if ( $users_plus_ofrece ) : ?>

	<?php if ( array() !== $users_plus_frescos ) : ?>
		<?php users_plus_panel_open( __( 'Write these down now', 'users-plus' ), true, 'users-plus-respaldo' ); ?>
			<p><?php esc_html_e( 'Each one gets you in once, if you lose the phone or the email. They are shown only this time: we keep them scrambled, so nobody —including us— can read them back.', 'users-plus' ); ?></p>
			<ul class="users-plus-respaldo__lista">
				<?php foreach ( $users_plus_frescos as $users_plus_code ) : ?>
					<li><code><?php echo esc_html( $users_plus_code ); ?></code></li>
				<?php endforeach; ?>
			</ul>
		<?php users_plus_panel_close(); ?>
	<?php endif; ?>

	<?php users_plus_panel_open( __( 'Two-step verification', 'users-plus' ) ); ?>
		<p><?php esc_html_e( 'One more thing when you sign in: a code that only you have. If somebody gets hold of your email, they still do not get in.', 'users-plus' ); ?></p>

		<?php
		// Lo que se dice acá tiene que ser lo que va a pasar, y eso cambia
		// según por dónde entre cada quien: la excepción del enlace por correo
		// es sólo del enlace por correo. Con una red social prendida, el
		// segundo paso sí se pide, y decir lo contrario es peor que callarse.
		if ( $users_plus_on && ! users_plus_2fa_worth_it_on_link( $users_plus_id ) && users_plus_login_has_link() ) :
			?>
			<p class="users-plus-notice users-plus-notice--info">
				<?php if ( array() === $users_plus_piden ) : ?>
					<?php esc_html_e( 'Right now it is not being asked anywhere: the only way in you have is the link we email you, and the second step would be another code to that same inbox — it would not prove anything new.', 'users-plus' ); ?>
				<?php else : ?>
					<?php
					echo esc_html(
						sprintf(
						/* translators: %s: lista de formas de entrar, ya separadas por comas */
							__( 'It is not asked when you get in with the link we email you: that link already proves you have the inbox, and the code would go to the same place. It is asked when you get in with %s.', 'users-plus' ),
							wp_sprintf( '%l', $users_plus_piden )
						)
					);
					?>
				<?php endif; ?>

				<?php if ( ! users_plus_totp_ready( $users_plus_id ) ) : ?>
					<?php esc_html_e( 'Set up the authenticator app below and it starts being asked with the link too.', 'users-plus' ); ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<p>
			<span class="users-plus-chip">
			<?php
				echo $users_plus_on
					? esc_html__( 'On', 'users-plus' )
					: esc_html__( 'Off', 'users-plus' );
			?>
			</span>
			<?php if ( 'required' === (string) users_plus_option( 'users_plus_2fa_mode' ) ) : ?>
				<span class="users-plus-note"><?php esc_html_e( 'This site requires it.', 'users-plus' ); ?></span>
			<?php endif; ?>
		</p>

		<?php if ( array() !== $users_plus_listos ) : ?>
			<p class="users-plus-note">
				<?php
				echo esc_html(
					sprintf(
					/* translators: %s: lista de métodos disponibles */
						__( 'Ready to use: %s.', 'users-plus' ),
						implode( ', ', wp_list_pluck( $users_plus_listos, 'label' ) )
					)
				);
				?>
			</p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="users_plus_security">
			<?php wp_nonce_field( 'users_plus_security' ); ?>

			<?php if ( ! $users_plus_on ) : ?>
				<button type="submit" name="users_plus_security" value="on" class="users-plus-button"><?php esc_html_e( 'Turn it on', 'users-plus' ); ?></button>
			<?php elseif ( users_plus_2fa_can_turn_off( $users_plus_id ) ) : ?>
				<button type="submit" name="users_plus_security" value="off" class="users-plus-button users-plus-button--soft"><?php esc_html_e( 'Turn it off', 'users-plus' ); ?></button>
			<?php endif; ?>

			<?php if ( $users_plus_on ) : ?>
				<button type="submit" name="users_plus_security" value="backup" class="users-plus-button users-plus-button--soft">
					<?php
					echo esc_html(
						sprintf(
						/* translators: %d: códigos que le quedan */
							__( 'New backup codes (%d left)', 'users-plus' ),
							users_plus_backup_left( $users_plus_id )
						)
					);
					?>
				</button>
			<?php endif; ?>
		</form>
	<?php users_plus_panel_close(); ?>

	<?php if ( isset( users_plus_2fa_methods()['totp'] ) ) : ?>
		<?php users_plus_panel_open( __( 'Authenticator app', 'users-plus' ) ); ?>

			<?php if ( users_plus_totp_ready( $users_plus_id ) ) : ?>
				<p><?php esc_html_e( 'Set up. When you sign in, we ask for the six-digit code from the app.', 'users-plus' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="users_plus_security">
					<?php wp_nonce_field( 'users_plus_security' ); ?>
					<button type="submit" name="users_plus_security" value="totp_off" class="users-plus-button users-plus-button--soft"><?php esc_html_e( 'Remove it', 'users-plus' ); ?></button>
				</form>
				<?php
			else :
				$users_plus_secret = users_plus_totp_pending( $users_plus_id );
				$users_plus_uri    = users_plus_totp_uri( $users_plus_id, $users_plus_secret );
				?>
				<p><?php esc_html_e( 'Scan this with Google Authenticator, 1Password, Aegis or whichever app you use, and then write down the code it shows to confirm it.', 'users-plus' ); ?></p>

				<div class="users-plus-totp">
					<div class="users-plus-totp__qr"><?php echo users_plus_qr_svg( $users_plus_uri, 190 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG propio. ?></div>

					<div class="users-plus-totp__manual">
						<p class="users-plus-note"><?php esc_html_e( 'Cannot scan it? Type this key into the app:', 'users-plus' ); ?></p>
						<p><code class="users-plus-totp__clave"><?php echo esc_html( users_plus_totp_readable( $users_plus_secret ) ); ?></code></p>

						<form class="users-plus-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="users_plus_security">
							<input type="hidden" name="users_plus_security" value="totp">
							<?php wp_nonce_field( 'users_plus_security' ); ?>

							<label for="users-plus-totp-code"><?php esc_html_e( 'The code from the app', 'users-plus' ); ?></label>
							<input type="text" id="users-plus-totp-code" name="users_plus_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" required>

							<button type="submit" class="users-plus-button"><?php esc_html_e( 'Confirm', 'users-plus' ); ?></button>
						</form>
					</div>
				</div>
			<?php endif; ?>
		<?php users_plus_panel_close(); ?>
	<?php endif; ?>

<?php endif; ?>

<?php
/*
 * Va en un panel como todo lo demás de esta pantalla: un título suelto
 * hereda el tamaño de h3 del tema y queda de otro cuerpo que los de
 * arriba. Acá todos los bloques son la misma caja.
 */
?>
<?php if ( users_plus_option( 'users_plus_sessions_show' ) ) : ?>
	<?php users_plus_panel_open( __( 'Where you are signed in', 'users-plus' ) ); ?>
		<?php echo do_shortcode( '[users_plus_sessions]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode propio. ?>
	<?php users_plus_panel_close(); ?>
<?php endif; ?>
