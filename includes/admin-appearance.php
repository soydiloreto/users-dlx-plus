<?php
/**
 * Apariencia: cómo se ve todo lo que pinta el plugin.
 *
 * Pantalla propia y no una solapa del acceso, que era donde estaba: la hoja de
 * estilos, el color y la foto de perfil valen para el área de cuenta, para los
 * campos, para las sesiones y para el ingreso. Un ajuste que manda sobre todo
 * el plugin no puede vivir adentro de una de sus partes.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

function upfw_screen_appearance(): void {
	$tabs = array(
		'styles' => __( 'Styles', 'users-plus-for-wordpress' ),
		'photo'  => __( 'Profile photo', 'users-plus-for-wordpress' ),
	);

	$current = upfw_tab( $tabs );

	if ( isset( $_POST['upfw_appearance_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['upfw_appearance_nonce'] ) ), 'upfw_appearance' ) ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verificado arriba.
		if ( 'photo' === $current ) {
			upfw_save_options(
				array(
					'upfw_avatar_upload'   => isset( $_POST['upfw_avatar_upload'] ) ? 1 : 0,
					'upfw_avatar_gravatar' => isset( $_POST['upfw_avatar_gravatar'] ) ? 1 : 0,
					'upfw_avatar_initials' => isset( $_POST['upfw_avatar_initials'] ) ? 1 : 0,
					'upfw_avatar_max_kb'   => (int) ( $_POST['upfw_avatar_max_kb'] ?? 2048 ),
				)
			);
		} else {
			upfw_save_options(
				array(
					'upfw_styles'       => isset( $_POST['upfw_styles'] ) ? 1 : 0,
					'upfw_style_accent' => sanitize_hex_color( wp_unslash( $_POST['upfw_style_accent'] ?? '' ) ) ?? '',
					'upfw_style_radius' => sanitize_text_field( wp_unslash( $_POST['upfw_style_radius'] ?? '' ) ),
				)
			);
		}
		// phpcs:enable

		upfw_notice( __( 'Saved.', 'users-plus-for-wordpress' ) );
	}

	upfw_screen_open( __( 'Appearance', 'users-plus-for-wordpress' ), 'upfw-appearance', $tabs, $current );

	echo '<form method="post">';
	wp_nonce_field( 'upfw_appearance', 'upfw_appearance_nonce' );

	if ( 'photo' === $current ) {
		upfw_screen_appearance_photo();
	} else {
		upfw_screen_appearance_styles();
	}

	submit_button();
	echo '</form>';

	upfw_screen_close();
}

/** La hoja, el color y las esquinas. */
function upfw_screen_appearance_styles(): void {
	upfw_intro( __( 'Everything the plugin draws —panels, forms, lists, buttons— takes its colours and its corners from a handful of CSS properties. Change those two below and everything follows; a site with its own design can point them at its own tokens from its stylesheet, without copying anything from here.', 'users-plus-for-wordpress' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Styles', 'users-plus-for-wordpress' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="upfw_styles" value="1" <?php checked( upfw_option( 'upfw_styles' ), 1 ); ?>>
					<?php esc_html_e( 'Load the plugin stylesheet', 'users-plus-for-wordpress' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Only turn it off if the site is going to style every upfw-* class itself. Turned off, its panels and buttons come out bare and the site has to draw them; re-pointing the properties is almost always enough, and it survives the plugin adding a new component.', 'users-plus-for-wordpress' ); ?></p>
				<p class="description"><code>--upfw-accent</code> <code>--upfw-surface</code> <code>--upfw-border</code> <code>--upfw-text</code> <code>--upfw-muted</code> <code>--upfw-radius</code> <code>--upfw-control-h</code></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Dark mode', 'users-plus-for-wordpress' ); ?></th>
			<td>
				<p class="description"><?php esc_html_e( 'There is none here, on purpose. Dark mode belongs to the site: a light site seen from a dark system used to end up with a light page and black panels. If the site has a dark mode, its own tokens change and these follow.', 'users-plus-for-wordpress' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="upfw_style_accent"><?php esc_html_e( 'Accent colour', 'users-plus-for-wordpress' ); ?></label></th>
			<td>
				<input type="color" id="upfw_style_accent" name="upfw_style_accent" value="<?php echo esc_attr( upfw_style_accent() ); ?>">
				<p class="description"><?php esc_html_e( 'Buttons, the open tab, links and the drawn avatars.', 'users-plus-for-wordpress' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="upfw_style_radius"><?php esc_html_e( 'Corners', 'users-plus-for-wordpress' ); ?></label></th>
			<td>
				<input type="number" id="upfw_style_radius" name="upfw_style_radius" class="small-text" min="0" max="40" value="<?php echo esc_attr( (string) upfw_option( 'upfw_style_radius' ) ); ?>">
				<?php esc_html_e( 'pixels — empty for the default', 'users-plus-for-wordpress' ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Templates', 'users-plus-for-wordpress' ); ?></th>
			<td>
				<p class="description"><?php esc_html_e( 'Copy any file from the plugin’s templates/ folder to your theme and edit it there:', 'users-plus-for-wordpress' ); ?></p>
				<p><code><?php echo esc_html( 'wp-content/themes/' . get_stylesheet() . '/users-plus-for-wordpress/' ); ?></code></p>
				<p class="description"><code>account.php</code> · <code>account-nav.php</code> · <code>account/*.php</code> · <code>login.php</code> · <code>fields.php</code> · <code>accounts.php</code> · <code>sessions.php</code></p>
			</td>
		</tr>
	</table>
	<?php
}

/** Las tres capas de la foto de perfil. */
function upfw_screen_appearance_photo(): void {
	upfw_intro( __( 'Three layers, in this order: the photo the person uploaded, then Gravatar, then their initials drawn on the accent colour. Turn off the ones you do not want.', 'users-plus-for-wordpress' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Where it comes from', 'users-plus-for-wordpress' ); ?></th>
			<td>
				<label class="upfw-roles__item">
					<input type="checkbox" name="upfw_avatar_upload" value="1" <?php checked( upfw_option( 'upfw_avatar_upload' ), 1 ); ?>>
					<?php esc_html_e( 'Let people upload their own', 'users-plus-for-wordpress' ); ?>
				</label>
				<label class="upfw-roles__item">
					<input type="checkbox" name="upfw_avatar_gravatar" value="1" <?php checked( upfw_option( 'upfw_avatar_gravatar' ), 1 ); ?>>
					<?php esc_html_e( 'Fall back to Gravatar when there is none', 'users-plus-for-wordpress' ); ?>
				</label>
				<label class="upfw-roles__item">
					<input type="checkbox" name="upfw_avatar_initials" value="1" <?php checked( upfw_option( 'upfw_avatar_initials' ), 1 ); ?>>
					<?php esc_html_e( 'Otherwise, draw their initials', 'users-plus-for-wordpress' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Gravatar means sending a hash of every visitor’s email address to a third party. With it off and initials on, nothing leaves the site.', 'users-plus-for-wordpress' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="upfw_avatar_max_kb"><?php esc_html_e( 'Largest photo accepted', 'users-plus-for-wordpress' ); ?></label></th>
			<td>
				<input type="number" id="upfw_avatar_max_kb" name="upfw_avatar_max_kb" class="small-text" min="64" value="<?php echo esc_attr( (string) upfw_option( 'upfw_avatar_max_kb' ) ); ?>"> KB
			</td>
		</tr>
	</table>
	<?php
}
