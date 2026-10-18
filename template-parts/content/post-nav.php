<?php
/**
 * Previous / next post inside the same topic cluster (shared category), published posts only.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_prev = get_previous_post( true, '', 'category' );
$nt_next = get_next_post( true, '', 'category' );
if ( ! $nt_prev instanceof WP_Post && ! $nt_next instanceof WP_Post ) {
	return;
}
?>
<nav class="nt-postnav" aria-label="Bài viết trước và sau">
	<?php if ( $nt_prev instanceof WP_Post ) : ?>
		<a class="nt-postnav__link nt-postnav__link--prev" href="<?php echo esc_url( (string) get_permalink( $nt_prev ) ); ?>" rel="prev">
			<span class="nt-postnav__label">Bài trước</span>
			<span class="nt-postnav__title"><?php echo esc_html( get_the_title( $nt_prev ) ); ?></span>
		</a>
	<?php endif; ?>
	<?php if ( $nt_next instanceof WP_Post ) : ?>
		<a class="nt-postnav__link nt-postnav__link--next" href="<?php echo esc_url( (string) get_permalink( $nt_next ) ); ?>" rel="next">
			<span class="nt-postnav__label">Bài sau</span>
			<span class="nt-postnav__title"><?php echo esc_html( get_the_title( $nt_next ) ); ?></span>
		</a>
	<?php endif; ?>
</nav>
