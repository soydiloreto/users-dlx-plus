<?php
/**
 * La portada del plugin: en qué estado está todo esto ahora.
 *
 * No es una pantalla de bienvenida con un texto fijo. Son los números y el
 * estado reales: cuánta gente hay, cuánta está adentro, qué se le pide, cómo
 * se entra y qué falta configurar. Lo que uno querría saber antes de tocar
 * cualquier otra pantalla.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

/**
 * Los números de la portada.
 *
 * Cuántas cuentas hay se cuenta con un COUNT(*) sobre la tabla de usuarios y
 * no con count_users(), que además agrupa por rol recorriendo la tabla de
 * metadatos: con veinticinco mil cuentas eso se llevaba cinco segundos y acá
 * el número por rol no se usa.
 *
 * Igual se cachea: son dos consultas que no cambian de un minuto a otro.
 *
 * @return array<string, int>
 */
function upfw_home_numbers(): array {
	$cached = get_transient( 'upfw_home_numbers' );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	global $wpdb;

	$numbers = array(
		'users'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" ),
		'sessions' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'session_tokens'" ),
	);

	set_transient( 'upfw_home_numbers', $numbers, 15 * MINUTE_IN_SECONDS );

	return $numbers;
}

/** Una baldosa con un número y su rótulo. */
function upfw_tile( string $value, string $label, string $link = '', string $link_label = '' ): void {
	?>
	<div class="upfw-tile">
		<b><?php echo esc_html( $value ); ?></b>
		<span><?php echo esc_html( $label ); ?></span>
		<?php if ( '' !== $link ) : ?>
			<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $link_label ); ?> &rarr;</a>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Una fila de estado: qué es, cómo está y qué hacer.
 *
 * El estado tiene tres valores y no dos. «A confirmar» existe porque hay cosas
 * que desde acá no se pueden saber —si el correo sale de verdad, por ejemplo—
 * y decir «listo» sin estar seguro es peor que no decir nada.
 *
 * @param string $state ok | pending | unknown
 */
function upfw_status_row( string $what, string $state, string $detail, string $link = '', string $link_label = '' ): void {
	$pills = array(
		'ok'      => array( 'on', __( 'Ready', 'users-plus-for-wordpress' ) ),
		'pending' => array( 'blank', __( 'Pending', 'users-plus-for-wordpress' ) ),
		'unknown' => array( 'off', __( 'Cannot tell', 'users-plus-for-wordpress' ) ),
	);

	[ $tone, $label ] = $pills[ $state ] ?? $pills['unknown'];
	?>
	<tr>
		<th scope="row"><?php echo esc_html( $what ); ?></th>
		<td>
			<span class="upfw-pill upfw-pill--<?php echo esc_attr( $tone ); ?>"><?php echo esc_html( $label ); ?></span>
			<?php echo esc_html( $detail ); ?>
			<?php if ( '' !== $link ) : ?>
				<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $link_label ); ?></a>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

