<?php
/**
 * Apariencia: cómo se ve todo lo que pinta el plugin.
 *
 * Pantalla propia y no una solapa del acceso, que era donde estaba: la hoja de
 * estilos, el color y la foto de perfil valen para el área de cuenta, para los
 * campos, para las sesiones y para el ingreso. Un ajuste que manda sobre todo
 * el plugin no puede vivir adentro de una de sus partes.
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;

/** Screen appearance. */
function users_dlx_plus_screen_appearance(): void {
	$tabs = array(
		'styles' => __( 'Styles', 'users-dlx-plus' ),
		'photo'  => __( 'Profile photo', 'users-dlx-plus' ),
	);

	$current = users_dlx_plus_tab( $tabs );

	if ( isset( $_POST['users_dlx_plus_appearance_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['users_dlx_plus_appearance_nonce'] ) ), 'users_dlx_plus_appearance' ) ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verificado arriba.
		if ( 'photo' === $current ) {
			users_dlx_plus_save_options(
				array(
					'users_dlx_plus_avatar_upload'   => isset( $_POST['users_dlx_plus_avatar_upload'] ) ? 1 : 0,
					'users_dlx_plus_avatar_gravatar' => isset( $_POST['users_dlx_plus_avatar_gravatar'] ) ? 1 : 0,
					'users_dlx_plus_avatar_initials' => isset( $_POST['users_dlx_plus_avatar_initials'] ) ? 1 : 0,
					'users_dlx_plus_avatar_max_kb'   => absint( wp_unslash( $_POST['users_dlx_plus_avatar_max_kb'] ?? 2048 ) ),
				)
			);
		} else {
			users_dlx_plus_save_options(
				array(
					'users_dlx_plus_styles'       => isset( $_POST['users_dlx_plus_styles'] ) ? 1 : 0,
					'users_dlx_plus_style_accent' => sanitize_hex_color( wp_unslash( $_POST['users_dlx_plus_style_accent'] ?? '' ) ) ?? '',
					'users_dlx_plus_style_radius' => sanitize_text_field( wp_unslash( $_POST['users_dlx_plus_style_radius'] ?? '' ) ),
				)
			);
		}
		// phpcs:enable

		users_dlx_plus_notice( __( 'Saved.', 'users-dlx-plus' ) );
	}

	users_dlx_plus_screen_open( __( 'Appearance', 'users-dlx-plus' ), 'users-dlx-plus-appearance', $tabs, $current );

	echo '<form method="post">';
	wp_nonce_field( 'users_dlx_plus_appearance', 'users_dlx_plus_appearance_nonce' );

	if ( 'photo' === $current ) {
		users_dlx_plus_screen_appearance_photo();
	} else {
		users_dlx_plus_screen_appearance_styles();
	}

	submit_button();
	echo '</form>';

	users_dlx_plus_screen_close();
}

/** La hoja, el color y las esquinas. */
function users_dlx_plus_screen_appearance_styles(): void {
	users_dlx_plus_intro( __( 'Everything the plugin draws —panels, forms, lists, buttons— takes its colours and its corners from a handful of CSS properties. Change those two below and everything follows; a site with its own design can point them at its own tokens from its stylesheet, without copying anything from here.', 'users-dlx-plus' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Styles', 'users-dlx-plus' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="users_dlx_plus_styles" value="1" <?php checked( users_dlx_plus_option( 'users_dlx_plus_styles' ), 1 ); ?>>
					<?php esc_html_e( 'Load the plugin stylesheet', 'users-dlx-plus' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Only turn it off if the site is going to style every users-dlx-plus-* class itself. Turned off, its panels and buttons come out bare and the site has to draw them; re-pointing the properties is almost always enough, and it survives the plugin adding a new component.', 'users-dlx-plus' ); ?></p>
				<p class="description"><code>--users-dlx-plus-accent</code> <code>--users-dlx-plus-surface</code> <code>--users-dlx-plus-border</code> <code>--users-dlx-plus-text</code> <code>--users-dlx-plus-muted</code> <code>--users-dlx-plus-radius</code> <code>--users-dlx-plus-control-h</code></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Dark mode', 'users-dlx-plus' ); ?></th>
			<td>
				<p class="description"><?php esc_html_e( 'There is none here, on purpose. Dark mode belongs to the site: a light site seen from a dark system used to end up with a light page and black panels. If the site has a dark mode, its own tokens change and these follow.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="users_dlx_plus_style_accent"><?php esc_html_e( 'Accent colour', 'users-dlx-plus' ); ?></label></th>
			<td>
				<input type="color" id="users_dlx_plus_style_accent" name="users_dlx_plus_style_accent" value="<?php echo esc_attr( users_dlx_plus_style_accent() ); ?>">
				<p class="description"><?php esc_html_e( 'Buttons, the open tab, links and the drawn avatars.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="users_dlx_plus_style_radius"><?php esc_html_e( 'Corners', 'users-dlx-plus' ); ?></label></th>
			<td>
				<input type="number" id="users_dlx_plus_style_radius" name="users_dlx_plus_style_radius" class="small-text" min="0" max="40" value="<?php echo esc_attr( (string) users_dlx_plus_option( 'users_dlx_plus_style_radius' ) ); ?>">
				<?php esc_html_e( 'pixels — empty for the default', 'users-dlx-plus' ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Templates', 'users-dlx-plus' ); ?></th>
			<td>
				<p class="description"><?php esc_html_e( 'Copy any file from the plugin’s templates/ folder to your theme and edit it there:', 'users-dlx-plus' ); ?></p>
				<p><code><?php echo esc_html( 'wp-content/themes/' . get_stylesheet() . '/users-dlx-plus/' ); ?></code></p>
				<p class="description"><code>account.php</code> · <code>account-nav.php</code> · <code>account/*.php</code> · <code>login.php</code> · <code>fields.php</code> · <code>accounts.php</code> · <code>sessions.php</code></p>
			</td>
		</tr>
	</table>
	<?php
}

/** Las tres capas de la foto de perfil. */
function users_dlx_plus_screen_appearance_photo(): void {
	users_dlx_plus_intro( __( 'Three layers, in this order: the photo the person uploaded, then Gravatar, then their initials drawn on the accent colour. Turn off the ones you do not want.', 'users-dlx-plus' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Where it comes from', 'users-dlx-plus' ); ?></th>
			<td>
				<label class="users-dlx-plus-roles__item">
					<input type="checkbox" name="users_dlx_plus_avatar_upload" value="1" <?php checked( users_dlx_plus_option( 'users_dlx_plus_avatar_upload' ), 1 ); ?>>
					<?php esc_html_e( 'Let people upload their own', 'users-dlx-plus' ); ?>
				</label>
				<label class="users-dlx-plus-roles__item">
					<input type="checkbox" name="users_dlx_plus_avatar_gravatar" value="1" <?php checked( users_dlx_plus_option( 'users_dlx_plus_avatar_gravatar' ), 1 ); ?>>
					<?php esc_html_e( 'Fall back to Gravatar when there is none', 'users-dlx-plus' ); ?>
				</label>
				<label class="users-dlx-plus-roles__item">
					<input type="checkbox" name="users_dlx_plus_avatar_initials" value="1" <?php checked( users_dlx_plus_option( 'users_dlx_plus_avatar_initials' ), 1 ); ?>>
					<?php esc_html_e( 'Otherwise, draw their initials', 'users-dlx-plus' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Gravatar means sending a hash of every visitor’s email address to a third party. With it off and initials on, nothing leaves the site.', 'users-dlx-plus' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="users_dlx_plus_avatar_max_kb"><?php esc_html_e( 'Largest photo accepted', 'users-dlx-plus' ); ?></label></th>
			<td>
				<input type="number" id="users_dlx_plus_avatar_max_kb" name="users_dlx_plus_avatar_max_kb" class="small-text" min="64" value="<?php echo esc_attr( (string) users_dlx_plus_option( 'users_dlx_plus_avatar_max_kb' ) ); ?>"> KB
			</td>
		</tr>
	</table>
	<?php
}
