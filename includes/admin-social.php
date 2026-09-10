<?php
/**
 * La pantalla del login social: la grilla de proveedores y la ficha de cada uno.
 *
 * Está armada como la de Nextend a propósito: quien ya configuró redes en un
 * WordPress reconoce el camino —una tarjeta por red, y adentro «cómo empezar»,
 * los ajustes y el uso— y no tiene que aprender otro.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

/** Screen social. */
function upfw_screen_social(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$id        = isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : '';
	$providers = upfw_sso_providers();

	if ( '' !== $id && isset( $providers[ $id ] ) ) {
		upfw_screen_provider( $id, $providers[ $id ] );
		return;
	}

	// Activar o desactivar desde la grilla, sin entrar a la ficha.
	if ( isset( $_GET['upfw_action'], $_GET['red'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'upfw_social_toggle' );

		$red = sanitize_key( wp_unslash( $_GET['red'] ) );

		if ( isset( $providers[ $red ] ) && upfw_sso_tested( $red ) ) {
			$all = (array) get_option( 'upfw_sso', array() );

			$all[ $red ]['active'] = 'on' === sanitize_key( wp_unslash( $_GET['upfw_action'] ) ) ? 1 : 0;

			update_option( 'upfw_sso', $all );
		}

		wp_safe_redirect( upfw_admin_url( 'upfw-social' ) );
		exit;
	}

	$tabs = array(
		'providers' => __( 'Providers', 'users-plus-for-wordpress' ),
		'buttons'   => __( 'Buttons', 'users-plus-for-wordpress' ),
		'general'   => __( 'Global settings', 'users-plus-for-wordpress' ),
	);

	$current = upfw_tab( $tabs );

	if ( isset( $_POST['upfw_social_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['upfw_social_nonce'] ) ), 'upfw_social' ) ) {
		upfw_save_options(
			array(
				// phpcs:disable WordPress.Security.NonceVerification.Missing -- verificado arriba.
				'upfw_sso_link_by_email' => isset( $_POST['upfw_sso_link_by_email'] ) ? 1 : 0,
				'upfw_sso_register'      => isset( $_POST['upfw_sso_register'] ) ? 1 : 0,
				'upfw_sso_verified_only' => isset( $_POST['upfw_sso_verified_only'] ) ? 1 : 0,
				'upfw_sso_blocked_roles' => array_map( 'sanitize_key', (array) wp_unslash( $_POST['upfw_sso_blocked_roles'] ?? array() ) ),
				// phpcs:enable
			)
		);

		upfw_notice( __( 'Settings saved.', 'users-plus-for-wordpress' ) );
	}

	if ( isset( $_POST['upfw_buttons_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['upfw_buttons_nonce'] ) ), 'upfw_buttons' ) ) {
		upfw_save_options(
			array(
				// phpcs:disable WordPress.Security.NonceVerification.Missing -- verificado arriba.
				'upfw_sso_button_skin'    => sanitize_key( wp_unslash( $_POST['upfw_sso_button_skin'] ?? 'brand' ) ),
				'upfw_sso_button_shape'   => sanitize_key( wp_unslash( $_POST['upfw_sso_button_shape'] ?? 'rounded' ) ),
				'upfw_sso_button_show'    => sanitize_key( wp_unslash( $_POST['upfw_sso_button_show'] ?? 'icon-text' ) ),
				'upfw_sso_button_text'    => sanitize_text_field( wp_unslash( $_POST['upfw_sso_button_text'] ?? '' ) ),
				'upfw_sso_button_columns' => absint( wp_unslash( $_POST['upfw_sso_button_columns'] ?? 2 ) ),
				// phpcs:enable
			)
		);

		upfw_notice( __( 'Buttons saved.', 'users-plus-for-wordpress' ) );
	}

	upfw_screen_open( __( 'Social login', 'users-plus-for-wordpress' ), 'upfw-social', $tabs, $current );

	if ( 'general' === $current ) {
		upfw_screen_social_general();
		upfw_screen_close();
		return;
	}

	if ( 'buttons' === $current ) {
		upfw_screen_social_buttons( $providers );
		upfw_screen_close();
		return;
	}

	upfw_intro( __( 'One app per network: create it in the provider’s developer console, paste the client ID and the secret, and copy the redirect URL that each card shows. Then run the live test — a provider cannot be enabled until the round trip actually works.', 'users-plus-for-wordpress' ) );
	?>
	<div class="upfw-cards">
		<?php
		foreach ( $providers as $slug => $provider ) :
			$state = upfw_sso_state( $slug );
			?>
			<div class="upfw-card">
				<div class="upfw-card__top" style="background: <?php echo esc_attr( $provider['color'] ); ?>">
					<span class="upfw-card__mark"><?php echo esc_html( mb_substr( $provider['name'], 0, 1 ) ); ?></span>
					<span class="upfw-card__name"><?php echo esc_html( $provider['name'] ); ?></span>
				</div>
				<div class="upfw-card__foot">
					<?php upfw_sso_state_pill( $state ); ?>

					<span class="upfw-card__acciones">
						<?php if ( 'enabled' === $state || 'disabled' === $state ) : ?>
							<a class="button button-small"
								<?php
								$upfw_toggle = upfw_admin_url(
									'upfw-social',
									array(
										'red'         => $slug,
										'upfw_action' => 'enabled' === $state ? 'off' : 'on',
									)
								);
								?>
								href="<?php echo esc_url( wp_nonce_url( $upfw_toggle, 'upfw_social_toggle' ) ); ?>">
								<?php echo 'enabled' === $state ? esc_html__( 'Disable', 'users-plus-for-wordpress' ) : esc_html__( 'Enable', 'users-plus-for-wordpress' ); ?>
							</a>
						<?php endif; ?>

						<a class="button button-small button-primary" href="<?php echo esc_url( upfw_admin_url( 'upfw-social', array( 'provider' => $slug ) ) ); ?>">
							<?php echo 'not-configured' === $state ? esc_html__( 'Get started', 'users-plus-for-wordpress' ) : esc_html__( 'Settings', 'users-plus-for-wordpress' ); ?>
						</a>
					</span>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<h2><?php esc_html_e( 'Not included, on purpose', 'users-plus-for-wordpress' ); ?></h2>
	<p class="upfw-admin__intro">
		<?php esc_html_e( 'Apple signs its client secret with a JWT that has to be regenerated every six months and answers by POST; Steam does not use OAuth 2 at all and never returns an email address. Both need their own flow, so they are not here yet: a button that does not work is worse than no button.', 'users-plus-for-wordpress' ); ?>
	</p>
	<?php
	upfw_screen_close();
}

/** La pastilla de estado de un proveedor. */
function upfw_sso_state_pill( string $state ): void {
	$labels = array(
		'not-configured' => array( 'blank', __( 'Not set up', 'users-plus-for-wordpress' ) ),
		'not-tested'     => array( 'blank', __( 'Needs testing', 'users-plus-for-wordpress' ) ),
		'disabled'       => array( 'off', __( 'Disabled', 'users-plus-for-wordpress' ) ),
		'enabled'        => array( 'on', __( 'Active', 'users-plus-for-wordpress' ) ),
	);

	[ $tone, $label ] = $labels[ $state ] ?? $labels['not-configured'];

	printf( '<span class="upfw-pill upfw-pill--%1$s">%2$s</span>', esc_attr( $tone ), esc_html( $label ) );
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
function upfw_screen_social_buttons( array $providers ): void {
	upfw_intro( __( 'How the buttons look on the sign-in page. It is the same markup and the same stylesheet the site uses, so the preview is the real thing.', 'users-plus-for-wordpress' ) );

	// Para la vista previa alcanza con unos pocos: la idea es ver el acabado,
	// no repasar la lista entera.
	$muestra = array_slice( $providers, 0, 4, true );
	?>
	<form method="post" class="upfw-botones">
		<?php wp_nonce_field( 'upfw_buttons', 'upfw_buttons_nonce' ); ?>

		<div class="upfw-botones__campos">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="upfw_sso_button_skin"><?php esc_html_e( 'Finish', 'users-plus-for-wordpress' ); ?></label></th>
					<td>
						<select id="upfw_sso_button_skin" name="upfw_sso_button_skin" data-upfw-vista="skin">
							<?php foreach ( upfw_sso_button_skins() as $clave => $rotulo ) : ?>
								<option value="<?php echo esc_attr( $clave ); ?>" <?php selected( upfw_option( 'upfw_sso_button_skin' ), $clave ); ?>><?php echo esc_html( $rotulo ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Google and Microsoft always stay white with their own logo: their brand guidelines ask for it, and a four-colour logo on a coloured background does not read.', 'users-plus-for-wordpress' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="upfw_sso_button_shape"><?php esc_html_e( 'Shape', 'users-plus-for-wordpress' ); ?></label></th>
					<td>
						<select id="upfw_sso_button_shape" name="upfw_sso_button_shape" data-upfw-vista="shape">
							<?php foreach ( upfw_sso_button_shapes() as $clave => $rotulo ) : ?>
								<option value="<?php echo esc_attr( $clave ); ?>" <?php selected( upfw_option( 'upfw_sso_button_shape' ), $clave ); ?>><?php echo esc_html( $rotulo ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="upfw_sso_button_show"><?php esc_html_e( 'What it shows', 'users-plus-for-wordpress' ); ?></label></th>
					<td>
						<select id="upfw_sso_button_show" name="upfw_sso_button_show" data-upfw-vista="show">
							<?php foreach ( upfw_sso_button_contents() as $clave => $rotulo ) : ?>
								<option value="<?php echo esc_attr( $clave ); ?>" <?php selected( upfw_option( 'upfw_sso_button_show' ), $clave ); ?>><?php echo esc_html( $rotulo ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'With logo only, the name is still there for screen readers.', 'users-plus-for-wordpress' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="upfw_sso_button_columns"><?php esc_html_e( 'Layout', 'users-plus-for-wordpress' ); ?></label></th>
					<td>
						<select id="upfw_sso_button_columns" name="upfw_sso_button_columns" data-upfw-vista="cols">
							<?php foreach ( upfw_sso_button_columns() as $clave => $rotulo ) : ?>
								<option value="<?php echo esc_attr( (string) $clave ); ?>" <?php selected( (int) upfw_option( 'upfw_sso_button_columns' ), (int) $clave ); ?>><?php echo esc_html( $rotulo ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="upfw_sso_button_text"><?php esc_html_e( 'Text', 'users-plus-for-wordpress' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="upfw_sso_button_text" name="upfw_sso_button_text"
							value="<?php echo esc_attr( (string) upfw_option( 'upfw_sso_button_text' ) ); ?>"
							<?php /* translators: %s: nombre de la red social */ ?>
							placeholder="<?php echo esc_attr( __( 'Continue with %s', 'users-plus-for-wordpress' ) ); ?>">
						<p class="description">
							<?php
							printf(
								/* translators: %s: el marcador %s, literal */
								esc_html__( 'Where %s goes, the name of the network goes. Leave it empty to use the default, which is already translated.', 'users-plus-for-wordpress' ),
								'<code>%s</code>'
							);
							?>
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</div>

		<div class="upfw-botones__vista">
			<h2><?php esc_html_e( 'Preview', 'users-plus-for-wordpress' ); ?></h2>

			<div class="upfw-botones__fondos">
				<button type="button" class="upfw-botones__fondo" data-upfw-fondo="claro" aria-pressed="true"><?php esc_html_e( 'On white', 'users-plus-for-wordpress' ); ?></button>
				<button type="button" class="upfw-botones__fondo" data-upfw-fondo="oscuro" aria-pressed="false"><?php esc_html_e( 'On dark', 'users-plus-for-wordpress' ); ?></button>
			</div>

			<div class="upfw-botones__lienzo">
				<?php echo upfw_sso_buttons( $muestra, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio, ya escapado. ?>
			</div>
			<p class="description"><?php esc_html_e( 'These buttons do nothing: they are here to be looked at.', 'users-plus-for-wordpress' ); ?></p>
		</div>
	</form>
	<?php
}

/** Los ajustes que valen para todas las redes. */
function upfw_screen_social_general(): void {
	upfw_intro( __( 'These apply to every provider. They decide what happens when someone comes back from a social network.', 'users-plus-for-wordpress' ) );
	?>
	<form method="post">
		<?php wp_nonce_field( 'upfw_social', 'upfw_social_nonce' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Recognise people by email', 'users-plus-for-wordpress' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="upfw_sso_link_by_email" value="1" <?php checked( upfw_option( 'upfw_sso_link_by_email' ), 1 ); ?>>
						<?php esc_html_e( 'If the email already exists, link the network to that account', 'users-plus-for-wordpress' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'This is what makes signing in with Google today and with GitHub tomorrow land on the same account instead of creating two. Each network the person uses gets added to their account, and any of them works from then on.', 'users-plus-for-wordpress' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Turned off, someone whose email is already registered simply cannot get in with a social network. Only turn it off if you do not trust the provider to verify its own users’ email addresses.', 'users-plus-for-wordpress' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Create accounts', 'users-plus-for-wordpress' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="upfw_sso_register" value="1" <?php checked( upfw_option( 'upfw_sso_register' ), 1 ); ?>>
						<?php esc_html_e( 'If the email does not exist yet, create the account', 'users-plus-for-wordpress' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Turned off, only people who already have an account can use the social buttons.', 'users-plus-for-wordpress' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Verified email only', 'users-plus-for-wordpress' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="upfw_sso_verified_only" value="1" <?php checked( upfw_option( 'upfw_sso_verified_only' ), 1 ); ?>>
						<?php esc_html_e( 'Refuse the sign-in when the provider does not say the email is verified', 'users-plus-for-wordpress' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Stricter, and a few providers never send that flag: with this on, those stop working.', 'users-plus-for-wordpress' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Roles that cannot use it', 'users-plus-for-wordpress' ); ?></th>
				<td>
					<?php
					$blocked = (array) upfw_option( 'upfw_sso_blocked_roles' );

					foreach ( wp_roles()->get_names() as $role => $label ) :
						?>
						<label class="upfw-roles__item">
							<input type="checkbox" name="upfw_sso_blocked_roles[]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, $blocked, true ) ); ?>>
							<?php echo esc_html( translate_user_role( $label ) ); ?>
						</label>
					<?php endforeach; ?>
					<p class="description">
						<?php esc_html_e( 'People with one of these roles have to use the email link. It is the account with the most power that is worth protecting: an administrator who signs in with Google depends on that Google account never being taken over.', 'users-plus-for-wordpress' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Nobody is locked out by this: the email link is always there.', 'users-plus-for-wordpress' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>

	<div class="upfw-donde">
		<p><strong><?php esc_html_e( 'How an account ends up, and how one person ends up with several networks', 'users-plus-for-wordpress' ); ?></strong></p>
		<p>
			<?php
			printf(
				/* translators: 1: nombre del rol con el que se crean las cuentas, 2: nombre de la pantalla donde se elige */
				esc_html__( 'There is nothing to choose here, and that is on purpose: a new account gets the email address as its username, no password at all —not even one nobody knows how to use— and the role %1$s, which is set once for the whole site on the %2$s screen, because a role per provider would be a quiet way of handing out privileges. The name comes from the provider and only fills in what the person has not written themselves. Each linked network is stored on the person, so anybody can add a second and a third from their profile and unlink them again, and from then on any of them opens the same account.', 'users-plus-for-wordpress' ),
				esc_html( translate_user_role( wp_roles()->get_names()[ (string) upfw_option( 'upfw_login_role' ) ] ?? (string) upfw_option( 'upfw_login_role' ) ) ),
				'«' . esc_html__( 'Registration and login', 'users-plus-for-wordpress' ) . '»'
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
function upfw_screen_provider( string $id, array $provider ): void {
	$tabs = array(
		'start'    => __( 'Getting started', 'users-plus-for-wordpress' ),
		'settings' => __( 'Settings', 'users-plus-for-wordpress' ),
		'usage'    => __( 'Usage', 'users-plus-for-wordpress' ),
	);

	$current = upfw_tab( $tabs );

	if ( isset( $_POST['upfw_provider_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['upfw_provider_nonce'] ) ), 'upfw_provider' ) ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verificado arriba.
		upfw_sso_save_credentials(
			$id,
			array(
				'active' => isset( $_POST['upfw_active'] ) ? 1 : 0,
				'id'     => sanitize_text_field( wp_unslash( $_POST['upfw_client_id'] ?? '' ) ),
				'secret' => sanitize_text_field( wp_unslash( $_POST['upfw_client_secret'] ?? '' ) ),
			)
		);
		// phpcs:enable

		upfw_notice( __( 'Provider saved.', 'users-plus-for-wordpress' ) );
	}

	$credentials = upfw_sso_credentials( $id );
	$state       = upfw_sso_state( $id );

	upfw_screen_open( $provider['name'], 'upfw-social', $tabs, $current, array( 'provider' => $id ) );
	?>
	<p><a href="<?php echo esc_url( upfw_admin_url( 'upfw-social' ) ); ?>">&larr; <?php esc_html_e( 'Back to all providers', 'users-plus-for-wordpress' ); ?></a></p>
	<?php

	upfw_screen_provider_state( $id, $provider, $state );

	if ( 'settings' === $current ) {
		?>
		<form method="post">
			<?php wp_nonce_field( 'upfw_provider', 'upfw_provider_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="upfw_client_id"><?php esc_html_e( 'Client ID', 'users-plus-for-wordpress' ); ?></label></th>
					<td><input type="text" class="large-text code" id="upfw_client_id" name="upfw_client_id" value="<?php echo esc_attr( $credentials['id'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="upfw_client_secret"><?php esc_html_e( 'Secret', 'users-plus-for-wordpress' ); ?></label></th>
					<td><input type="password" class="large-text code" id="upfw_client_secret" name="upfw_client_secret" value="<?php echo esc_attr( $credentials['secret'] ); ?>" autocomplete="off"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'users-plus-for-wordpress' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="upfw_active" value="1" <?php checked( $credentials['active'] ); ?>>
							<?php esc_html_e( 'Show the button', 'users-plus-for-wordpress' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	} elseif ( 'usage' === $current ) {
		upfw_intro( __( 'The buttons are drawn by the sign-in shortcode, together with the email form. There is nothing else to place.', 'users-plus-for-wordpress' ) );
		?>
		<table class="widefat striped upfw-shortcodes">
			<tbody>
				<tr><td><code>[upfw_login]</code></td><td><?php esc_html_e( 'The email sign-in form and the social buttons.', 'users-plus-for-wordpress' ); ?></td></tr>
				<tr><td><code>[upfw_accounts]</code></td><td><?php esc_html_e( 'Linked providers, to link or unlink.', 'users-plus-for-wordpress' ); ?></td></tr>
			</tbody>
		</table>
		<p class="upfw-admin__intro">
			<?php
			printf(
				/* translators: %s: nombre del proveedor */
				esc_html__( 'A direct link to sign in with %s, if you want it somewhere else:', 'users-plus-for-wordpress' ),
				esc_html( $provider['name'] )
			);
			?>
		</p>
		<p><code><?php echo esc_html( upfw_sso_login_url( $id ) ); ?></code></p>
		<?php
	} else {
		upfw_screen_provider_start( $id, $provider );
	}

	upfw_screen_close();
}

/** «Cómo empezar»: qué crear, dónde, y qué URL pegar. */
/**
 * @param array<string, mixed> $provider
 */
function upfw_screen_provider_start( string $id, array $provider ): void {
	$guide = upfw_sso_guide( $id );
	?>
	<h2><?php esc_html_e( 'Getting started', 'users-plus-for-wordpress' ); ?></h2>
	<p class="upfw-admin__intro">
		<?php
		printf(
			/* translators: %s: nombre del proveedor */
			esc_html__( 'To let people sign in with their %s account you have to create an app there. Below is the whole thing, click by click.', 'users-plus-for-wordpress' ),
			esc_html( $provider['name'] )
		);
		?>
	</p>

	<div class="upfw-url-retorno">
		<h3><?php esc_html_e( 'The URL they are going to ask you for', 'users-plus-for-wordpress' ); ?></h3>
		<p><?php esc_html_e( 'Keep it at hand: one of the steps below asks for it, and it has to be pasted exactly as it is.', 'users-plus-for-wordpress' ); ?></p>
		<input type="text" class="large-text code" readonly value="<?php echo esc_attr( upfw_sso_redirect_uri( $id ) ); ?>" onclick="this.select();">
		<p class="description"><?php esc_html_e( 'Depending on the provider it is called redirect URI, callback URL, return URL or authorized redirect URL.', 'users-plus-for-wordpress' ); ?></p>
	</div>

	<h3>
		<?php
		printf(
			/* translators: %s: nombre del proveedor */
			esc_html__( 'Step by step in %s', 'users-plus-for-wordpress' ),
			esc_html( $provider['name'] )
		);
		?>
	</h3>

	<p>
		<a class="button" href="<?php echo esc_url( $provider['console'] ); ?>" target="_blank" rel="noopener">
			<?php esc_html_e( 'Open the console', 'users-plus-for-wordpress' ); ?>
			<span class="dashicons dashicons-external" aria-hidden="true"></span>
		</a>
		<span class="description"><?php echo esc_html( $provider['console'] ); ?></span>
	</p>

	<ol class="upfw-pasos upfw-pasos--numeros">
		<?php foreach ( $guide['steps'] as $paso ) : ?>
			<li><?php echo esc_html( $paso ); ?></li>
		<?php endforeach; ?>
	</ol>

	<?php if ( '' !== $guide['gotcha'] ) : ?>
		<p class="upfw-ojo">
			<strong><?php esc_html_e( 'Watch out:', 'users-plus-for-wordpress' ); ?></strong>
			<?php echo esc_html( $guide['gotcha'] ); ?>
		</p>
	<?php endif; ?>

	<p class="description">
		<?php esc_html_e( 'The provider’s own documentation:', 'users-plus-for-wordpress' ); ?>
		<a href="<?php echo esc_url( $provider['guide'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $provider['guide'] ); ?></a>
	</p>

	<h3><?php esc_html_e( 'And then, here', 'users-plus-for-wordpress' ); ?></h3>
	<p><?php esc_html_e( 'Paste the client ID and the secret in Settings, run the live test, and turn the button on.', 'users-plus-for-wordpress' ); ?></p>
	<p>
		<?php
		$upfw_ajustes = upfw_admin_url(
			'upfw-social',
			array(
				'provider' => $id,
				'tab'      => 'settings',
			)
		);
		?>
		<a class="button button-primary" href="<?php echo esc_url( $upfw_ajustes ); ?>">
			<?php esc_html_e( 'I already created the app', 'users-plus-for-wordpress' ); ?>
		</a>
	</p>

	<h3><?php esc_html_e( 'What this provider asks for', 'users-plus-for-wordpress' ); ?></h3>
	<table class="widefat striped upfw-detalle">
		<tbody>
			<tr><th><?php esc_html_e( 'Permissions requested', 'users-plus-for-wordpress' ); ?></th><td><code><?php echo esc_html( $provider['scope'] ); ?></code></td></tr>
			<tr><th><?php esc_html_e( 'Authorization URL', 'users-plus-for-wordpress' ); ?></th><td><code><?php echo esc_html( $provider['authorize'] ); ?></code></td></tr>
			<?php if ( ! empty( $provider['pkce'] ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'PKCE', 'users-plus-for-wordpress' ); ?></th>
					<td><?php esc_html_e( 'Required by this provider. The plugin handles it; nothing to configure.', 'users-plus-for-wordpress' ); ?></td>
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
 * script leyendo `data-upfw-popup`; un `onclick` en el marcado no serviría,
 * porque `wp_kses_post()` borra los manejadores de evento y el botón quedaba
 * sin hacer nada.
 */
function upfw_sso_test_button( string $id, string $state ): void {
	?>
	<a class="button <?php echo 'not-tested' === $state ? 'button-primary' : 'button-secondary'; ?>"
		href="<?php echo esc_url( upfw_sso_test_url( $id ) ); ?>"
		target="upfw-test"
		data-upfw-popup="600x740">
		<?php
		echo 'not-tested' === $state
			? esc_html__( 'Run the live test', 'users-plus-for-wordpress' )
			: esc_html__( 'Test it again', 'users-plus-for-wordpress' );
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
function upfw_screen_provider_state( string $id, array $provider, string $state ): void {
	if ( 'not-configured' === $state ) {
		?>
		<div class="notice notice-info inline upfw-estado-caja">
			<p><strong><?php esc_html_e( 'Nothing loaded yet', 'users-plus-for-wordpress' ); ?></strong></p>
			<p><?php esc_html_e( 'Create the app, load the redirect URL and paste the client ID and the secret in Settings.', 'users-plus-for-wordpress' ); ?></p>
		</div>
		<?php
		return;
	}

	if ( 'not-tested' === $state ) {
		?>
		<div class="notice notice-warning inline upfw-estado-caja">
			<p><strong><?php esc_html_e( 'This needs to be tested', 'users-plus-for-wordpress' ); ?></strong></p>
			<p>
				<?php
				printf(
					/* translators: %s: nombre del proveedor */
					esc_html__( 'A window opens, %s asks you to authorise, and it comes back here. Nobody is signed in and nothing is saved to your account — it only checks that the round trip works. Until it does, the button cannot be enabled.', 'users-plus-for-wordpress' ),
					esc_html( $provider['name'] )
				);
				?>
			</p>
			<p><?php upfw_sso_test_button( $id, $state ); ?></p>
		</div>
		<?php
		return;
	}
	?>
	<div class="notice notice-<?php echo 'enabled' === $state ? 'success' : 'info'; ?> inline upfw-estado-caja">
		<p>
			<strong><?php esc_html_e( 'Tested and working', 'users-plus-for-wordpress' ); ?></strong> —
			<?php
			echo 'enabled' === $state
				? esc_html__( 'the button is showing on the sign-in page.', 'users-plus-for-wordpress' )
				: esc_html__( 'the button is not showing: it is disabled.', 'users-plus-for-wordpress' );
			?>
		</p>
		<p>
			<?php upfw_sso_test_button( $id, $state ); ?>
			<a class="button <?php echo 'enabled' === $state ? '' : 'button-primary'; ?>"
				<?php
				$upfw_toggle = upfw_admin_url(
					'upfw-social',
					array(
						'red'         => $id,
						'upfw_action' => 'enabled' === $state ? 'off' : 'on',
					)
				);
				?>
				href="<?php echo esc_url( wp_nonce_url( $upfw_toggle, 'upfw_social_toggle' ) ); ?>">
				<?php echo 'enabled' === $state ? esc_html__( 'Disable', 'users-plus-for-wordpress' ) : esc_html__( 'Enable', 'users-plus-for-wordpress' ); ?>
			</a>
		</p>
	</div>
	<?php
}
