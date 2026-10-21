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
			<?php $nt_tripadvisor = (string) nt_business( 'tripadvisor_url' ); ?>
			<?php if ( '' !== $nt_tripadvisor ) : ?>
				<a class="nt-footer__social--tripadvisor" href="<?php echo esc_url( $nt_tripadvisor ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Xem Nguyễn Tú trên Tripadvisor">
					<?php nt_the_icon( 'tripadvisor', array( 'size' => 18 ) ); ?>
					<span>Tripadvisor</span>
				</a>
			<?php endif; ?>
		</nav>
	</section>

	<section class="nt-footer__column nt-footer__column--rental" aria-labelledby="nt-f-rental">
		<details class="nt-footer__disclosure" open>
		<summary id="nt-f-rental" class="nt-footer__title">Thuê xe</summary>
		<nav aria-label="Dịch vụ và bảng giá thuê xe máy">
			<?php nt_footer_list( $nt_footer_groups['rental'] ); ?>
		</nav>
		</details>
	</section>

	<section class="nt-footer__column nt-footer__column--blog" aria-labelledby="nt-f-blog">
		<details class="nt-footer__disclosure" open>
		<summary id="nt-f-blog" class="nt-footer__title">Cẩm nang</summary>
		<nav aria-label="Các chuyên mục hướng dẫn và kinh nghiệm">
			<?php nt_footer_list( $nt_footer_groups['hubs'] ); ?>
		</nav>
		</details>
	</section>

	<section class="nt-footer__column nt-footer__column--contact" aria-labelledby="nt-f-contact">
		<details class="nt-footer__disclosure" open>
		<summary id="nt-f-contact" class="nt-footer__title">Thông tin</summary>
		<nav aria-label="Giới thiệu, liên hệ và chính sách">
			<?php nt_footer_list( $nt_footer_groups['info'] ); ?>
		</nav>
		<?php get_template_part( 'template-parts/footer/contact', null, array( 'mode' => 'actions' ) ); ?>
		</details>
	</section>
</div>
