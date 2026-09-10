<?php
/**
 * La portada del plugin: en qué estado está todo esto ahora.
 *
 * No es una pantalla de bienvenida con un texto fijo. Son los números y el
 * estado reales: cuánta gente hay, cuánta está adentro, qué se le pide, cómo
 * se entra y qué falta configurar. Lo que uno querría saber antes de tocar
 * cualquier otra pantalla.
 *
 * @package UsersDlxPlus
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
function users_dlx_plus_home_numbers(): array {
	$cached = get_transient( 'users_dlx_plus_home_numbers' );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	global $wpdb;

	// Dos conteos para la pantalla de resumen. `count_users()` recorre todos
	// los roles para devolver uno de estos números, y en un sitio de 25.000
	// personas eso tarda; el otro no tiene API ninguna. No se cachean porque
	// la pantalla existe para mostrar cómo está el sitio ahora.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$numbers = array(
		'users'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" ),
		'sessions' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'session_tokens'" ),
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	set_transient( 'users_dlx_plus_home_numbers', $numbers, 15 * MINUTE_IN_SECONDS );

	return $numbers;
}

/** Una baldosa con un número y su rótulo. */
function users_dlx_plus_tile( string $value, string $label, string $link = '', string $link_label = '' ): void {
	?>
	<div class="users-dlx-plus-tile">
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
function users_dlx_plus_status_row( string $what, string $state, string $detail, string $link = '', string $link_label = '' ): void {
	$pills = array(
		'ok'      => array( 'on', __( 'Ready', 'users-dlx-plus' ) ),
		'pending' => array( 'blank', __( 'Pending', 'users-dlx-plus' ) ),
		'unknown' => array( 'off', __( 'Cannot tell', 'users-dlx-plus' ) ),
	);

	[ $tone, $label ] = $pills[ $state ] ?? $pills['unknown'];
	?>
	<tr>
		<th scope="row"><?php echo esc_html( $what ); ?></th>
		<td>
			<span class="users-dlx-plus-pill users-dlx-plus-pill--<?php echo esc_attr( $tone ); ?>"><?php echo esc_html( $label ); ?></span>
			<?php echo esc_html( $detail ); ?>
			<?php if ( '' !== $link ) : ?>
				<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $link_label ); ?></a>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

/** Screen home. */
function users_dlx_plus_screen_home(): void {
	$numbers   = users_dlx_plus_home_numbers();
	$fields    = users_dlx_plus_fields( '', false );
	$active    = array_filter( $fields, static fn( array $f ): bool => (bool) $f['active'] );
	$providers = users_dlx_plus_sso_providers();
	$ready     = users_dlx_plus_sso_available();
	$setup     = array_filter( array_keys( $providers ), 'users_dlx_plus_sso_configured' );
	$page      = (int) users_dlx_plus_option( 'users_dlx_plus_login_page' );

	users_dlx_plus_screen_open( users_dlx_plus_screens()[ USERS_DLX_PLUS_MENU ] );

	users_dlx_plus_intro( __( 'Who is in this site, what is asked of them and how they get in.', 'users-dlx-plus' ) );
	?>

	<div class="users-dlx-plus-tiles">
		<?php
		users_dlx_plus_tile(
			number_format_i18n( $numbers['users'] ),
			__( 'accounts', 'users-dlx-plus' ),
			admin_url( 'users.php' ),
			__( 'All users', 'users-dlx-plus' )
		);

		users_dlx_plus_tile(
			number_format_i18n( $numbers['sessions'] ),
			__( 'with an open session', 'users-dlx-plus' ),
			users_dlx_plus_admin_url( 'users-dlx-plus-sessions' ),
			__( 'See who', 'users-dlx-plus' )
		);

		users_dlx_plus_tile(
			number_format_i18n( count( $active ) ) . ' / ' . number_format_i18n( count( $fields ) ),
			__( 'fields in use', 'users-dlx-plus' ),
			users_dlx_plus_admin_url( 'users-dlx-plus-fields' ),
			__( 'Manage them', 'users-dlx-plus' )
		);

		users_dlx_plus_tile(
			number_format_i18n( count( $ready ) ) . ' / ' . number_format_i18n( count( $providers ) ),
			__( 'social providers on', 'users-dlx-plus' ),
			users_dlx_plus_admin_url( 'users-dlx-plus-social' ),
			__( 'Set them up', 'users-dlx-plus' )
		);
		?>
	</div>

	<h2><?php esc_html_e( 'How people get in', 'users-dlx-plus' ); ?></h2>
	<table class="widefat striped users-dlx-plus-estado">
		<tbody>
			<?php
			users_dlx_plus_status_row(
				__( 'Sign-in page', 'users-dlx-plus' ),
				$page > 0 ? 'ok' : 'pending',
				$page > 0
					? (string) get_the_title( $page )
					: __( 'Not chosen yet: wp-login.php is doing the job.', 'users-dlx-plus' ),
				users_dlx_plus_admin_url( 'users-dlx-plus-login' ),
				__( 'Choose it', 'users-dlx-plus' )
			);

			users_dlx_plus_status_row(
				__( 'Link by email', 'users-dlx-plus' ),
				'ok',
				users_dlx_plus_option( 'users_dlx_plus_login_register' )
					? __( 'On. If the email does not exist, the account is created in the same step.', 'users-dlx-plus' )
					: __( 'On, for accounts that already exist. New ones are not created from here.', 'users-dlx-plus' )
			);

			$metodos = array(
				'link'     => __( 'Only the email link: wp-login.php sends people to the sign-in page.', 'users-dlx-plus' ),
				'password' => __( 'Only username and password, the WordPress one.', 'users-dlx-plus' ),
				'both'     => __( 'The email link and the password, both.', 'users-dlx-plus' ),
			);

			users_dlx_plus_status_row(
				__( 'How people get in', 'users-dlx-plus' ),
				'ok',
				$metodos[ users_dlx_plus_login_method() ]
			);

			$dos_pasos = array(
				'off'      => __( 'Off: nobody is asked for a second step.', 'users-dlx-plus' ),
				'optional' => __( 'Optional: whoever wants it turns it on from their profile.', 'users-dlx-plus' ),
				'required' => __( 'Required for everybody who can use it.', 'users-dlx-plus' ),
			);

			users_dlx_plus_status_row(
				__( 'Two-step verification', 'users-dlx-plus' ),
				'off' === (string) users_dlx_plus_option( 'users_dlx_plus_2fa_mode' ) ? 'pending' : 'ok',
				$dos_pasos[ (string) users_dlx_plus_option( 'users_dlx_plus_2fa_mode' ) ] ?? ''
			);

			users_dlx_plus_status_row(
				__( 'Passkeys', 'users-dlx-plus' ),
				users_dlx_plus_option( 'users_dlx_plus_passkey_enabled' ) ? 'ok' : 'pending',
				users_dlx_plus_option( 'users_dlx_plus_passkey_enabled' )
					? sprintf(
						/* translators: %s: el dominio con el que quedan atadas */
						__( 'On, tied to %s.', 'users-dlx-plus' ),
						users_dlx_plus_passkey_rp_id()
					)
					: __( 'Off. It is the only way in that cannot be phished.', 'users-dlx-plus' )
			);

			users_dlx_plus_status_row(
				__( 'Social login', 'users-dlx-plus' ),
				array() !== $ready ? 'ok' : 'pending',
				array() !== $ready
					/* translators: %s: lista de proveedores */
					? sprintf( __( 'Working: %s', 'users-dlx-plus' ), implode( ', ', wp_list_pluck( $ready, 'name' ) ) )
					: (
						array() !== $setup
							? __( 'There are providers with credentials, but none is verified and enabled yet.', 'users-dlx-plus' )
							: __( 'No provider set up yet: only the email link works.', 'users-dlx-plus' )
					),
				users_dlx_plus_admin_url( 'users-dlx-plus-social' ),
				__( 'Providers', 'users-dlx-plus' )
			);

			// Si sale o no un correo no se puede saber sin mandar uno. Lo único
			// comprobable es si hay algo enganchado al envío, y eso no alcanza
			// para decir que funciona.
			$hay_mailer = (bool) has_filter( 'phpmailer_init' );

			users_dlx_plus_status_row(
				__( 'Outgoing email', 'users-dlx-plus' ),
				$hay_mailer ? 'unknown' : 'pending',
				$hay_mailer
					? __( 'Something is hooked into delivery, but whether mail actually leaves cannot be known from here. Send yourself a link to find out.', 'users-dlx-plus' )
					: __( 'Nothing is hooked into delivery: WordPress will try the server’s mail() and that usually fails. Without email there is no sign-in link.', 'users-dlx-plus' )
			);

			users_dlx_plus_status_row(
				__( 'Session length', 'users-dlx-plus' ),
				'ok',
				sprintf(
					/* translators: 1: días con recordarme, 2: días sin recordarme */
					__( '%1$d days with “remember me”, %2$d without.', 'users-dlx-plus' ),
					(int) users_dlx_plus_option( 'users_dlx_plus_session_long_days' ),
					(int) users_dlx_plus_option( 'users_dlx_plus_session_short_days' )
				),
				users_dlx_plus_admin_url( 'users-dlx-plus-sessions', array( 'tab' => 'duration' ) ),
				__( 'Change it', 'users-dlx-plus' )
			);
			?>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'What is asked of people', 'users-dlx-plus' ); ?></h2>
	<?php if ( array() === $active ) : ?>
		<p class="users-dlx-plus-admin__intro"><?php esc_html_e( 'Nothing beyond the email address.', 'users-dlx-plus' ); ?></p>
	<?php else : ?>
		<table class="widefat striped users-dlx-plus-estado">
			<tbody>
				<?php
				foreach ( users_dlx_plus_groups() as $group => $group_label ) :
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

	<h2><?php esc_html_e( 'If you get locked out', 'users-dlx-plus' ); ?></h2>
	<p class="users-dlx-plus-admin__intro">
		<?php esc_html_e( 'On a site without passwords and without outgoing email, an expired session leaves you outside. With access to the server:', 'users-dlx-plus' ); ?>
	</p>
	<p><code>wp users-dlx-plus login <?php echo esc_html( wp_get_current_user()->user_email ); ?></code></p>
	<?php

	users_dlx_plus_screen_close();
}
