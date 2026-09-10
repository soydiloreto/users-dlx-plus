<?php
/**
 * La navegación del área de cuenta.
 *
 * @var string                              $current
 * @var array<string, array<string, mixed>> $sections
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;
?>
<nav class="users-plus-account__nav" aria-label="<?php esc_attr_e( 'Account sections', 'users-plus' ); ?>">
	<?php foreach ( $sections as $users_plus_id => $users_plus_section ) : ?>
		<a class="users-plus-account__tab <?php echo $users_plus_id === $current ? 'is-current' : ''; ?>"
			href="<?php echo esc_url( users_plus_account_url( $users_plus_id ) ); ?>"
			<?php echo $users_plus_id === $current ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $users_plus_section['label'] ); ?></a>
	<?php endforeach; ?>
</nav>
