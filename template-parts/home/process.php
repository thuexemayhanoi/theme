<?php
/**
 * Booking process and trust points. Every sentence comes from the shop's published policy and
 * verified facts (address, hours); no ratings, review counts or unverifiable promises.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_steps = nt_home_process_steps();
$nt_trust = nt_home_trust_points();
?>
<section class="nt-section nt-section--navy" aria-labelledby="nt-process-title">
	<div class="nt-container">
		<header class="nt-section__head">
			<p class="nt-eyebrow">Thủ tục thuê xe</p>
			<h2 id="nt-process-title" class="nt-section__title">Quy trình thuê xe rõ ràng</h2>
		</header>
		<ol class="nt-steps">
			<?php foreach ( $nt_steps as $nt_i => $nt_step ) : ?>
				<li class="nt-step">
					<span class="nt-step__num" aria-hidden="true"><?php echo esc_html( (string) ( $nt_i + 1 ) ); ?></span>
					<h3 class="nt-step__title"><?php echo esc_html( $nt_step['title'] ); ?></h3>
					<p class="nt-step__text"><?php echo esc_html( $nt_step['text'] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ol>
		<ul class="nt-trust">
			<?php foreach ( $nt_trust as $nt_point ) : ?>
				<li class="nt-trust__item">
					<span class="nt-trust__icon" aria-hidden="true"><?php nt_the_icon( $nt_point['icon'], array( 'size' => 24 ) ); ?></span>
					<div>
						<h3 class="nt-trust__title"><?php echo esc_html( $nt_point['title'] ); ?></h3>
						<p class="nt-trust__text"><?php echo esc_html( $nt_point['text'] ); ?></p>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
