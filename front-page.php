<?php
/**
 * Front page (WP Page #562 as static front page).
 *
 * Content-first and non-destructive:
 *  1. The stored page content is always rendered (legacy shortcodes through the bridge), so no
 *     existing text, heading, link or image is dropped. If that content already has an <h1> the
 *     theme prints none; only when it has none does the native hero provide the H1.
 *  2. Native sections (rental options, vehicle categories, products, prices, process, local info,
 *     FAQ, latest posts) are added in "auto" mode ONLY where the page content does not already
 *     provide that section, so nothing is duplicated. Each can be forced on/off in settings.
 *  3. The quick-facts list lives in the native hero; with authored content that owns the hero and
 *     its H1 it is printed as a strip under the content only when the setting is "on"
 *     (see nt_home_facts_placement()).
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="nt-main nt-home" tabindex="-1">
	<?php
	while ( have_posts() ) :
		the_post();
		// A compact editorial/services home leaves stored legacy Page #562 completely intact.
		// Switch to the legacy view from Appearance > Nguyen Tu to roll back instantly.
		if ( 'editorial' === nt_home_layout() ) {
			get_template_part( 'template-parts/home/editorial' );
			continue;
		}

		$nt_page     = get_post();
		$nt_content  = nt_get_rendered_content();
		$nt_hero     = $nt_page instanceof WP_Post ? nt_home_hero( $nt_page, $nt_content ) : array(
			'content_has_h1' => false,
			'h1'             => get_the_title(),
		);
		$nt_has_body = '' !== trim( wp_strip_all_tags( $nt_content ) ) || false !== stripos( $nt_content, '<img' );
		$nt_facts    = nt_home_facts_placement( $nt_hero['content_has_h1'], nt_toggle( 'home.quickfacts' ) );

		if ( ! $nt_hero['content_has_h1'] ) {
			get_template_part(
				'template-parts/home/hero',
				null,
				array(
					'h1'    => $nt_hero['h1'],
					'lead'  => has_excerpt() ? get_the_excerpt() : '',
					'facts' => 'hero' === $nt_facts,
				)
			);
		}

		if ( $nt_has_body ) :
			?>
			<div class="nt-home__body nt-prose entry-content">
				<?php echo $nt_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content filter output. ?>
			</div>
			<?php
		endif;

		if ( 'strip' === $nt_facts ) :
			?>
			<div class="nt-quickfacts">
				<div class="nt-container"><?php get_template_part( 'template-parts/home/quickfacts' ); ?></div>
			</div>
			<?php
		endif;

		$nt_sections = array( 'rental', 'categories', 'products', 'prices', 'process', 'local', 'faq', 'posts' );
		foreach ( $nt_sections as $nt_section ) {
			if ( nt_section_enabled( $nt_section, $nt_content ) ) {
				get_template_part( 'template-parts/home/' . $nt_section );
			}
		}
	endwhile;
	?>
</main>
<?php
get_footer();
