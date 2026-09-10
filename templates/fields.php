<?php
/**
 * Los campos de la persona, para editarlos.
 *
 * Reemplazable desde el tema en:
 *   wp-content/themes/<tu-tema>/users-plus-for-wordpress/datos.php
 *
 * @var int                              $user_id
 * @var array<int, array<string, mixed>> $fields
 * @var string                           $group
 * @var string                           $title
 * @var string                           $state
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;

if ( array() === $fields ) {
	return;
}
?>
<div class="upfw upfw-fields">

	<?php if ( '' !== $title ) : ?>
		<h2 class="upfw-fields__title"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>

	<?php if ( 'saved' === $state ) : ?>
		<p class="upfw-notice upfw-notice--ok"><?php esc_html_e( 'Saved.', 'users-plus-for-wordpress' ); ?></p>
	<?php elseif ( 'missing' === $state ) : ?>
		<p class="upfw-notice upfw-notice--error"><?php esc_html_e( 'Some required fields are missing.', 'users-plus-for-wordpress' ); ?></p>
	<?php endif; ?>

	<form class="upfw-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="upfw_fields_save">
		<input type="hidden" name="upfw_group" value="<?php echo esc_attr( $group ); ?>">
		<?php wp_nonce_field( 'upfw_fields_save' ); ?>

		<?php foreach ( $fields as $field ) : ?>
			<div class="upfw-field upfw-field--<?php echo esc_attr( $field['type'] ); ?>">
				<?php if ( 'checkbox' !== $field['type'] ) : ?>
					<label for="<?php echo esc_attr( $field['key'] ); ?>">
						<?php echo esc_html( $field['label'] ); ?>
						<?php if ( $field['required'] ) : ?>
							<span class="upfw-field__required" aria-hidden="true">*</span>
						<?php endif; ?>
					</label>
				<?php endif; ?>

				<?php upfw_field_input( $field, upfw_value( $user_id, $field['key'] ) ); ?>

				<?php if ( '' !== $field['help'] ) : ?>
					<p class="upfw-field__help"><?php echo esc_html( $field['help'] ); ?></p>
				<?php endif; ?>

				<?php $upfw_nota = upfw_field_edit_note( $field, $user_id ); ?>
				<?php if ( '' !== $upfw_nota ) : ?>
					<p class="upfw-field__help upfw-field__limite"><?php echo esc_html( $upfw_nota ); ?></p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

		<button type="submit" class="upfw-button"><?php esc_html_e( 'Save', 'users-plus-for-wordpress' ); ?></button>
	</form>
</div>
