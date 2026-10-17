<?php
/**
 * Contact card shown beside (never inside) an article. Neutral wording, verified NAP only, no
 * exact-match marketing anchors injected into the reading text.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="nt-ctabox" aria-labelledby="nt-ctabox-title">
	<h2 id="nt-ctabox-title" class="nt-ctabox__title">Cần thuê xe máy ở Hà Nội?</h2>
	<p class="nt-ctabox__text"><?php echo esc_html( nt_business( 'brand' ) ); ?> &middot; <?php echo esc_html( nt_address( false ) ); ?> &middot; Mở cửa <?php echo esc_html( nt_hours_range() ); ?>.</p>
	<?php
	nt_cta_buttons(
		array(
			'size'  => 'md',
			'class' => 'nt-cta--stack',
		)
	);
	?>
	<p class="nt-note"><?php echo esc_html( nt_policy()['confirm'] ); ?></p>
</section>
