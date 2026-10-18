<?php
/**
 * Short FAQ built only from verified facts (see nt_faq_items()). Visible content only: no
 * FAQPage markup is emitted by the theme (Yoast owns structured data).
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_items = nt_faq_items();
$nt_faq   = 'publish' === get_post_status( 484 ) ? (string) get_permalink( 484 ) : '';
?>
<section class="nt-section nt-section--tint" aria-labelledby="nt-faq-title">
	<div class="nt-container nt-container--narrow">
		<header class="nt-section__head">
			<p class="nt-eyebrow">Giải đáp nhanh</p>
			<h2 id="nt-faq-title" class="nt-section__title">Câu hỏi thường gặp khi thuê xe máy</h2>
		</header>
		<div class="nt-faq">
			<?php foreach ( $nt_items as $nt_item ) : ?>
				<details class="nt-faq__item">
					<summary class="nt-faq__q"><?php echo esc_html( $nt_item['q'] ); ?></summary>
					<div class="nt-faq__a"><p><?php echo esc_html( $nt_item['a'] ); ?></p></div>
				</details>
			<?php endforeach; ?>
		</div>
		<?php if ( '' !== $nt_faq ) : ?>
			<p class="nt-faq__more"><a class="nt-link-arrow" href="<?php echo esc_url( $nt_faq ); ?>">Xem tất cả câu hỏi thường gặp <?php nt_the_icon( 'arrow-right', array( 'size' => 18 ) ); ?></a></p>
		<?php endif; ?>
	</div>
</section>