function upfw_screen_home(): void {
	$numbers   = upfw_home_numbers();
	$fields    = upfw_fields( '', false );
	$active    = array_filter( $fields, static fn( array $f ): bool => (bool) $f['active'] );
	$providers = upfw_sso_providers();
	$ready     = upfw_sso_available();
	$setup     = array_filter( array_keys( $providers ), 'upfw_sso_configured' );
	$page      = (int) upfw_option( 'upfw_login_page' );

	upfw_screen_open( upfw_screens()[ UPFW_MENU ] );

	upfw_intro( __( 'Who is in this site, what is asked of them and how they get in.', 'users-plus-for-wordpress' ) );
	?>

	<div class="upfw-tiles">
		<?php
		upfw_tile(
			number_format_i18n( $numbers['users'] ),
			__( 'accounts', 'users-plus-for-wordpress' ),
			admin_url( 'users.php' ),
			__( 'All users', 'users-plus-for-wordpress' )
		);

		upfw_tile(
			number_format_i18n( $numbers['sessions'] ),
			__( 'with an open session', 'users-plus-for-wordpress' ),
			upfw_admin_url( 'upfw-sessions' ),
			__( 'See who', 'users-plus-for-wordpress' )
		);

		upfw_tile(
			number_format_i18n( count( $active ) ) . ' / ' . number_format_i18n( count( $fields ) ),
			__( 'fields in use', 'users-plus-for-wordpress' ),
			upfw_admin_url( 'upfw-fields' ),
			__( 'Manage them', 'users-plus-for-wordpress' )
		);

		upfw_tile(
			number_format_i18n( count( $ready ) ) . ' / ' . number_format_i18n( count( $providers ) ),
			__( 'social providers on', 'users-plus-for-wordpress' ),
			upfw_admin_url( 'upfw-social' ),
			__( 'Set them up', 'users-plus-for-wordpress' )
		);
		?>
	</div>

	<h2><?php esc_html_e( 'How people get in', 'users-plus-for-wordpress' ); ?></h2>
	<table class="widefat striped upfw-estado">
		<tbody>
			<?php
			upfw_status_row(
				__( 'Sign-in page', 'users-plus-for-wordpress' ),
				$page > 0 ? 'ok' : 'pending',
				$page > 0
					? (string) get_the_title( $page )
					: __( 'Not chosen yet: wp-login.php is doing the job.', 'users-plus-for-wordpress' ),
				upfw_admin_url( 'upfw-login' ),
				__( 'Choose it', 'users-plus-for-wordpress' )
			);

			upfw_status_row(
				__( 'Link by email', 'users-plus-for-wordpress' ),
				'ok',
				upfw_option( 'upfw_login_register' )
					? __( 'On. If the email does not exist, the account is created in the same step.', 'users-plus-for-wordpress' )
					: __( 'On, for accounts that already exist. New ones are not created from here.', 'users-plus-for-wordpress' )
			);

			$metodos = array(
				'link'     => __( 'Only the email link: wp-login.php sends people to the sign-in page.', 'users-plus-for-wordpress' ),
				'password' => __( 'Only username and password, the WordPress one.', 'users-plus-for-wordpress' ),
				'both'     => __( 'The email link and the password, both.', 'users-plus-for-wordpress' ),
			);

			upfw_status_row(
				__( 'How people get in', 'users-plus-for-wordpress' ),
				'ok',
				$metodos[ upfw_login_method() ]
			);

			$dos_pasos = array(
				'off'      => __( 'Off: nobody is asked for a second step.', 'users-plus-for-wordpress' ),
				'optional' => __( 'Optional: whoever wants it turns it on from their profile.', 'users-plus-for-wordpress' ),
				'required' => __( 'Required for everybody who can use it.', 'users-plus-for-wordpress' ),
			);

			upfw_status_row(
				__( 'Two-step verification', 'users-plus-for-wordpress' ),
				'off' === (string) upfw_option( 'upfw_2fa_mode' ) ? 'pending' : 'ok',
				$dos_pasos[ (string) upfw_option( 'upfw_2fa_mode' ) ] ?? ''
			);

			upfw_status_row(
				__( 'Passkeys', 'users-plus-for-wordpress' ),
				upfw_option( 'upfw_passkey_enabled' ) ? 'ok' : 'pending',
				upfw_option( 'upfw_passkey_enabled' )
					? sprintf(
						/* translators: %s: el dominio con el que quedan atadas */
						__( 'On, tied to %s.', 'users-plus-for-wordpress' ),
						upfw_passkey_rp_id()
					)
					: __( 'Off. It is the only way in that cannot be phished.', 'users-plus-for-wordpress' )
			);

			upfw_status_row(
				__( 'Social login', 'users-plus-for-wordpress' ),
				array() !== $ready ? 'ok' : 'pending',
				array() !== $ready
					/* translators: %s: lista de proveedores */
					? sprintf( __( 'Working: %s', 'users-plus-for-wordpress' ), implode( ', ', wp_list_pluck( $ready, 'name' ) ) )
					: (
						array() !== $setup
							? __( 'There are providers with credentials, but none is verified and enabled yet.', 'users-plus-for-wordpress' )
							: __( 'No provider set up yet: only the email link works.', 'users-plus-for-wordpress' )
					),
				upfw_admin_url( 'upfw-social' ),
				__( 'Providers', 'users-plus-for-wordpress' )
			);

			// Si sale o no un correo no se puede saber sin mandar uno. Lo único
			// comprobable es si hay algo enganchado al envío, y eso no alcanza
			// para decir que funciona.
			$hay_mailer = (bool) has_filter( 'phpmailer_init' );

			upfw_status_row(
				__( 'Outgoing email', 'users-plus-for-wordpress' ),
				$hay_mailer ? 'unknown' : 'pending',
				$hay_mailer
					? __( 'Something is hooked into delivery, but whether mail actually leaves cannot be known from here. Send yourself a link to find out.', 'users-plus-for-wordpress' )
					: __( 'Nothing is hooked into delivery: WordPress will try the server’s mail() and that usually fails. Without email there is no sign-in link.', 'users-plus-for-wordpress' )
			);

			upfw_status_row(
				__( 'Session length', 'users-plus-for-wordpress' ),
				'ok',
				sprintf(
					/* translators: 1: días con recordarme, 2: días sin recordarme */
					__( '%1$d days with “remember me”, %2$d without.', 'users-plus-for-wordpress' ),
					(int) upfw_option( 'upfw_session_long_days' ),
					(int) upfw_option( 'upfw_session_short_days' )
				),
				upfw_admin_url( 'upfw-sessions', array( 'tab' => 'duration' ) ),
				__( 'Change it', 'users-plus-for-wordpress' )
			);
			?>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'What is asked of people', 'users-plus-for-wordpress' ); ?></h2>
	<?php if ( array() === $active ) : ?>
		<p class="upfw-admin__intro"><?php esc_html_e( 'Nothing beyond the email address.', 'users-plus-for-wordpress' ); ?></p>
	<?php else : ?>
		<table class="widefat striped upfw-estado">
			<tbody>
				<?php
				foreach ( upfw_groups() as $group => $group_label ) :
					$in_group = array_filter( $active, static fn( array $f ): bool => $f['group'] === $group );

					if ( array() === $in_group ) {
						continue;
					}
					?>
					<tr>
						<th scope="row"><?php echo esc_html( $group_label ); ?></th>
						<td><?php echo esc_html( implode( ' · ', wp_list_pluck( $in_group, 'label' ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2><?php esc_html_e( 'If you get locked out', 'users-plus-for-wordpress' ); ?></h2>
	<p class="upfw-admin__intro">
		<?php esc_html_e( 'On a site without passwords and without outgoing email, an expired session leaves you outside. With access to the server:', 'users-plus-for-wordpress' ); ?>
	</p>
	<p><code>wp upfw login <?php echo esc_html( wp_get_current_user()->user_email ); ?></code></p>
	<?php

	upfw_screen_close();
}
