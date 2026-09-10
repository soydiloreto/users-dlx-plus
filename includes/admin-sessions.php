<?php
/**
 * La pantalla de sesiones: quién está adentro y cuánto le dura.
 *
 * La lista se busca y se pagina contra la base. Un combo con todos los
 * usuarios sería medio megabyte de HTML en cada carga en un sitio con
 * veinticinco mil cuentas, y no serviría igual: lo que se necesita es
 * encontrar a una persona, no recorrer la lista.
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;

/** Screen sessions. */
function users_plus_screen_sessions(): void {
	$tabs = array(
		'open'     => __( 'Open sessions', 'users-plus' ),
		'duration' => __( 'Settings', 'users-plus' ),
	);

	$current = users_plus_tab( $tabs );

	if ( isset( $_POST['users_plus_sessions_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['users_plus_sessions_nonce'] ) ), 'users_plus_sessions_options' ) ) {
		users_plus_save_options(
			array(
				// phpcs:disable WordPress.Security.NonceVerification.Missing -- verificado arriba.
				'users_plus_session_long_days'  => absint( wp_unslash( $_POST['users_plus_session_long_days'] ?? 30 ) ),
				'users_plus_session_short_days' => absint( wp_unslash( $_POST['users_plus_session_short_days'] ?? 2 ) ),
				'users_plus_sessions_show'      => isset( $_POST['users_plus_sessions_show'] ) ? 1 : 0,
				// phpcs:enable
			)
		);

		users_plus_notice( __( 'Settings saved.', 'users-plus' ) );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['users_plus_done'] ) && 'closed' === sanitize_key( wp_unslash( $_GET['users_plus_done'] ) ) ) {
		users_plus_notice( __( 'Sessions closed.', 'users-plus' ) );
	}

	users_plus_screen_open( __( 'User sessions', 'users-plus' ), 'users-plus-sessions', $tabs, $current );

	if ( 'duration' === $current ) {
		users_plus_screen_sessions_duration();
	} else {
		users_plus_screen_sessions_list();
	}

	users_plus_screen_close();
}

/** Screen sessions duration. */
function users_plus_screen_sessions_duration(): void {
	users_plus_intro( __( 'By default WordPress ends the session after 2 days, or 14 with “remember me”. On a passwordless site that means going through the email again and again.', 'users-plus' ) );
	?>
	<form method="post">
		<?php wp_nonce_field( 'users_plus_sessions_options', 'users_plus_sessions_nonce' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="users_plus_session_long_days"><?php esc_html_e( 'With “remember me”', 'users-plus' ); ?></label></th>
				<td>
					<input type="number" id="users_plus_session_long_days" name="users_plus_session_long_days" min="1" class="small-text" value="<?php echo esc_attr( (string) users_plus_option( 'users_plus_session_long_days' ) ); ?>">
					<?php esc_html_e( 'days', 'users-plus' ); ?>
					<p class="description"><?php esc_html_e( 'The sign-in link always counts as “remember me”: there is no password to type again.', 'users-plus' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="users_plus_session_short_days"><?php esc_html_e( 'Without “remember me”', 'users-plus' ); ?></label></th>
				<td>
					<input type="number" id="users_plus_session_short_days" name="users_plus_session_short_days" min="1" class="small-text" value="<?php echo esc_attr( (string) users_plus_option( 'users_plus_session_short_days' ) ); ?>">
					<?php esc_html_e( 'days', 'users-plus' ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'In their account', 'users-plus' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="users_plus_sessions_show" value="1" <?php checked( users_plus_option( 'users_plus_sessions_show' ), 1 ); ?>>
						<?php esc_html_e( 'Each person sees where they have a session open, and can close them', 'users-plus' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'It shows up under Security. With this off, that box is not there —and closing sessions stays an administrator job, from here.', 'users-plus' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
	<?php
}

/** Screen sessions list. */
function users_plus_screen_sessions_list(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- es una búsqueda de lectura.
	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$per    = isset( $_GET['per'] ) ? max( 5, min( 200, absint( $_GET['per'] ) ) ) : 20;
	// phpcs:enable

	$result = users_plus_sessions_search( $search, $page, $per );
	$total  = $result['total'];
	$pages  = (int) max( 1, ceil( $total / $per ) );
	?>
	<form method="get" class="users-plus-buscador">
		<input type="hidden" name="page" value="users-plus-sessions">
		<label class="screen-reader-text" for="users-plus-s"><?php esc_html_e( 'Search', 'users-plus' ); ?></label>
		<input type="search" id="users-plus-s" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Email, username or name…', 'users-plus' ); ?>">
		<?php submit_button( __( 'Search', 'users-plus' ), 'secondary', '', false ); ?>

		<a class="button" href="<?php echo esc_url( users_plus_admin_url( 'users-plus-sessions', array( 'per' => $per ) ) ); ?>"><?php esc_html_e( 'Clear', 'users-plus' ); ?></a>

		<label class="users-plus-buscador__por">
			<?php esc_html_e( 'Show', 'users-plus' ); ?>
			<select name="per" onchange="this.form.submit()">
				<?php foreach ( array( 10, 20, 50, 100 ) as $opcion ) : ?>
					<option value="<?php echo esc_attr( (string) $opcion ); ?>" <?php selected( $per, $opcion ); ?>><?php echo esc_html( number_format_i18n( $opcion ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php esc_html_e( 'per page', 'users-plus' ); ?>
		</label>

		<?php
		$users_plus_refrescar = users_plus_admin_url(
			'users-plus-sessions',
			array(
				's'     => $search,
				'per'   => $per,
				'paged' => $page,
			)
		);
		?>
		<a class="button users-plus-buscador__refrescar" href="<?php echo esc_url( $users_plus_refrescar ); ?>">
			<span class="dashicons dashicons-update" aria-hidden="true"></span>
			<?php esc_html_e( 'Refresh', 'users-plus' ); ?>
		</a>

		<span class="users-plus-buscador__cuenta">
			<?php
			printf(
				/* translators: 1: cantidad de personas, 2: página actual, 3: total de páginas */
				esc_html__( '%1$s people with an open session · page %2$d of %3$d', 'users-plus' ),
				esc_html( number_format_i18n( $total ) ),
				(int) $page,
				(int) $pages
			);
			?>
		</span>
	</form>

	<table class="wp-list-table widefat fixed striped users-plus-list">
		<thead>
			<tr>
				<th class="users-plus-list__name"><?php esc_html_e( 'Person', 'users-plus' ); ?></th>
				<th><?php esc_html_e( 'Last sign-in', 'users-plus' ); ?></th>
				<th><?php esc_html_e( 'Expires', 'users-plus' ); ?></th>
				<th><?php esc_html_e( 'Status', 'users-plus' ); ?></th>
				<th><?php esc_html_e( 'IP', 'users-plus' ); ?></th>
				<th><?php esc_html_e( 'Device', 'users-plus' ); ?></th>
				<th class="users-plus-list__num"><?php esc_html_e( 'Sessions', 'users-plus' ); ?></th>
				<th class="users-plus-list__order"><?php esc_html_e( 'Actions', 'users-plus' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( array() === $result['rows'] ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'Nobody matches that.', 'users-plus' ); ?></td></tr>
			<?php endif; ?>

			<?php
			foreach ( $result['rows'] as $row ) :
				$vigente = $row['expires'] > time();
				?>
				<tr>
					<td class="users-plus-list__name">
						<strong><a href="<?php echo esc_url( get_edit_user_link( $row['user_id'] ) ); ?>"><?php echo esc_html( '' !== $row['name'] ? $row['name'] : $row['login'] ); ?></a></strong>
						<span class="users-plus-list__mail"><?php echo esc_html( $row['email'] ); ?></span>
					</td>
					<td><?php echo esc_html( $row['started'] ? (string) wp_date( 'j M Y, H:i', $row['started'] ) : '—' ); ?></td>
					<td><?php echo esc_html( $row['expires'] ? (string) wp_date( 'j M Y, H:i', $row['expires'] ) : '—' ); ?></td>
					<td>
						<span class="users-plus-pill users-plus-pill--<?php echo $vigente ? 'on' : 'off'; ?>">
							<?php echo $vigente ? esc_html__( 'Active', 'users-plus' ) : esc_html__( 'Expired', 'users-plus' ); ?>
						</span>
					</td>
					<td><code><?php echo esc_html( $row['ip'] ); ?></code></td>
					<td><?php echo esc_html( trim( $row['browser'] . ( '' !== $row['os'] ? ' · ' . $row['os'] : '' ) ) ); ?></td>
					<td class="users-plus-list__num"><?php echo esc_html( number_format_i18n( $row['sessions'] ) ); ?></td>
					<td class="users-plus-list__order">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="users_plus_sessions_admin">
							<input type="hidden" name="users_plus_user" value="<?php echo esc_attr( (string) $row['user_id'] ); ?>">
							<?php wp_nonce_field( 'users_plus_sessions_admin' ); ?>
							<button type="submit" class="button button-small"><?php esc_html_e( 'Close sessions', 'users-plus' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $pages > 1 ) : ?>
		<div class="tablenav"><div class="tablenav-pages">
			<?php
			$users_plus_base = users_plus_admin_url(
				'users-plus-sessions',
				array(
					's'   => $search,
					'per' => $per,
				)
			);

			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => $users_plus_base . '&paged=%#%',
						'format'    => '',
						'current'   => $page,
						'total'     => $pages,
						'prev_text' => '&lsaquo;',
						'next_text' => '&rsaquo;',
					)
				)
			);
			?>
		</div></div>
	<?php endif; ?>
	<?php
}
