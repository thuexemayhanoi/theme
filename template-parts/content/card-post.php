<?php
/**
 * Post card. One link target per card (the title); the image link is hidden from assistive tech
 * so screen readers do not announce the same destination twice.
 *
 * Args: heading (h2|h3|h4), eager (bool, above the fold image), lead (bool, larger variant).
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_heading = in_array( $args['heading'] ?? 'h2', array( 'h2', 'h3', 'h4' ), true ) ? $args['heading'] : 'h2';
$nt_lead    = ! empty( $args['lead'] );
$nt_id      = (int) get_the_ID();
?>
<article class="nt-card nt-card--post<?php echo $nt_lead ? ' nt-card--lead' : ''; ?>">
	<a class="nt-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php
		echo nt_card_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image helper / trusted placeholder.
			$nt_id,
			array(
				'eager' => ! empty( $args['eager'] ),
				'size'  => $nt_lead ? 'large' : 'medium_large',
				'sizes' => $nt_lead ? '(min-width: 1024px) 720px, 100vw' : '(min-width: 1024px) 380px, (min-width: 640px) 46vw, 100vw',
			)
		);
		?>
	</a>
	<div class="nt-card__body">
		<?php
		$nt_chips = nt_category_chips( $nt_id, 1 );
		if ( '' !== $nt_chips ) {
			echo '<div class="nt-card__chips">' . $nt_chips . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in nt_category_chips().
		}
		?>
		<<?php echo esc_html( $nt_heading ); ?> class="nt-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></<?php echo esc_html( $nt_heading ); ?>>
		<p class="nt-card__excerpt"><?php echo esc_html( nt_trim_words( wp_strip_all_tags( get_the_excerpt() ), $nt_lead ? 36 : 24 ) ); ?></p>
		<p class="nt-card__meta"><?php echo nt_date_html( $nt_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></p>
	</div>
</article>
