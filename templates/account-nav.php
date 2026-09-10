<?php
/**
 * La navegación del área de cuenta.
 *
 * @var string                              $current
 * @var array<string, array<string, mixed>> $sections
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;
?>
<nav class="users-dlx-plus-account__nav" aria-label="<?php esc_attr_e( 'Account sections', 'users-dlx-plus' ); ?>">
	<?php foreach ( $sections as $users_dlx_plus_id => $users_dlx_plus_section ) : ?>
		<a class="users-dlx-plus-account__tab <?php echo $users_dlx_plus_id === $current ? 'is-current' : ''; ?>"
			href="<?php echo esc_url( users_dlx_plus_account_url( $users_dlx_plus_id ) ); ?>"
			<?php echo $users_dlx_plus_id === $current ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $users_dlx_plus_section['label'] ); ?></a>
	<?php endforeach; ?>
</nav>
