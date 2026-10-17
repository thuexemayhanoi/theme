<?php
/**
 * Featured item card (title, optional image, excerpt, period chip).
 *
 * Args: heading (h2|h3|h4).
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_heading = in_array( $args['heading'] ?? 'h3', array( 'h2', 'h3', 'h4' ), true ) ? $args['heading'] : 'h3';
$nt_id      = (int) get_the_ID();
$nt_terms   = get_the_terms( $nt_id, 'featured_item_category' );
$nt_chip    = '';
if ( is_array( $nt_terms ) ) {
	foreach ( $nt_terms as $nt_term ) {
		if ( '' !== nt_featured_period_for_slug( $nt_term->slug ) ) {
			$nt_chip = $nt_term->name;
			break;
		}
	}
}
?>
<article class="nt-card nt-card--featured">
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="nt-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
			<?php echo nt_card_image( $nt_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image helper. ?>
		</a>
	<?php endif; ?>
	<div class="nt-card__body">
		<?php if ( '' !== $nt_chip ) : ?>
			<div class="nt-card__chips"><span class="nt-chip"><?php echo esc_html( $nt_chip ); ?></span></div>
		<?php endif; ?>
		<<?php echo esc_html( $nt_heading ); ?> class="nt-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></<?php echo esc_html( $nt_heading ); ?>>
		<p class="nt-card__excerpt"><?php echo esc_html( nt_trim_words( wp_strip_all_tags( get_the_excerpt() ), 24 ) ); ?></p>
		<p class="nt-card__more"><a class="nt-link-arrow" href="<?php the_permalink(); ?>">Xem chi tiết <?php nt_the_icon( 'arrow-right', array( 'size' => 16 ) ); ?></a></p>
	</div>
</article>
