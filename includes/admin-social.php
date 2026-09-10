<?php
/**
 * La pantalla del login social: la grilla de proveedores y la ficha de cada uno.
 *
 * Está armada como la de Nextend a propósito: quien ya configuró redes en un
 * WordPress reconoce el camino —una tarjeta por red, y adentro «cómo empezar»,
 * los ajustes y el uso— y no tiene que aprender otro.
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;

/** Screen social. */
function users_dlx_plus_screen_social(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$id        = isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : '';
	$providers = users_dlx_plus_sso_providers();

	if ( '' !== $id && isset( $providers[ $id ] ) ) {
		users_dlx_plus_screen_provider( $id, $providers[ $id ] );
		return;
	}

	// Activar o desactivar desde la grilla, sin entrar a la ficha.
	if ( isset( $_GET['users_dlx_plus_action'], $_GET['red'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'users_dlx_plus_social_toggle' );

		$red = sanitize_key( wp_unslash( $_GET['red'] ) );

		if ( isset( $providers[ $red ] ) && users_dlx_plus_sso_tested( $red ) ) {
			$all = (array) get_option( 'users_dlx_plus_sso', array() );

			$all[ $red ]['active'] = 'on' === sanitize_key( wp_unslash( $_GET['users_dlx_plus_action'] ) ) ? 1 : 0;

			update_option( 'users_dlx_plus_sso', $all );
		}

		wp_safe_redirect( users_dlx_plus_admin_url( 'users-dlx-plus-social' ) );
		exit;
	}

	$tabs = array(
		'providers' => __( 'Providers', 'users-dlx-plus' ),
		'buttons'   => __( 'Buttons', 'users-dlx-plus' ),
		'general'   => __( 'Global settings', 'users-dlx-plus' ),
	);

	$current = users_dlx_plus_tab( $tabs );

	if ( isset( $_POST['users_dlx_plus_social_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['users_dlx_plus_social_nonce'] ) ), 'users_dlx_plus_social' ) ) {
		users_dlx_plus_save_options(
			array(
				// phpcs:disable WordPress.Security.NonceVerification.Missing -- verificado arriba.
				'users_dlx_plus_sso_link_by_email' => isset( $_POST['users_dlx_plus_sso_link_by_email'] ) ? 1 : 0,
				'users_dlx_plus_sso_register'      => isset( $_POST['users_dlx_plus_sso_register'] ) ? 1 : 0,
				'users_dlx_plus_sso_verified_only' => isset( $_POST['users_dlx_plus_sso_verified_only'] ) ? 1 : 0,
				'users_dlx_plus_sso_blocked_roles' => array_map( 'sanitize_key', (array) wp_unslash( $_POST['users_dlx_plus_sso_blocked_roles'] ?? array() ) ),
				// phpcs:enable
			)
		);

		users_dlx_plus_notice( __( 'Settings saved.', 'users-dlx-plus' ) );
	}

	if ( isset( $_POST['users_dlx_plus_buttons_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['users_dlx_plus_buttons_nonce'] ) ), 'users_dlx_plus_buttons' ) ) {
		users_dlx_plus_save_options(
			array(
				// phpcs:disable WordPress.Security.NonceVerification.Missing -- verificado arriba.
				'users_dlx_plus_sso_button_skin'    => sanitize_key( wp_unslash( $_POST['users_dlx_plus_sso_button_skin'] ?? 'brand' ) ),
				'users_dlx_plus_sso_button_shape'   => sanitize_key( wp_unslash( $_POST['users_dlx_plus_sso_button_shape'] ?? 'rounded' ) ),
				'users_dlx_plus_sso_button_show'    => sanitize_key( wp_unslash( $_POST['users_dlx_plus_sso_button_show'] ?? 'icon-text' ) ),
				'users_dlx_plus_sso_button_text'    => sanitize_text_field( wp_unslash( $_POST['users_dlx_plus_sso_button_text'] ?? '' ) ),
				'users_dlx_plus_sso_button_columns' => absint( wp_unslash( $_POST['users_dlx_plus_sso_button_columns'] ?? 2 ) ),
				// phpcs:enable
			)
		);

		users_dlx_plus_notice( __( 'Buttons saved.', 'users-dlx-plus' ) );
	}

	users_dlx_plus_screen_open( __( 'Social login', 'users-dlx-plus' ), 'users-dlx-plus-social', $tabs, $current );

	if ( 'general' === $current ) {
		users_dlx_plus_screen_social_general();
		users_dlx_plus_screen_close();
		return;
	}

	if ( 'buttons' === $current ) {
		users_dlx_plus_screen_social_buttons( $providers );
		users_dlx_plus_screen_close();
		return;
	}

	users_dlx_plus_intro( __( 'One app per network: create it in the provider’s developer console, paste the client ID and the secret, and copy the redirect URL that each card shows. Then run the live test — a provider cannot be enabled until the round trip actually works.', 'users-dlx-plus' ) );
	?>
	<div class="users-dlx-plus-cards">
		<?php
		foreach ( $providers as $slug => $provider ) :
			$state = users_dlx_plus_sso_state( $slug );
			?>
			<div class="users-dlx-plus-card">
				<div class="users-dlx-plus-card__top" style="background: <?php echo esc_attr( $provider['color'] ); ?>">
					<span class="users-dlx-plus-card__mark"><?php echo esc_html( mb_substr( $provider['name'], 0, 1 ) ); ?></span>
					<span class="users-dlx-plus-card__name"><?php echo esc_html( $provider['name'] ); ?></span>
				</div>
				<div class="users-dlx-plus-card__foot">
					<?php users_dlx_plus_sso_state_pill( $state ); ?>

					<span class="users-dlx-plus-card__acciones">
						<?php if ( 'enabled' === $state || 'disabled' === $state ) : ?>
							<a class="button button-small"
								<?php
								$users_dlx_plus_toggle = users_dlx_plus_admin_url(
									'users-dlx-plus-social',
									array(
										'red' => $slug,
										'users_dlx_plus_action' => 'enabled' === $state ? 'off' : 'on',
									)
								);
								?>
								href="<?php echo esc_url( wp_nonce_url( $users_dlx_plus_toggle, 'users_dlx_plus_social_toggle' ) ); ?>">
								<?php echo 'enabled' === $state ? esc_html__( 'Disable', 'users-dlx-plus' ) : esc_html__( 'Enable', 'users-dlx-plus' ); ?>
							</a>
						<?php endif; ?>

						<a class="button button-small button-primary" href="<?php echo esc_url( users_dlx_plus_admin_url( 'users-dlx-plus-social', array( 'provider' => $slug ) ) ); ?>">
							<?php echo 'not-configured' === $state ? esc_html__( 'Get started', 'users-dlx-plus' ) : esc_html__( 'Settings', 'users-dlx-plus' ); ?>
						</a>
					</span>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<h2><?php esc_html_e( 'Not included, on purpose', 'users-dlx-plus' ); ?></h2>
	<p class="users-dlx-plus-admin__intro">
		<?php esc_html_e( 'Apple signs its client secret with a JWT that has to be regenerated every six months and answers by POST; Steam does not use OAuth 2 at all and never returns an email address. Both need their own flow, so they are not here yet: a button that does not work is worse than no button.', 'users-dlx-plus' ); ?>
	</p>
	<?php
	users_dlx_plus_screen_close();
}

/** La pastilla de estado de un proveedor. */
function users_dlx_plus_sso_state_pill( string $state ): void {
	$labels = array(
		'not-configured' => array( 'blank', __( 'Not set up', 'users-dlx-plus' ) ),
		'not-tested'     => array( 'blank', __( 'Needs testing', 'users-dlx-plus' ) ),
		'disabled'       => array( 'off', __( 'Disabled', 'users-dlx-plus' ) ),
		'enabled'        => array( 'on', __( 'Active', 'users-dlx-plus' ) ),
	);

	[ $tone, $label ] = $labels[ $state ] ?? $labels['not-configured'];

	printf( '<span class="users-dlx-plus-pill users-dlx-plus-pill--%1$s">%2$s</span>', esc_attr( $tone ), esc_html( $label ) );
}

/**
 * Cómo se ven los botones.
 *
 * La vista previa se pinta con el marcado y la hoja de verdad —no con una
 * imitación— y el script del admin le cambia las clases mientras se elige, así
 * que lo que se ve acá es lo que va a ver la gente.
 *
 * @param array<string, array<string, mixed>> $providers Todos los proveedores.
 */
function users_dlx_plus_screen_social_buttons( array $providers ): void {
	users_dlx_plus_intro( __( 'How the buttons look on the sign-in page. It is the same markup and the same stylesheet the site uses, so the preview is the real thing.', 'users-dlx-plus' ) );

	// Para la vista previa alcanza con unos pocos: la idea es ver el acabado,
	// no repasar la lista entera.
	$muestra = array_slice( $providers, 0, 4, true );
	?>
	<form method="post" class="users-dlx-plus-botones">
		<?php wp_nonce_field( 'users_dlx_plus_buttons', 'users_dlx_plus_buttons_nonce' ); ?>

		<div class="users-dlx-plus-botones__campos">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="users_dlx_plus_sso_button_skin"><?php esc_html_e( 'Finish', 'users-dlx-plus' ); ?></label></th>
					<td>
						<select id="users_dlx_plus_sso_button_skin" name="users_dlx_plus_sso_button_skin" data-users-dlx-plus-vista="skin">
							<?php foreach ( users_dlx_plus_sso_button_skins() as $clave => $rotulo ) : ?>
								<option value="<?php echo esc_attr( $clave ); ?>" <?php selected( users_dlx_plus_option( 'users_dlx_plus_sso_button_skin' ), $clave ); ?>><?php echo esc_html( $rotulo ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Google and Microsoft always stay white with their own logo: their brand guidelines ask for it, and a four-colour logo on a coloured background does not read.', 'users-dlx-plus' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="users_dlx_plus_sso_button_shape"><?php esc_html_e( 'Shape', 'users-dlx-plus' ); ?></label></th>
					<td>
						<select id="users_dlx_plus_sso_button_shape" name="users_dlx_plus_sso_button_shape" data-users-dlx-plus-vista="shape">
							<?php foreach ( users_dlx_plus_sso_button_shapes() as $clave => $rotulo ) : ?>
								<option value="<?php echo esc_attr( $clave ); ?>" <?php selected( users_dlx_plus_option( 'users_dlx_plus_sso_button_shape' ), $clave ); ?>><?php echo esc_html( $rotulo ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="users_dlx_plus_sso_button_show"><?php esc_html_e( 'What it shows', 'users-dlx-plus' ); ?></label></th>
					<td>
						<select id="users_dlx_plus_sso_button_show" name="users_dlx_plus_sso_button_show" data-users-dlx-plus-vista="show">
							<?php foreach ( users_dlx_plus_sso_button_contents() as $clave => $rotulo ) : ?>
								<option value="<?php echo esc_attr( $clave ); ?>" <?php selected( users_dlx_plus_option( 'users_dlx_plus_sso_button_show' ), $clave ); ?>><?php echo esc_html( $rotulo ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'With logo only, the name is still there for screen readers.', 'users-dlx-plus' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="users_dlx_plus_sso_button_columns"><?php esc_html_e( 'Layout', 'users-dlx-plus' ); ?></label></th>
					<td>
						<select id="users_dlx_plus_sso_button_columns" name="users_dlx_plus_sso_button_columns" data-users-dlx-plus-vista="cols">
							<?php foreach ( users_dlx_plus_sso_button_columns() as $clave => $rotulo ) : ?>
								<option value="<?php echo esc_attr( (string) $clave ); ?>" <?php selected( (int) users_dlx_plus_option( 'users_dlx_plus_sso_button_columns' ), (int) $clave ); ?>><?php echo esc_html( $rotulo ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="users_dlx_plus_sso_button_text"><?php esc_html_e( 'Text', 'users-dlx-plus' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="users_dlx_plus_sso_button_text" name="users_dlx_plus_sso_button_text"
							value="<?php echo esc_attr( (string) users_dlx_plus_option( 'users_dlx_plus_sso_button_text' ) ); ?>"
							<?php /* translators: %s: nombre de la red social */ ?>
							placeholder="<?php echo esc_attr( __( 'Continue with %s', 'users-dlx-plus' ) ); ?>">
						<p class="description">
							<?php
							printf(
								/* translators: %s: el marcador %s, literal */
								esc_html__( 'Where %s goes, the name of the network goes. Leave it empty to use the default, which is already translated.', 'users-dlx-plus' ),
								'<code>%s</code>'
							);
							?>
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</div>

		<div class="users-dlx-plus-botones__vista">
			<h2><?php esc_html_e( 'Preview', 'users-dlx-plus' ); ?></h2>

			<div class="users-dlx-plus-botones__fondos">
				<button type="button" class="users-dlx-plus-botones__fondo" data-users-dlx-plus-fondo="claro" aria-pressed="true"><?php esc_html_e( 'On white', 'users-dlx-plus' ); ?></button>
				<button type="button" class="users-dlx-plus-botones__fondo" data-users-dlx-plus-fondo="oscuro" aria-pressed="false"><?php esc_html_e( 'On dark', 'users-dlx-plus' ); ?></button>
			</div>

			<div class="users-dlx-plus-botones__lienzo">
				<?php echo users_dlx_plus_sso_buttons( $muestra, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio, ya escapado. ?>
			</div>
			<p class="description"><?php esc_html_e( 'These buttons do nothing: they are here to be looked at.', 'users-dlx-plus' ); ?></p>
		</div>
	</form>
	<?php
}

/** Los ajustes que valen para todas las redes. */
function users_dlx_plus_screen_social_general(): void {
	users_dlx_plus_intro( __( 'These apply to every provider. They decide what happens when someone comes back from a social network.', 'users-dlx-plus' ) );
	?>
	<form method="post">
		<?php wp_nonce_field( 'users_dlx_plus_social', 'users_dlx_plus_social_nonce' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Recognise people by email', 'users-dlx-plus' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="users_dlx_plus_sso_link_by_email" value="1" <?php checked( users_dlx_plus_option( 'users_dlx_plus_sso_link_by_email' ), 1 ); ?>>
						<?php esc_html_e( 'If the email already exists, link the network to that account', 'users-dlx-plus' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'This is what makes signing in with Google today and with GitHub tomorrow land on the same account instead of creating two. Each network the person uses gets added to their account, and any of them works from then on.', 'users-dlx-plus' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Turned off, someone whose email is already registered simply cannot get in with a social network. Only turn it off if you do not trust the provider to verify its own users’ email addresses.', 'users-dlx-plus' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Create accounts', 'users-dlx-plus' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="users_dlx_plus_sso_register" value="1" <?php checked( users_dlx_plus_option( 'users_dlx_plus_sso_register' ), 1 ); ?>>
						<?php esc_html_e( 'If the email does not exist yet, create the account', 'users-dlx-plus' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Turned off, only people who already have an account can use the social buttons.', 'users-dlx-plus' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Verified email only', 'users-dlx-plus' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="users_dlx_plus_sso_verified_only" value="1" <?php checked( users_dlx_plus_option( 'users_dlx_plus_sso_verified_only' ), 1 ); ?>>
						<?php esc_html_e( 'Refuse the sign-in when the provider does not say the email is verified', 'users-dlx-plus' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Stricter, and a few providers never send that flag: with this on, those stop working.', 'users-dlx-plus' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Roles that cannot use it', 'users-dlx-plus' ); ?></th>
				<td>
					<?php
					$blocked = (array) users_dlx_plus_option( 'users_dlx_plus_sso_blocked_roles' );

					foreach ( wp_roles()->get_names() as $role => $label ) :
						?>
						<label class="users-dlx-plus-roles__item">
							<input type="checkbox" name="users_dlx_plus_sso_blocked_roles[]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, $blocked, true ) ); ?>>
							<?php echo esc_html( translate_user_role( $label ) ); ?>
						</label>
					<?php endforeach; ?>
					<p class="description">
						<?php esc_html_e( 'People with one of these roles have to use the email link. It is the account with the most power that is worth protecting: an administrator who signs in with Google depends on that Google account never being taken over.', 'users-dlx-plus' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Nobody is locked out by this: the email link is always there.', 'users-dlx-plus' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>

	<div class="users-dlx-plus-donde">
		<p><strong><?php esc_html_e( 'How an account ends up, and how one person ends up with several networks', 'users-dlx-plus' ); ?></strong></p>
		<p>
			<?php
			printf(
				/* translators: 1: nombre del rol con el que se crean las cuentas, 2: nombre de la pantalla donde se elige */
				esc_html__( 'There is nothing to choose here, and that is on purpose: a new account gets the email address as its username, no password at all —not even one nobody knows how to use— and the role %1$s, which is set once for the whole site on the %2$s screen, because a role per provider would be a quiet way of handing out privileges. The name comes from the provider and only fills in what the person has not written themselves. Each linked network is stored on the person, so anybody can add a second and a third from their profile and unlink them again, and from then on any of them opens the same account.', 'users-dlx-plus' ),
				esc_html( translate_user_role( wp_roles()->get_names()[ (string) users_dlx_plus_option( 'users_dlx_plus_login_role' ) ] ?? (string) users_dlx_plus_option( 'users_dlx_plus_login_role' ) ) ),
				'«' . esc_html__( 'Registration and login', 'users-dlx-plus' ) . '»'
			);
			?>
		</p>
	</div>
	<?php
}

/** La ficha de un proveedor, con sus propias solapas. */
/**
 * @param array<string, mixed> $provider
 */
function users_dlx_plus_screen_provider( string $id, array $provider ): void {
	$tabs = array(
		'start'    => __( 'Getting started', 'users-dlx-plus' ),
		'settings' => __( 'Settings', 'users-dlx-plus' ),
		'usage'    => __( 'Usage', 'users-dlx-plus' ),
	);

	$current = users_dlx_plus_tab( $tabs );

	if ( isset( $_POST['users_dlx_plus_provider_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['users_dlx_plus_provider_nonce'] ) ), 'users_dlx_plus_provider' ) ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verificado arriba.
		users_dlx_plus_sso_save_credentials(
			$id,
			array(
				'active' => isset( $_POST['users_dlx_plus_active'] ) ? 1 : 0,
				'id'     => sanitize_text_field( wp_unslash( $_POST['users_dlx_plus_client_id'] ?? '' ) ),
				'secret' => sanitize_text_field( wp_unslash( $_POST['users_dlx_plus_client_secret'] ?? '' ) ),
			)
		);
		// phpcs:enable

		users_dlx_plus_notice( __( 'Provider saved.', 'users-dlx-plus' ) );
	}

	$credentials = users_dlx_plus_sso_credentials( $id );
	$state       = users_dlx_plus_sso_state( $id );

	users_dlx_plus_screen_open( $provider['name'], 'users-dlx-plus-social', $tabs, $current, array( 'provider' => $id ) );
	?>
	<p><a href="<?php echo esc_url( users_dlx_plus_admin_url( 'users-dlx-plus-social' ) ); ?>">&larr; <?php esc_html_e( 'Back to all providers', 'users-dlx-plus' ); ?></a></p>
	<?php

	users_dlx_plus_screen_provider_state( $id, $provider, $state );

	if ( 'settings' === $current ) {
		?>
		<form method="post">
			<?php wp_nonce_field( 'users_dlx_plus_provider', 'users_dlx_plus_provider_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="users_dlx_plus_client_id"><?php esc_html_e( 'Client ID', 'users-dlx-plus' ); ?></label></th>
					<td><input type="text" class="large-text code" id="users_dlx_plus_client_id" name="users_dlx_plus_client_id" value="<?php echo esc_attr( $credentials['id'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="users_dlx_plus_client_secret"><?php esc_html_e( 'Secret', 'users-dlx-plus' ); ?></label></th>
					<td><input type="password" class="large-text code" id="users_dlx_plus_client_secret" name="users_dlx_plus_client_secret" value="<?php echo esc_attr( $credentials['secret'] ); ?>" autocomplete="off"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'users-dlx-plus' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="users_dlx_plus_active" value="1" <?php checked( $credentials['active'] ); ?>>
							<?php esc_html_e( 'Show the button', 'users-dlx-plus' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	} elseif ( 'usage' === $current ) {
		users_dlx_plus_intro( __( 'The buttons are drawn by the sign-in shortcode, together with the email form. There is nothing else to place.', 'users-dlx-plus' ) );
		?>
		<table class="widefat striped users-dlx-plus-shortcodes">
			<tbody>
				<tr><td><code>[users_dlx_plus_login]</code></td><td><?php esc_html_e( 'The email sign-in form and the social buttons.', 'users-dlx-plus' ); ?></td></tr>
				<tr><td><code>[users_dlx_plus_accounts]</code></td><td><?php esc_html_e( 'Linked providers, to link or unlink.', 'users-dlx-plus' ); ?></td></tr>
			</tbody>
		</table>
		<p class="users-dlx-plus-admin__intro">
			<?php
			printf(
				/* translators: %s: nombre del proveedor */
				esc_html__( 'A direct link to sign in with %s, if you want it somewhere else:', 'users-dlx-plus' ),
				esc_html( $provider['name'] )
			);
			?>
		</p>
		<p><code><?php echo esc_html( users_dlx_plus_sso_login_url( $id ) ); ?></code></p>
		<?php
	} else {
		users_dlx_plus_screen_provider_start( $id, $provider );
	}

	users_dlx_plus_screen_close();
}

/** «Cómo empezar»: qué crear, dónde, y qué URL pegar. */
/**
 * @param array<string, mixed> $provider
 */
function users_dlx_plus_screen_provider_start( string $id, array $provider ): void {
	$guide = users_dlx_plus_sso_guide( $id );
	?>
	<h2><?php esc_html_e( 'Getting started', 'users-dlx-plus' ); ?></h2>
	<p class="users-dlx-plus-admin__intro">
		<?php
		printf(
			/* translators: %s: nombre del proveedor */
			esc_html__( 'To let people sign in with their %s account you have to create an app there. Below is the whole thing, click by click.', 'users-dlx-plus' ),
			esc_html( $provider['name'] )
		);
		?>
	</p>

	<div class="users-dlx-plus-url-retorno">
		<h3><?php esc_html_e( 'The URL they are going to ask you for', 'users-dlx-plus' ); ?></h3>
		<p><?php esc_html_e( 'Keep it at hand: one of the steps below asks for it, and it has to be pasted exactly as it is.', 'users-dlx-plus' ); ?></p>
		<input type="text" class="large-text code" readonly value="<?php echo esc_attr( users_dlx_plus_sso_redirect_uri( $id ) ); ?>" onclick="this.select();">
		<p class="description"><?php esc_html_e( 'Depending on the provider it is called redirect URI, callback URL, return URL or authorized redirect URL.', 'users-dlx-plus' ); ?></p>
	</div>

	<h3>
		<?php
		printf(
			/* translators: %s: nombre del proveedor */
			esc_html__( 'Step by step in %s', 'users-dlx-plus' ),
			esc_html( $provider['name'] )
		);
		?>
	</h3>

	<p>
		<a class="button" href="<?php echo esc_url( $provider['console'] ); ?>" target="_blank" rel="noopener">
			<?php esc_html_e( 'Open the console', 'users-dlx-plus' ); ?>
			<span class="dashicons dashicons-external" aria-hidden="true"></span>
		</a>
		<span class="description"><?php echo esc_html( $provider['console'] ); ?></span>
	</p>

	<ol class="users-dlx-plus-pasos users-dlx-plus-pasos--numeros">
		<?php foreach ( $guide['steps'] as $paso ) : ?>
			<li><?php echo esc_html( $paso ); ?></li>
		<?php endforeach; ?>
	</ol>

	<?php if ( '' !== $guide['gotcha'] ) : ?>
		<p class="users-dlx-plus-ojo">
			<strong><?php esc_html_e( 'Watch out:', 'users-dlx-plus' ); ?></strong>
			<?php echo esc_html( $guide['gotcha'] ); ?>
		</p>
	<?php endif; ?>

	<p class="description">
		<?php esc_html_e( 'The provider’s own documentation:', 'users-dlx-plus' ); ?>
		<a href="<?php echo esc_url( $provider['guide'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $provider['guide'] ); ?></a>
	</p>

	<h3><?php esc_html_e( 'And then, here', 'users-dlx-plus' ); ?></h3>
	<p><?php esc_html_e( 'Paste the client ID and the secret in Settings, run the live test, and turn the button on.', 'users-dlx-plus' ); ?></p>
	<p>
		<?php
		$users_dlx_plus_ajustes = users_dlx_plus_admin_url(
			'users-dlx-plus-social',
			array(
				'provider' => $id,
				'tab'      => 'settings',
			)
		);
		?>
		<a class="button button-primary" href="<?php echo esc_url( $users_dlx_plus_ajustes ); ?>">
			<?php esc_html_e( 'I already created the app', 'users-dlx-plus' ); ?>
		</a>
	</p>

	<h3><?php esc_html_e( 'What this provider asks for', 'users-dlx-plus' ); ?></h3>
	<table class="widefat striped users-dlx-plus-detalle">
		<tbody>
			<tr><th><?php esc_html_e( 'Permissions requested', 'users-dlx-plus' ); ?></th><td><code><?php echo esc_html( $provider['scope'] ); ?></code></td></tr>
			<tr><th><?php esc_html_e( 'Authorization URL', 'users-dlx-plus' ); ?></th><td><code><?php echo esc_html( $provider['authorize'] ); ?></code></td></tr>
			<?php if ( ! empty( $provider['pkce'] ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'PKCE', 'users-dlx-plus' ); ?></th>
					<td><?php esc_html_e( 'Required by this provider. The plugin handles it; nothing to configure.', 'users-dlx-plus' ); ?></td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>
	<?php
}

/**
 * El botón que abre la prueba en vivo.
 *
 * Es un enlace de verdad, con `target`: si el JavaScript del admin no cargó,
 * la prueba igual se abre en una pestaña. El tamaño de la ventana lo pone el
 * script leyendo `data-users-dlx-plus-popup`; un `onclick` en el marcado no serviría,
 * porque `wp_kses_post()` borra los manejadores de evento y el botón quedaba
 * sin hacer nada.
 */
function users_dlx_plus_sso_test_button( string $id, string $state ): void {
	?>
	<a class="button <?php echo 'not-tested' === $state ? 'button-primary' : 'button-secondary'; ?>"
		href="<?php echo esc_url( users_dlx_plus_sso_test_url( $id ) ); ?>"
		target="users-dlx-plus-test"
		data-users-dlx-plus-popup="600x740">
		<?php
		echo 'not-tested' === $state
			? esc_html__( 'Run the live test', 'users-dlx-plus' )
			: esc_html__( 'Test it again', 'users-dlx-plus' );
		?>
	</a>
	<?php
}

/**
 * La caja de estado de un proveedor, con la prueba en vivo.
 *
 * La prueba es el ida y vuelta de verdad contra el proveedor, en una ventana
 * aparte: es la única forma de saber que el ID, el secreto y la URL de retorno
 * están bien antes de que se entere la primera persona que no puede entrar.
 * Por eso no se puede prender un proveedor sin haberlo probado.
 *
 * @param array<string, mixed> $provider
 */
function users_dlx_plus_screen_provider_state( string $id, array $provider, string $state ): void {
	if ( 'not-configured' === $state ) {
		?>
		<div class="notice notice-info inline users-dlx-plus-estado-caja">
			<p><strong><?php esc_html_e( 'Nothing loaded yet', 'users-dlx-plus' ); ?></strong></p>
			<p><?php esc_html_e( 'Create the app, load the redirect URL and paste the client ID and the secret in Settings.', 'users-dlx-plus' ); ?></p>
		</div>
		<?php
		return;
	}

	if ( 'not-tested' === $state ) {
		?>
		<div class="notice notice-warning inline users-dlx-plus-estado-caja">
			<p><strong><?php esc_html_e( 'This needs to be tested', 'users-dlx-plus' ); ?></strong></p>
			<p>
				<?php
				printf(
					/* translators: %s: nombre del proveedor */
					esc_html__( 'A window opens, %s asks you to authorise, and it comes back here. Nobody is signed in and nothing is saved to your account — it only checks that the round trip works. Until it does, the button cannot be enabled.', 'users-dlx-plus' ),
					esc_html( $provider['name'] )
				);
				?>
			</p>
			<p><?php users_dlx_plus_sso_test_button( $id, $state ); ?></p>
		</div>
		<?php
		return;
	}
	?>
	<div class="notice notice-<?php echo 'enabled' === $state ? 'success' : 'info'; ?> inline users-dlx-plus-estado-caja">
		<p>
			<strong><?php esc_html_e( 'Tested and working', 'users-dlx-plus' ); ?></strong> —
			<?php
			echo 'enabled' === $state
				? esc_html__( 'the button is showing on the sign-in page.', 'users-dlx-plus' )
				: esc_html__( 'the button is not showing: it is disabled.', 'users-dlx-plus' );
			?>
		</p>
		<p>
			<?php users_dlx_plus_sso_test_button( $id, $state ); ?>
			<a class="button <?php echo 'enabled' === $state ? '' : 'button-primary'; ?>"
				<?php
				$users_dlx_plus_toggle = users_dlx_plus_admin_url(
					'users-dlx-plus-social',
					array(
						'red'                   => $id,
						'users_dlx_plus_action' => 'enabled' === $state ? 'off' : 'on',
					)
				);
				?>
				href="<?php echo esc_url( wp_nonce_url( $users_dlx_plus_toggle, 'users_dlx_plus_social_toggle' ) ); ?>">
				<?php echo 'enabled' === $state ? esc_html__( 'Disable', 'users-dlx-plus' ) : esc_html__( 'Enable', 'users-dlx-plus' ); ?>
			</a>
		</p>
	</div>
	<?php
}
