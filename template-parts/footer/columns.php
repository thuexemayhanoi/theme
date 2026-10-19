<?php
/**
 * Shared permanent four-column SEO footer: only selected commercial pages and hub terms.
 * URLs resolve from published WordPress objects through inc/footer.php, not the main menu.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_footer_groups = nt_footer_groups();
$nt_tagline       = nt_footer_tagline();
$nt_contact_url   = '';
foreach ( $nt_footer_groups['info'] as $nt_footer_info ) {
	if ( 'Liên hệ' === $nt_footer_info['label'] ) {
		$nt_contact_url = $nt_footer_info['url'];
		break;
	}
}
?>
<div class="nt-footer__grid">
	<section class="nt-footer__column nt-footer__column--brand" aria-labelledby="nt-f-brand">
		<p id="nt-f-brand" class="nt-footer__brand"><?php echo esc_html( nt_business( 'brand' ) ); ?></p>
		<p class="nt-footer__tagline"><?php echo esc_html( $nt_tagline ); ?></p>
		<?php get_template_part( 'template-parts/footer/contact', null, array( 'mode' => 'nap' ) ); ?>
		<nav class="nt-footer__social" aria-label="Mạng xã hội Nguyễn Tú">
			<?php $nt_facebook = (string) nt_business( 'facebook_url' ); ?>
			<?php if ( '' !== $nt_facebook ) : ?>
				<a href="<?php echo esc_url( $nt_facebook ); ?>" target="_blank" rel="noopener noreferrer">Facebook</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( nt_zalo_url() ); ?>" target="_blank" rel="noopener noreferrer">Zalo</a>
			<a href="<?php echo esc_url( nt_whatsapp_url() ); ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
		</nav>
	</section>

	<section class="nt-footer__column nt-footer__column--rental" aria-labelledby="nt-f-rental">
		<h2 id="nt-f-rental" class="nt-footer__title">Thuê xe &amp; bảng giá</h2>
		<nav aria-label="Dịch vụ và bảng giá thuê xe máy">
			<?php nt_footer_list( $nt_footer_groups['rental'] ); ?>
		</nav>
		<?php if ( '' !== $nt_contact_url ) : ?>
			<p class="nt-footer__hint">Cần thuê theo giờ? <a href="<?php echo esc_url( $nt_contact_url ); ?>">Liên hệ để được tư vấn</a> trước khi đặt.</p>
		<?php endif; ?>
	</section>

	<section class="nt-footer__column nt-footer__column--blog" aria-labelledby="nt-f-blog">
		<h2 id="nt-f-blog" class="nt-footer__title">Cẩm nang &amp; Blog</h2>
		<nav aria-label="Các chuyên mục hướng dẫn và kinh nghiệm">
			<?php nt_footer_list( $nt_footer_groups['hubs'] ); ?>
		</nav>
	</section>

	<section class="nt-footer__column nt-footer__column--contact" aria-labelledby="nt-f-contact">
		<h2 id="nt-f-contact" class="nt-footer__title">Liên hệ &amp; thông tin</h2>
		<nav aria-label="Giới thiệu, liên hệ và chính sách">
			<?php nt_footer_list( $nt_footer_groups['info'] ); ?>
		</nav>
		<?php get_template_part( 'template-parts/footer/contact', null, array( 'mode' => 'actions' ) ); ?>
	</section>
</div>
