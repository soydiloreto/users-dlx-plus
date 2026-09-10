<?php
/**
 * La portada del área de cuenta: el resumen.
 *
 * @var array<int, array<string, string>> $cards
 *
 * @package UPFW
 */

defined( 'ABSPATH' ) || exit;
?>

<?php if ( array() === $cards ) : ?>
	<p><?php esc_html_e( 'Nothing to show yet. The sections above are all yours.', 'users-plus-for-wordpress' ); ?></p>
<?php else : ?>
	<div class="upfw-cards">
		<?php foreach ( $cards as $upfw_card ) : ?>
			<a class="upfw-card-resumen" href="<?php echo esc_url( $upfw_card['link'] ); ?>">
				<span class="upfw-card-resumen__rotulo"><?php echo esc_html( $upfw_card['label'] ); ?></span>
				<span class="upfw-card-resumen__valor"><?php echo esc_html( $upfw_card['value'] ); ?></span>
				<?php if ( '' !== $upfw_card['note'] ) : ?>
					<span class="upfw-card-resumen__nota"><?php echo esc_html( $upfw_card['note'] ); ?></span>
				<?php endif; ?>
				<span class="upfw-card-resumen__cta"><?php echo esc_html( $upfw_card['cta'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
