<?php
/**
 * El campo del nombre público, para meter dentro de otro formulario.
 *
 * @var bool   $can
 * @var string $handle
 * @var int    $next
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;

$users_dlx_plus_url_actual = users_dlx_plus_handle_base_url() . $handle . '/';
?>
<div class="users-dlx-plus-handle-campo">
	<label for="users-dlx-plus-handle"><?php esc_html_e( 'Public name', 'users-dlx-plus' ); ?></label>

	<p class="users-dlx-plus-handle__que">
		<?php esc_html_e( 'It is your short name on the site: the one that goes in the address of your profile and the one other people use to find you. It is not how you sign in —that is always your email— and it is not the name shown on your certificates, which comes from your first and last name.', 'users-dlx-plus' ); ?>
	</p>

	<input type="text" id="users-dlx-plus-handle" name="users_dlx_plus_handle" value="<?php echo esc_attr( $handle ); ?>"
		minlength="<?php echo esc_attr( (string) users_dlx_plus_option( 'users_dlx_plus_handle_min' ) ); ?>"
		maxlength="<?php echo esc_attr( (string) users_dlx_plus_option( 'users_dlx_plus_handle_max' ) ); ?>"
		autocomplete="off" spellcheck="false"
		<?php disabled( ! $can ); ?>>

	<p class="users-dlx-plus-handle__vista" data-users-dlx-plus-handle-vista<?php echo '' === $handle ? ' hidden' : ''; ?>>
		<?php esc_html_e( 'Your profile:', 'users-dlx-plus' ); ?>
		<a href="<?php echo esc_url( $users_dlx_plus_url_actual ); ?>" target="_blank" rel="noopener" data-users-dlx-plus-handle-url><?php echo esc_html( $users_dlx_plus_url_actual ); ?></a>
	</p>

	<?php if ( $can ) : ?>
		<p class="users-dlx-plus-handle__estado">
			<a href="<?php echo esc_url( $users_dlx_plus_url_actual ); ?>" target="_blank" rel="noopener" data-users-dlx-plus-handle-check><?php esc_html_e( 'Check if it is available', 'users-dlx-plus' ); ?></a>
			<span data-users-dlx-plus-handle-aviso></span>
		</p>
	<?php endif; ?>

	<p class="users-dlx-plus-note">
		<?php if ( ! $can ) : ?>
			<?php
			echo esc_html(
				sprintf(
				/* translators: %s: fecha a partir de la cual se puede cambiar */
					__( 'You changed it recently. You can change it again on %s.', 'users-dlx-plus' ),
					wp_date( 'j M Y', $next )
				)
			);
			?>
		<?php else : ?>
			<?php
			echo 'reject' === users_dlx_plus_option( 'users_dlx_plus_handle_spaces' )
				? esc_html__( 'No spaces: this goes in a web address. Letters, numbers, dots, dashes and underscores.', 'users-dlx-plus' )
				: esc_html__( 'Spaces turn into dashes, because a web address cannot have them. Everything else that does not fit in an address is dropped.', 'users-dlx-plus' );
			?>
		<?php endif; ?>
	</p>
</div>
