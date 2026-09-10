<?php
/**
 * La pantalla del acceso por correo, con sus tres partes.
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;

/** La pantalla de registro y acceso, con sus solapas. */
function users_dlx_plus_screen_login(): void {
	$tabs = array(
		'registration' => __( 'Registration', 'users-dlx-plus' ),
		'link'         => __( 'Sign in', 'users-dlx-plus' ),
		'email'        => __( 'The email', 'users-dlx-plus' ),
		'handle'       => __( 'Public name', 'users-dlx-plus' ),
		'2fa'          => __( 'Two-step verification', 'users-dlx-plus' ),
		'passkeys'     => __( 'Passkeys', 'users-dlx-plus' ),
	);

	$current = users_dlx_plus_tab( $tabs );

	if ( isset( $_POST['users_dlx_plus_options_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['users_dlx_plus_options_nonce'] ) ), 'users_dlx_plus_options' ) ) {
		users_dlx_plus_screen_login_save( $current );
		users_dlx_plus_notice( __( 'Settings saved.', 'users-dlx-plus' ) );
	}

	users_dlx_plus_screen_open( __( 'Registration and login', 'users-dlx-plus' ), 'users-dlx-plus-login', $tabs, $current );

	echo '<form method="post">';
	wp_nonce_field( 'users_dlx_plus_options', 'users_dlx_plus_options_nonce' );

	switch ( $current ) {
		case 'registration':
			users_dlx_plus_screen_login_registration();
			break;

		case 'email':
			users_dlx_plus_screen_login_email();
			break;

		case 'handle':
			users_dlx_plus_screen_login_handle();
			break;

		case '2fa':
			users_dlx_plus_screen_login_2fa();
			break;

		case 'passkeys':
			users_dlx_plus_screen_login_passkeys();
			break;

		default:
			users_dlx_plus_screen_login_link();
	}

	submit_button();
	echo '</form>';

	users_dlx_plus_screen_close();
}

/** Guarda sólo lo que manda la solapa que se está mirando. */
function users_dlx_plus_screen_login_save( string $tab ): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- lo verifica quien llama.
	if ( 'email' === $tab ) {
		users_dlx_plus_save_options(
			array(
				'users_dlx_plus_login_subject' => sanitize_text_field( wp_unslash( $_POST['users_dlx_plus_login_subject'] ?? '' ) ),
				'users_dlx_plus_login_body'    => sanitize_textarea_field( wp_unslash( $_POST['users_dlx_plus_login_body'] ?? '' ) ),
			)
		);

		return;
	}

	if ( 'passkeys' === $tab ) {
		users_dlx_plus_save_options(
			array(
				'users_dlx_plus_passkey_enabled' => isset( $_POST['users_dlx_plus_passkey_enabled'] ) ? 1 : 0,
				'users_dlx_plus_passkey_where'   => sanitize_key( wp_unslash( $_POST['users_dlx_plus_passkey_where'] ?? 'any' ) ),
				'users_dlx_plus_passkey_verify'  => isset( $_POST['users_dlx_plus_passkey_verify'] ) ? 1 : 0,
			)
		);

		return;
	}

	if ( '2fa' === $tab ) {
		users_dlx_plus_save_options(
			array(
				'users_dlx_plus_2fa_mode'          => sanitize_key( wp_unslash( $_POST['users_dlx_plus_2fa_mode'] ?? 'optional' ) ),
				'users_dlx_plus_2fa_methods'       => array_map( 'sanitize_key', (array) wp_unslash( $_POST['users_dlx_plus_2fa_methods'] ?? array() ) ),
				'users_dlx_plus_2fa_roles'         => array_map( 'sanitize_key', (array) wp_unslash( $_POST['users_dlx_plus_2fa_roles'] ?? array() ) ),
				'users_dlx_plus_2fa_link'          => sanitize_key( wp_unslash( $_POST['users_dlx_plus_2fa_link'] ?? 'auto' ) ),
				'users_dlx_plus_2fa_remember_days' => absint( wp_unslash( $_POST['users_dlx_plus_2fa_remember_days'] ?? 30 ) ),
			)
		);

		return;
	}

	if ( 'handle' === $tab ) {
		users_dlx_plus_save_options(
			array(
				'users_dlx_plus_handle_enabled'  => isset( $_POST['users_dlx_plus_handle_enabled'] ) ? 1 : 0,
				'users_dlx_plus_handle_login'    => isset( $_POST['users_dlx_plus_handle_login'] ) ? 1 : 0,
				'users_dlx_plus_handle_min'      => absint( wp_unslash( $_POST['users_dlx_plus_handle_min'] ?? 3 ) ),
				'users_dlx_plus_handle_max'      => absint( wp_unslash( $_POST['users_dlx_plus_handle_max'] ?? 30 ) ),
				'users_dlx_plus_handle_charset'  => sanitize_key( wp_unslash( $_POST['users_dlx_plus_handle_charset'] ?? 'strict' ) ),
				'users_dlx_plus_handle_spaces'   => sanitize_key( wp_unslash( $_POST['users_dlx_plus_handle_spaces'] ?? 'dash' ) ),
				'users_dlx_plus_handle_cooldown' => absint( wp_unslash( $_POST['users_dlx_plus_handle_cooldown'] ?? 30 ) ),
				'users_dlx_plus_handle_reserved' => sanitize_textarea_field( wp_unslash( $_POST['users_dlx_plus_handle_reserved'] ?? '' ) ),
			)
		);

		return;
	}

	if ( 'registration' === $tab ) {
		users_dlx_plus_save_options(
			array(
				'users_dlx_plus_login_register'  => isset( $_POST['users_dlx_plus_login_register'] ) ? 1 : 0,
				'users_dlx_plus_login_role'      => sanitize_key( wp_unslash( $_POST['users_dlx_plus_login_role'] ?? 'subscriber' ) ),
				'users_dlx_plus_wp_registration' => sanitize_key( wp_unslash( $_POST['users_dlx_plus_wp_registration'] ?? 'site' ) ),
				'users_dlx_plus_wp_profile'      => sanitize_key( wp_unslash( $_POST['users_dlx_plus_wp_profile'] ?? 'allow' ) ),
			)
		);

		return;
	}

	users_dlx_plus_save_options(
		array(
			'users_dlx_plus_login_method'   => sanitize_key( wp_unslash( $_POST['users_dlx_plus_login_method'] ?? 'both' ) ),
			'users_dlx_plus_login_page'     => absint( wp_unslash( $_POST['users_dlx_plus_login_page'] ?? 0 ) ),
			'users_dlx_plus_login_expiry'   => absint( wp_unslash( $_POST['users_dlx_plus_login_expiry'] ?? 15 ) ),
			'users_dlx_plus_login_throttle' => absint( wp_unslash( $_POST['users_dlx_plus_login_throttle'] ?? 60 ) ),
		)
	);
	// phpcs:enable
}

/** Cómo entra la gente: la puerta, la página y los tiempos. */
function users_dlx_plus_screen_login_link(): void {
	users_dlx_plus_intro( __( 'The person types their email and gets a single-use link. There is no password to choose, to remember or to steal. Put the [users_dlx_plus_login] shortcode on the page you pick below.', 'users-dlx-plus' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Sign-in page', 'users-dlx-plus' ); ?></th>
			<td>
				<?php
				/** @var array<string, mixed> $users_dlx_plus_dropdown */
				$users_dlx_plus_dropdown = array(
					'name'              => 'users_dlx_plus_login_page',
					'selected'          => (int) users_dlx_plus_option( 'users_dlx_plus_login_page' ),
					'show_option_none'  => __( '— Use wp-login.php —', 'users-dlx-plus' ),
					'option_none_value' => 0,
				);

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_dropdown_pages() escapa lo suyo y pinta él.
				wp_dropdown_pages( $users_dlx_plus_dropdown );
				?>
				<p class="description"><?php esc_html_e( 'The page holding the form. Without it, passwordless mode is not applied: it would leave the site with no way in.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'How people get in', 'users-dlx-plus' ); ?></th>
			<td>
				<?php users_dlx_plus_forzado_aviso( 'users_dlx_plus_login_method' ); ?>

				<?php
				$metodos = array(
					'both'     => __( 'Both: the link, and username and password underneath', 'users-dlx-plus' ),
					'link'     => __( 'Only a link sent to their email — no passwords on this site', 'users-dlx-plus' ),
					'password' => __( 'Only the WordPress username and password', 'users-dlx-plus' ),
				);

				foreach ( $metodos as $clave => $rotulo ) :
					?>
					<label class="users-dlx-plus-roles__item">
						<input type="radio" name="users_dlx_plus_login_method" value="<?php echo esc_attr( $clave ); ?>" <?php checked( users_dlx_plus_login_method(), $clave ); ?>>
						<?php echo esc_html( $rotulo ); ?>
					</label>
				<?php endforeach; ?>

				<p class="description"><?php esc_html_e( 'Only the first option closes anything: with “only a link”, wp-login.php stops showing its form and the native registration is switched off, because otherwise both would still be there and the choice would be a decoration.', 'users-dlx-plus' ); ?></p>
				<p class="description">
					<?php
					printf(
						/* translators: %s: URL de la salida de emergencia */
						esc_html__( 'Even then there is an emergency way in for administrators: %s', 'users-dlx-plus' ),
						'<code>' . esc_html( wp_login_url() ) . '?users-dlx-plus-admin=1</code>'
					);
					?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="users_dlx_plus_login_expiry"><?php esc_html_e( 'The link expires after', 'users-dlx-plus' ); ?></label></th>
			<td>
				<input type="number" id="users_dlx_plus_login_expiry" name="users_dlx_plus_login_expiry" min="1" max="1440" class="small-text" value="<?php echo esc_attr( (string) users_dlx_plus_option( 'users_dlx_plus_login_expiry' ) ); ?>">
				<?php esc_html_e( 'minutes', 'users-dlx-plus' ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="users_dlx_plus_login_throttle"><?php esc_html_e( 'Wait between requests', 'users-dlx-plus' ); ?></label></th>
			<td>
				<input type="number" id="users_dlx_plus_login_throttle" name="users_dlx_plus_login_throttle" min="0" class="small-text" value="<?php echo esc_attr( (string) users_dlx_plus_option( 'users_dlx_plus_login_throttle' ) ); ?>">
				<?php esc_html_e( 'seconds, for the same email address', 'users-dlx-plus' ); ?>
				<p class="description"><?php esc_html_e( 'Stops the form being used as a machine for emailing third parties.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

/** El texto del correo con el enlace de acceso. */
function users_dlx_plus_screen_login_email(): void {
	users_dlx_plus_intro( __( 'This is what lands in the inbox. Leave it empty to use the text the plugin ships with.', 'users-dlx-plus' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="users_dlx_plus_login_subject"><?php esc_html_e( 'Subject', 'users-dlx-plus' ); ?></label></th>
			<td><input type="text" id="users_dlx_plus_login_subject" name="users_dlx_plus_login_subject" class="large-text" value="<?php echo esc_attr( (string) users_dlx_plus_option( 'users_dlx_plus_login_subject' ) ); ?>" placeholder="<?php echo esc_attr( users_dlx_plus_login_subject() ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="users_dlx_plus_login_body"><?php esc_html_e( 'Message', 'users-dlx-plus' ); ?></label></th>
			<td>
				<textarea id="users_dlx_plus_login_body" name="users_dlx_plus_login_body" class="large-text code" rows="9" placeholder="<?php echo esc_attr( users_dlx_plus_login_body( '{link}' ) ); ?>"><?php echo esc_textarea( (string) users_dlx_plus_option( 'users_dlx_plus_login_body' ) ); ?></textarea>
				<p class="description">
					<?php
					printf(
						/* translators: 1 y 2: marcadores que se reemplazan */
						esc_html__( '%1$s and %2$s are replaced. Empty uses the text above.', 'users-dlx-plus' ),
						'<code>{link}</code>',
						'<code>{minutes}</code>'
					);
					?>
				</p>
			</td>
		</tr>
	</table>
	<?php
}


/**
 * El nombre público: si se ofrece, con qué reglas, y si además sirve para
 * pedir el enlace de acceso.
 */
function users_dlx_plus_screen_login_handle(): void {
	users_dlx_plus_intro( __( 'The email is the identity and nobody chooses it. This is the short name people see, the one that goes in the address of their profile.', 'users-dlx-plus' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Offer it', 'users-dlx-plus' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="users_dlx_plus_handle_enabled" value="1" <?php checked( users_dlx_plus_option( 'users_dlx_plus_handle_enabled' ), 1 ); ?>>
					<?php esc_html_e( 'Let people choose their public name', 'users-dlx-plus' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Turned off, the name comes from what they wrote as their first and last name, and the profile address is made from that.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Sign in with it', 'users-dlx-plus' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="users_dlx_plus_handle_login" value="1" <?php checked( users_dlx_plus_option( 'users_dlx_plus_handle_login' ), 1 ); ?>>
					<?php esc_html_e( 'Accept the public name in the sign-in box, as well as the email', 'users-dlx-plus' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'The link still goes to the email on the account, never to something typed at that moment: this opens no new door, it only saves people from remembering which address they used.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Length', 'users-dlx-plus' ); ?></th>
			<td>
				<input type="number" name="users_dlx_plus_handle_min" class="small-text" min="1" value="<?php echo esc_attr( (string) users_dlx_plus_option( 'users_dlx_plus_handle_min' ) ); ?>">
				<?php esc_html_e( 'to', 'users-dlx-plus' ); ?>
				<input type="number" name="users_dlx_plus_handle_max" class="small-text" min="1" value="<?php echo esc_attr( (string) users_dlx_plus_option( 'users_dlx_plus_handle_max' ) ); ?>">
				<?php esc_html_e( 'characters', 'users-dlx-plus' ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Letters', 'users-dlx-plus' ); ?></th>
			<td>
				<?php
				$sets = array(
					'strict'  => __( 'Plain: a–z, digits, dot, dash and underscore', 'users-dlx-plus' ),
					'unicode' => __( 'Also accents and ñ', 'users-dlx-plus' ),
				);

				foreach ( $sets as $clave => $rotulo ) :
					?>
					<label class="users-dlx-plus-roles__item">
						<input type="radio" name="users_dlx_plus_handle_charset" value="<?php echo esc_attr( $clave ); ?>" <?php checked( users_dlx_plus_option( 'users_dlx_plus_handle_charset' ), $clave ); ?>>
						<?php echo esc_html( $rotulo ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Whatever is typed is turned into the same thing WordPress would put in a URL, so what passes here is exactly what ends up in the address. Anything that does not fit —punctuation, symbols, emoji— is dropped, and the person sees what it turned into before saving.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Spaces', 'users-dlx-plus' ); ?></th>
			<td>
				<?php
				$espacios = array(
					'dash'   => __( 'Turn them into dashes: “Ana Gómez” becomes ana-gomez', 'users-dlx-plus' ),
					'reject' => __( 'Refuse them and say so', 'users-dlx-plus' ),
				);

				foreach ( $espacios as $clave => $rotulo ) :
					?>
					<label class="users-dlx-plus-roles__item">
						<input type="radio" name="users_dlx_plus_handle_spaces" value="<?php echo esc_attr( $clave ); ?>" <?php checked( users_dlx_plus_option( 'users_dlx_plus_handle_spaces' ), $clave ); ?>>
						<?php echo esc_html( $rotulo ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'A web address cannot have spaces, so one of the two has to happen. The first is what almost everybody expects; the second is for a site that would rather nobody ends up with a name they did not type.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Taken names', 'users-dlx-plus' ); ?></th>
			<td>
				<p class="description">
					<?php
					printf(
						/* translators: 1: user_nicename, 2: user_login */
						esc_html__( 'Always checked, and against two things: the public names already in use (%1$s) and the usernames that came with the accounts (%2$s). The second one matters because a site that lets people sign in by public name would otherwise have two people answering to the same text, and the link would go to the wrong account.', 'users-dlx-plus' ),
						'<code>user_nicename</code>',
						'<code>user_login</code>'
					);
					?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'How often it can change', 'users-dlx-plus' ); ?></th>
			<td>
				<input type="number" name="users_dlx_plus_handle_cooldown" class="small-text" min="0" value="<?php echo esc_attr( (string) users_dlx_plus_option( 'users_dlx_plus_handle_cooldown' ) ); ?>">
				<?php esc_html_e( 'days between one change and the next', 'users-dlx-plus' ); ?>
				<p class="description"><?php esc_html_e( '0 means whenever they like. A name that changes every day does not identify anybody, and the old address stops working each time.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="users_dlx_plus_handle_reserved"><?php esc_html_e( 'Names nobody can take', 'users-dlx-plus' ); ?></label></th>
			<td>
				<textarea id="users_dlx_plus_handle_reserved" name="users_dlx_plus_handle_reserved" rows="3" class="large-text code"><?php echo esc_textarea( (string) users_dlx_plus_option( 'users_dlx_plus_handle_reserved' ) ); ?></textarea>
				<p class="description"><?php esc_html_e( 'One per line, or separated by commas. The obvious ones —admin, support, api, login— are already blocked; these are yours to add.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Registro: quién puede crearse una cuenta acá y con qué rol.
 *
 * Va aparte del acceso porque son dos preguntas distintas: una es quién entra
 * a algo que ya existe y la otra es quién puede empezar a existir.
 */
function users_dlx_plus_screen_login_registration(): void {
	users_dlx_plus_intro( __( 'Who gets an account on this site, and what that account can do. Everything else —how they get in afterwards— is on the next tab.', 'users-dlx-plus' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Create the account', 'users-dlx-plus' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="users_dlx_plus_login_register" value="1" <?php checked( users_dlx_plus_option( 'users_dlx_plus_login_register' ), 1 ); ?>>
					<?php esc_html_e( 'If the email does not exist, register it in the same step', 'users-dlx-plus' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'With this, signing in and signing up are the same thing and no separate registration form is needed.', 'users-dlx-plus' ); ?></p>
				<p class="description"><?php esc_html_e( 'Turned off, only people who already have an account can get in — and somebody has to create the accounts from Users.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="users_dlx_plus_login_role"><?php esc_html_e( 'Role for new accounts', 'users-dlx-plus' ); ?></label></th>
			<td>
				<select name="users_dlx_plus_login_role" id="users_dlx_plus_login_role">
					<?php wp_dropdown_roles( (string) users_dlx_plus_option( 'users_dlx_plus_login_role' ) ); ?>
				</select>
				<p class="description"><?php esc_html_e( 'The same one for everybody, whether they come in by email or by a social network: a role per provider is a quiet way of handing out privileges.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'The WordPress registration form', 'users-dlx-plus' ); ?></th>
			<td>
				<?php
				$registro = array(
					'site' => __( 'Whatever Settings → General says', 'users-dlx-plus' ),
					'on'   => __( 'Open, whatever that setting says', 'users-dlx-plus' ),
					'off'  => __( 'Closed, whatever that setting says', 'users-dlx-plus' ),
				);

				foreach ( $registro as $clave => $rotulo ) :
					?>
					<label class="users-dlx-plus-roles__item">
						<input type="radio" name="users_dlx_plus_wp_registration" value="<?php echo esc_attr( $clave ); ?>" <?php checked( users_dlx_plus_option( 'users_dlx_plus_wp_registration' ), $clave ); ?>>
						<?php echo esc_html( $rotulo ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'This is wp-login.php?action=register and wp-signup.php, the ones WordPress brings. They are decided here because this is where the accounts are administered; leaving that in another screen is how a site ends up with a registration form nobody remembers is open.', 'users-dlx-plus' ); ?></p>
				<p class="description"><?php esc_html_e( 'With “only a link” as the way in, it stays closed no matter what: it would hand out accounts with a password through a door the site closed.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'The WordPress profile screen', 'users-dlx-plus' ); ?></th>
			<td>
				<?php
				$perfil = array(
					'allow'    => __( 'Leave it alone', 'users-dlx-plus' ),
					'redirect' => __( 'Send them to their account on the site', 'users-dlx-plus' ),
					'block'    => __( 'Close it: their details are edited on the site', 'users-dlx-plus' ),
				);

				foreach ( $perfil as $clave => $rotulo ) :
					?>
					<label class="users-dlx-plus-roles__item">
						<input type="radio" name="users_dlx_plus_wp_profile" value="<?php echo esc_attr( $clave ); ?>" <?php checked( users_dlx_plus_option( 'users_dlx_plus_wp_profile' ), $clave ); ?>>
						<?php echo esc_html( $rotulo ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'A site that built its account area on the front does not want half the data edited on another screen, with another look and other rules: the edit limits and the required fields set up here do not apply there.', 'users-dlx-plus' ); ?></p>
				<p class="description"><?php esc_html_e( 'It never applies to whoever administers: that is the person who has to be able to fix what broke, and the desktop profile is where it gets fixed.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'How the account ends up', 'users-dlx-plus' ); ?></th>
			<td>
				<p class="description"><?php esc_html_e( 'The email is the username and it never changes —WordPress does not allow it. There is no password at all, not even one nobody knows. The name shown comes from what the person writes, and the fields they are asked for are the ones on the User fields screen.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * El segundo factor: a quién se le pide, con qué, y cuándo no.
 *
 * Los métodos se listan desde el registro y no a mano: si un add-on agrega
 * uno, aparece acá solo.
 */
function users_dlx_plus_screen_login_2fa(): void {
	users_dlx_plus_intro( __( 'One more thing after the password or the link: a code that only that person has. Whoever gets hold of an email still does not get in.', 'users-dlx-plus' ) );

	// Se piden todos, no sólo los prendidos: la casilla de un método apagado
	// tiene que existir para poder prenderlo.
	$todos     = (array) apply_filters(
		'users_dlx_plus_2fa_methods',
		array(
			'email' => array( 'label' => __( 'A code by email', 'users-dlx-plus' ) ),
			'totp'  => array( 'label' => __( 'An authenticator app', 'users-dlx-plus' ) ),
		)
	);
	$prendidos = (array) users_dlx_plus_option( 'users_dlx_plus_2fa_methods' );
	$roles     = (array) users_dlx_plus_option( 'users_dlx_plus_2fa_roles' );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'When it is asked for', 'users-dlx-plus' ); ?></th>
			<td>
				<?php
				$modos = array(
					'optional' => __( 'Optional: whoever wants it turns it on from their profile', 'users-dlx-plus' ),
					'required' => __( 'Required: everybody who can use it has to', 'users-dlx-plus' ),
					'off'      => __( 'Off: it is not offered at all', 'users-dlx-plus' ),
				);

				foreach ( $modos as $clave => $rotulo ) :
					?>
					<label class="users-dlx-plus-roles__item">
						<input type="radio" name="users_dlx_plus_2fa_mode" value="<?php echo esc_attr( $clave ); ?>" <?php checked( users_dlx_plus_option( 'users_dlx_plus_2fa_mode' ), $clave ); ?>>
						<?php echo esc_html( $rotulo ); ?>
					</label>
				<?php endforeach; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'With what', 'users-dlx-plus' ); ?></th>
			<td>
				<?php foreach ( $todos as $clave => $metodo ) : ?>
					<label class="users-dlx-plus-roles__item">
						<input type="checkbox" name="users_dlx_plus_2fa_methods[]" value="<?php echo esc_attr( $clave ); ?>" <?php checked( in_array( $clave, $prendidos, true ) ); ?>>
						<?php echo esc_html( $metodo['label'] ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'The app is the stronger one: the code never travels. The email is the one people actually turn on, because there is nothing to install.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'To whom', 'users-dlx-plus' ); ?></th>
			<td>
				<?php foreach ( wp_roles()->get_names() as $rol => $rotulo ) : ?>
					<label class="users-dlx-plus-roles__item">
						<input type="checkbox" name="users_dlx_plus_2fa_roles[]" value="<?php echo esc_attr( $rol ); ?>" <?php checked( in_array( $rol, $roles, true ) ); ?>>
						<?php echo esc_html( translate_user_role( $rotulo ) ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'None ticked means everybody. Ticking only the roles that can change things is the usual middle ground: the second step where it is worth the friction.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Coming in by email link', 'users-dlx-plus' ); ?></th>
			<td>
				<?php
				$enlace = array(
					'auto'   => __( 'Work it out: ask, unless the second step is another email', 'users-dlx-plus' ),
					'always' => __( 'Always ask', 'users-dlx-plus' ),
					'never'  => __( 'Never ask', 'users-dlx-plus' ),
				);

				foreach ( $enlace as $clave => $rotulo ) :
					?>
					<label class="users-dlx-plus-roles__item">
						<input type="radio" name="users_dlx_plus_2fa_link" value="<?php echo esc_attr( $clave ); ?>" <?php checked( users_dlx_plus_option( 'users_dlx_plus_2fa_link' ), $clave ); ?>>
						<?php echo esc_html( $rotulo ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'A code sent to the same inbox the person just opened to follow the link does not prove anything the link did not prove already. An authenticator app does. That is the whole rule, and it is why the first option exists: it asks when asking is worth something.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="users_dlx_plus_2fa_remember_days"><?php esc_html_e( 'Remember the browser', 'users-dlx-plus' ); ?></label></th>
			<td>
				<input type="number" id="users_dlx_plus_2fa_remember_days" name="users_dlx_plus_2fa_remember_days" class="small-text" min="0" value="<?php echo esc_attr( (string) users_dlx_plus_option( 'users_dlx_plus_2fa_remember_days' ) ); ?>">
				<?php esc_html_e( 'days — 0 to ask every time', 'users-dlx-plus' ); ?>
				<p class="description"><?php esc_html_e( 'A signed cookie, no more: it does not let anybody in, it only saves repeating the step on a browser that already passed it.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Qué puede hacer cada persona con sus datos, sin pedirle nada a nadie.
 *
 * Los dos vienen prendidos porque es lo que corresponde. Apagarlos no es
 * esconder la obligación: es decir que esos pedidos se atienden a mano, y en
 * ese caso la sección entera desaparece del frente en vez de ofrecer botones
 * que no llevan a ningún lado.
 */
function users_dlx_plus_screen_login_privacy(): void {
	users_dlx_plus_intro( __( 'What each person can do with their own data from the site, without asking anybody.', 'users-dlx-plus' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Their data', 'users-dlx-plus' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="users_dlx_plus_privacy_export" value="1" <?php checked( users_dlx_plus_option( 'users_dlx_plus_privacy_export' ), 1 ); ?>>
					<?php esc_html_e( 'They can ask for a copy of everything and download it', 'users-dlx-plus' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'It is the export WordPress already knows how to make: it asks for confirmation by email and leaves the file ready.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Their account', 'users-dlx-plus' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="users_dlx_plus_privacy_delete" value="1" <?php checked( users_dlx_plus_option( 'users_dlx_plus_privacy_delete' ), 1 ); ?>>
					<?php esc_html_e( 'They can ask for their account to be deleted', 'users-dlx-plus' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Also confirmed by email, and never for an account that administers the site: it would leave the site with nobody in charge.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
	</table>

	<p class="description"><?php esc_html_e( 'With both off, the “Your data” section stops showing: an empty section is worse than no section.', 'users-dlx-plus' ); ?></p>
	<?php
}

/** Passkeys: si se ofrecen, cuáles se aceptan y qué se exige. */
function users_dlx_plus_screen_login_passkeys(): void {
	users_dlx_plus_intro( __( 'A passkey is a private key that lives on the person’s device or keychain and never leaves it. There is nothing on this side worth stealing, nothing to reuse on another site, and it cannot be phished: the browser refuses to sign for a domain that is not the one it was made for.', 'users-dlx-plus' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Offer them', 'users-dlx-plus' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="users_dlx_plus_passkey_enabled" value="1" <?php checked( users_dlx_plus_option( 'users_dlx_plus_passkey_enabled' ), 1 ); ?>>
					<?php esc_html_e( 'People can add passkeys and sign in with them', 'users-dlx-plus' ); ?>
				</label>
				<p class="description">
					<?php
					printf(
						/* translators: %s: el dominio con el que quedan atadas las passkeys */
						esc_html__( 'They get tied to %s. If the site moves to another domain, the passkeys made here stop working and have to be added again — there is no way around that, and it is exactly what makes them unphishable.', 'users-dlx-plus' ),
						'<code>' . esc_html( users_dlx_plus_passkey_rp_id() ) . '</code>'
					);
					?>
				</p>
				<?php if ( ! is_ssl() && 'local' !== wp_get_environment_type() ) : ?>
					<p class="description users-dlx-plus-danger"><?php esc_html_e( 'This site is not on HTTPS. Browsers will refuse passkeys until it is.', 'users-dlx-plus' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Which ones', 'users-dlx-plus' ); ?></th>
			<td>
				<?php
				$donde = array(
					'any'    => __( 'Any: the device being used, a USB key, or another phone', 'users-dlx-plus' ),
					'device' => __( 'Only the device being used', 'users-dlx-plus' ),
				);

				foreach ( $donde as $clave => $rotulo ) :
					?>
					<label class="users-dlx-plus-roles__item">
						<input type="radio" name="users_dlx_plus_passkey_where" value="<?php echo esc_attr( $clave ); ?>" <?php checked( users_dlx_plus_option( 'users_dlx_plus_passkey_where' ), $clave ); ?>>
						<?php echo esc_html( $rotulo ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'The first one lets somebody register from their laptop using their phone, and lets a hardware key work. The second keeps everything on the machine in front of them, which some organisations require and everybody else finds annoying.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Ask who they are', 'users-dlx-plus' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="users_dlx_plus_passkey_verify" value="1" <?php checked( users_dlx_plus_option( 'users_dlx_plus_passkey_verify' ), 1 ); ?>>
					<?php esc_html_e( 'Require the fingerprint, the face or the PIN', 'users-dlx-plus' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'This is what makes a passkey count as two things at once: something they have and something they are. With it off, whoever is holding an unlocked device gets in, and the passkey is worth one factor.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}
