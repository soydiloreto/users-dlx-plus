<?php
/**
 * Los campos de la persona, para editarlos.
 *
 * Reemplazable desde el tema en:
 *   wp-content/themes/<tu-tema>/users-plus/datos.php
 *
 * @var int                              $user_id
 * @var array<int, array<string, mixed>> $fields
 * @var string                           $group
 * @var string                           $title
 * @var string                           $state
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;

if ( array() === $fields ) {
	return;
}
?>
<div class="users-plus users-plus-fields">

	<?php if ( '' !== $title ) : ?>
		<h2 class="users-plus-fields__title"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>

	<?php if ( 'saved' === $state ) : ?>
		<p class="users-plus-notice users-plus-notice--ok"><?php esc_html_e( 'Saved.', 'users-plus' ); ?></p>
	<?php elseif ( 'missing' === $state ) : ?>
		<p class="users-plus-notice users-plus-notice--error"><?php esc_html_e( 'Some required fields are missing.', 'users-plus' ); ?></p>
	<?php endif; ?>

	<form class="users-plus-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="users_plus_fields_save">
		<input type="hidden" name="users_plus_group" value="<?php echo esc_attr( $group ); ?>">
		<?php wp_nonce_field( 'users_plus_fields_save' ); ?>

		<?php foreach ( $fields as $users_plus_field ) : ?>
			<div class="users-plus-field users-plus-field--<?php echo esc_attr( $users_plus_field['type'] ); ?>">
				<?php if ( 'checkbox' !== $users_plus_field['type'] ) : ?>
					<label for="<?php echo esc_attr( $users_plus_field['key'] ); ?>">
						<?php echo esc_html( $users_plus_field['label'] ); ?>
						<?php if ( $users_plus_field['required'] ) : ?>
							<span class="users-plus-field__required" aria-hidden="true">*</span>
						<?php endif; ?>
					</label>
				<?php endif; ?>

				<?php users_plus_field_input( $users_plus_field, users_plus_value( $user_id, $users_plus_field['key'] ) ); ?>

				<?php if ( '' !== $users_plus_field['help'] ) : ?>
					<p class="users-plus-field__help"><?php echo esc_html( $users_plus_field['help'] ); ?></p>
				<?php endif; ?>

				<?php $users_plus_nota = users_plus_field_edit_note( $users_plus_field, $user_id ); ?>
				<?php if ( '' !== $users_plus_nota ) : ?>
					<p class="users-plus-field__help users-plus-field__limite"><?php echo esc_html( $users_plus_nota ); ?></p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

		<button type="submit" class="users-plus-button"><?php esc_html_e( 'Save', 'users-plus' ); ?></button>
	</form>
</div>
