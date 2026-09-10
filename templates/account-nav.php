<?php
/**
 * La navegación del área de cuenta.
 *
 * @var string                              $current
 * @var array<string, array<string, mixed>> $sections
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;
?>
<nav class="upfw-account__nav" aria-label="<?php esc_attr_e( 'Account sections', 'users-plus-for-wordpress' ); ?>">
	<?php foreach ( $sections as $upfw_id => $upfw_section ) : ?>
		<a class="upfw-account__tab <?php echo $upfw_id === $current ? 'is-current' : ''; ?>"
			href="<?php echo esc_url( upfw_account_url( $upfw_id ) ); ?>"
			<?php echo $upfw_id === $current ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $upfw_section['label'] ); ?></a>
	<?php endforeach; ?>
</nav>
