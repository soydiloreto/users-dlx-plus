<?php
/**
 * La portada del área de cuenta: el resumen.
 *
 * @var array<int, array<string, string>> $cards
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;
?>

<?php if ( array() === $cards ) : ?>
	<p><?php esc_html_e( 'Nothing to show yet. The sections above are all yours.', 'users-plus' ); ?></p>
<?php else : ?>
	<div class="users-plus-cards">
		<?php foreach ( $cards as $users_plus_card ) : ?>
			<a class="users-plus-card-resumen" href="<?php echo esc_url( $users_plus_card['link'] ); ?>">
				<span class="users-plus-card-resumen__rotulo"><?php echo esc_html( $users_plus_card['label'] ); ?></span>
				<span class="users-plus-card-resumen__valor"><?php echo esc_html( $users_plus_card['value'] ); ?></span>
				<?php if ( '' !== $users_plus_card['note'] ) : ?>
					<span class="users-plus-card-resumen__nota"><?php echo esc_html( $users_plus_card['note'] ); ?></span>
				<?php endif; ?>
				<span class="users-plus-card-resumen__cta"><?php echo esc_html( $users_plus_card['cta'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
