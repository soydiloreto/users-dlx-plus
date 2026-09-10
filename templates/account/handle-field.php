<?php
/**
 * El campo del nombre público, para meter dentro de otro formulario.
 *
 * Variables: $handle, $can, $next.
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

$upfw_url_actual = upfw_handle_base_url() . $handle . '/';
?>
<div class="upfw-handle-campo">
	<label for="upfw-handle"><?php esc_html_e( 'Public name', 'users-plus-for-wordpress' ); ?></label>

	<p class="upfw-handle__que">
		<?php esc_html_e( 'It is your short name on the site: the one that goes in the address of your profile and the one other people use to find you. It is not how you sign in —that is always your email— and it is not the name shown on your certificates, which comes from your first and last name.', 'users-plus-for-wordpress' ); ?>
	</p>

	<input type="text" id="upfw-handle" name="upfw_handle" value="<?php echo esc_attr( $handle ); ?>"
		minlength="<?php echo esc_attr( (string) upfw_option( 'upfw_handle_min' ) ); ?>"
		maxlength="<?php echo esc_attr( (string) upfw_option( 'upfw_handle_max' ) ); ?>"
		autocomplete="off" spellcheck="false"
		<?php disabled( ! $can ); ?>>

	<p class="upfw-handle__vista" data-upfw-handle-vista<?php echo '' === $handle ? ' hidden' : ''; ?>>
		<?php esc_html_e( 'Your profile:', 'users-plus-for-wordpress' ); ?>
		<a href="<?php echo esc_url( $upfw_url_actual ); ?>" target="_blank" rel="noopener" data-upfw-handle-url><?php echo esc_html( $upfw_url_actual ); ?></a>
	</p>

	<?php if ( $can ) : ?>
		<p class="upfw-handle__estado">
			<a href="<?php echo esc_url( $upfw_url_actual ); ?>" target="_blank" rel="noopener" data-upfw-handle-check><?php esc_html_e( 'Check if it is available', 'users-plus-for-wordpress' ); ?></a>
			<span data-upfw-handle-aviso></span>
		</p>
	<?php endif; ?>

	<p class="upfw-note">
		<?php if ( ! $can ) : ?>
			<?php
			echo esc_html(
				sprintf(
				/* translators: %s: fecha a partir de la cual se puede cambiar */
					__( 'You changed it recently. You can change it again on %s.', 'users-plus-for-wordpress' ),
					wp_date( 'j M Y', $next )
				)
			);
			?>
		<?php else : ?>
			<?php
			echo 'reject' === upfw_option( 'upfw_handle_spaces' )
				? esc_html__( 'No spaces: this goes in a web address. Letters, numbers, dots, dashes and underscores.', 'users-plus-for-wordpress' )
				: esc_html__( 'Spaces turn into dashes, because a web address cannot have them. Everything else that does not fit in an address is dropped.', 'users-plus-for-wordpress' );
			?>
		<?php endif; ?>
	</p>
</div>
