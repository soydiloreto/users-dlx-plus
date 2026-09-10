<?php
/**
 * La pantalla de sesiones: quién está adentro y cuánto le dura.
 *
 * La lista se busca y se pagina contra la base. Un combo con todos los
 * usuarios sería medio megabyte de HTML en cada carga en un sitio con
 * veinticinco mil cuentas, y no serviría igual: lo que se necesita es
 * encontrar a una persona, no recorrer la lista.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

/** Screen sessions. */
function upfw_screen_sessions(): void {
	$tabs = array(
		'open'     => __( 'Open sessions', 'users-plus-for-wordpress' ),
		'duration' => __( 'Settings', 'users-plus-for-wordpress' ),
	);

	$current = upfw_tab( $tabs );

	if ( isset( $_POST['upfw_sessions_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['upfw_sessions_nonce'] ) ), 'upfw_sessions_options' ) ) {
		upfw_save_options(
			array(
				// phpcs:disable WordPress.Security.NonceVerification.Missing -- verificado arriba.
				'upfw_session_long_days'  => absint( wp_unslash( $_POST['upfw_session_long_days'] ?? 30 ) ),
				'upfw_session_short_days' => absint( wp_unslash( $_POST['upfw_session_short_days'] ?? 2 ) ),
				'upfw_sessions_show'      => isset( $_POST['upfw_sessions_show'] ) ? 1 : 0,
				// phpcs:enable
			)
		);

		upfw_notice( __( 'Settings saved.', 'users-plus-for-wordpress' ) );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['upfw_done'] ) && 'closed' === sanitize_key( wp_unslash( $_GET['upfw_done'] ) ) ) {
		upfw_notice( __( 'Sessions closed.', 'users-plus-for-wordpress' ) );
	}

	upfw_screen_open( __( 'User sessions', 'users-plus-for-wordpress' ), 'upfw-sessions', $tabs, $current );

	if ( 'duration' === $current ) {
		upfw_screen_sessions_duration();
	} else {
		upfw_screen_sessions_list();
	}

	upfw_screen_close();
}

/** Screen sessions duration. */
function upfw_screen_sessions_duration(): void {
	upfw_intro( __( 'By default WordPress ends the session after 2 days, or 14 with “remember me”. On a passwordless site that means going through the email again and again.', 'users-plus-for-wordpress' ) );
	?>
	<form method="post">
		<?php wp_nonce_field( 'upfw_sessions_options', 'upfw_sessions_nonce' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="upfw_session_long_days"><?php esc_html_e( 'With “remember me”', 'users-plus-for-wordpress' ); ?></label></th>
				<td>
					<input type="number" id="upfw_session_long_days" name="upfw_session_long_days" min="1" class="small-text" value="<?php echo esc_attr( (string) upfw_option( 'upfw_session_long_days' ) ); ?>">
					<?php esc_html_e( 'days', 'users-plus-for-wordpress' ); ?>
					<p class="description"><?php esc_html_e( 'The sign-in link always counts as “remember me”: there is no password to type again.', 'users-plus-for-wordpress' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="upfw_session_short_days"><?php esc_html_e( 'Without “remember me”', 'users-plus-for-wordpress' ); ?></label></th>
				<td>
					<input type="number" id="upfw_session_short_days" name="upfw_session_short_days" min="1" class="small-text" value="<?php echo esc_attr( (string) upfw_option( 'upfw_session_short_days' ) ); ?>">
					<?php esc_html_e( 'days', 'users-plus-for-wordpress' ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'In their account', 'users-plus-for-wordpress' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="upfw_sessions_show" value="1" <?php checked( upfw_option( 'upfw_sessions_show' ), 1 ); ?>>
						<?php esc_html_e( 'Each person sees where they have a session open, and can close them', 'users-plus-for-wordpress' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'It shows up under Security. With this off, that box is not there —and closing sessions stays an administrator job, from here.', 'users-plus-for-wordpress' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
	<?php
}

/** Screen sessions list. */
function upfw_screen_sessions_list(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- es una búsqueda de lectura.
	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$per    = isset( $_GET['per'] ) ? max( 5, min( 200, absint( $_GET['per'] ) ) ) : 20;
	// phpcs:enable

	$result = upfw_sessions_search( $search, $page, $per );
	$total  = $result['total'];
	$pages  = (int) max( 1, ceil( $total / $per ) );
	?>
	<form method="get" class="upfw-buscador">
		<input type="hidden" name="page" value="upfw-sessions">
		<label class="screen-reader-text" for="upfw-s"><?php esc_html_e( 'Search', 'users-plus-for-wordpress' ); ?></label>
		<input type="search" id="upfw-s" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Email, username or name…', 'users-plus-for-wordpress' ); ?>">
		<?php submit_button( __( 'Search', 'users-plus-for-wordpress' ), 'secondary', '', false ); ?>

		<a class="button" href="<?php echo esc_url( upfw_admin_url( 'upfw-sessions', array( 'per' => $per ) ) ); ?>"><?php esc_html_e( 'Clear', 'users-plus-for-wordpress' ); ?></a>

		<label class="upfw-buscador__por">
			<?php esc_html_e( 'Show', 'users-plus-for-wordpress' ); ?>
			<select name="per" onchange="this.form.submit()">
				<?php foreach ( array( 10, 20, 50, 100 ) as $opcion ) : ?>
					<option value="<?php echo esc_attr( (string) $opcion ); ?>" <?php selected( $per, $opcion ); ?>><?php echo esc_html( number_format_i18n( $opcion ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php esc_html_e( 'per page', 'users-plus-for-wordpress' ); ?>
		</label>

		<?php
		$upfw_refrescar = upfw_admin_url(
			'upfw-sessions',
			array(
				's'     => $search,
				'per'   => $per,
				'paged' => $page,
			)
		);
		?>
		<a class="button upfw-buscador__refrescar" href="<?php echo esc_url( $upfw_refrescar ); ?>">
			<span class="dashicons dashicons-update" aria-hidden="true"></span>
			<?php esc_html_e( 'Refresh', 'users-plus-for-wordpress' ); ?>
		</a>

		<span class="upfw-buscador__cuenta">
			<?php
			printf(
				/* translators: 1: cantidad de personas, 2: página actual, 3: total de páginas */
				esc_html__( '%1$s people with an open session · page %2$d of %3$d', 'users-plus-for-wordpress' ),
				esc_html( number_format_i18n( $total ) ),
				(int) $page,
				(int) $pages
			);
			?>
		</span>
	</form>

	<table class="wp-list-table widefat fixed striped upfw-list">
		<thead>
			<tr>
				<th class="upfw-list__name"><?php esc_html_e( 'Person', 'users-plus-for-wordpress' ); ?></th>
				<th><?php esc_html_e( 'Last sign-in', 'users-plus-for-wordpress' ); ?></th>
				<th><?php esc_html_e( 'Expires', 'users-plus-for-wordpress' ); ?></th>
				<th><?php esc_html_e( 'Status', 'users-plus-for-wordpress' ); ?></th>
				<th><?php esc_html_e( 'IP', 'users-plus-for-wordpress' ); ?></th>
				<th><?php esc_html_e( 'Device', 'users-plus-for-wordpress' ); ?></th>
				<th class="upfw-list__num"><?php esc_html_e( 'Sessions', 'users-plus-for-wordpress' ); ?></th>
				<th class="upfw-list__order"><?php esc_html_e( 'Actions', 'users-plus-for-wordpress' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( array() === $result['rows'] ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'Nobody matches that.', 'users-plus-for-wordpress' ); ?></td></tr>
			<?php endif; ?>

			<?php
			foreach ( $result['rows'] as $row ) :
				$vigente = $row['expires'] > time();
				?>
				<tr>
					<td class="upfw-list__name">
						<strong><a href="<?php echo esc_url( get_edit_user_link( $row['user_id'] ) ); ?>"><?php echo esc_html( '' !== $row['name'] ? $row['name'] : $row['login'] ); ?></a></strong>
						<span class="upfw-list__mail"><?php echo esc_html( $row['email'] ); ?></span>
					</td>
					<td><?php echo esc_html( $row['started'] ? (string) wp_date( 'j M Y, H:i', $row['started'] ) : '—' ); ?></td>
					<td><?php echo esc_html( $row['expires'] ? (string) wp_date( 'j M Y, H:i', $row['expires'] ) : '—' ); ?></td>
					<td>
						<span class="upfw-pill upfw-pill--<?php echo $vigente ? 'on' : 'off'; ?>">
							<?php echo $vigente ? esc_html__( 'Active', 'users-plus-for-wordpress' ) : esc_html__( 'Expired', 'users-plus-for-wordpress' ); ?>
						</span>
					</td>
					<td><code><?php echo esc_html( $row['ip'] ); ?></code></td>
					<td><?php echo esc_html( trim( $row['browser'] . ( '' !== $row['os'] ? ' · ' . $row['os'] : '' ) ) ); ?></td>
					<td class="upfw-list__num"><?php echo esc_html( number_format_i18n( $row['sessions'] ) ); ?></td>
					<td class="upfw-list__order">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="upfw_sessions_admin">
							<input type="hidden" name="upfw_user" value="<?php echo esc_attr( (string) $row['user_id'] ); ?>">
							<?php wp_nonce_field( 'upfw_sessions_admin' ); ?>
							<button type="submit" class="button button-small"><?php esc_html_e( 'Close sessions', 'users-plus-for-wordpress' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $pages > 1 ) : ?>
		<div class="tablenav"><div class="tablenav-pages">
			<?php
			$upfw_base = upfw_admin_url(
				'upfw-sessions',
				array(
					's'   => $search,
					'per' => $per,
				)
			);

			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => $upfw_base . '&paged=%#%',
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
