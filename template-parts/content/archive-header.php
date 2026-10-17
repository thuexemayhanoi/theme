<?php
/**
 * Archive header: breadcrumb, term H1, genuine term description, and sub-category chips.
 * Nothing is invented for empty descriptions.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_head     = nt_archive_heading();
$nt_children = nt_child_terms();
?>
<header class="nt-archive-head">
	<?php nt_breadcrumbs(); ?>
	<h1 class="nt-archive-head__title"><?php echo esc_html( html_entity_decode( $nt_head['title'], ENT_QUOTES, 'UTF-8' ) ); ?></h1>
	<?php if ( '' !== trim( wp_strip_all_tags( $nt_head['description'] ) ) ) : ?>
		<div class="nt-archive-head__desc nt-prose"><?php echo wp_kses_post( $nt_head['description'] ); ?></div>
	<?php endif; ?>
	<?php if ( array() !== $nt_children ) : ?>
		<nav class="nt-subcats" aria-label="Chuyên mục con">
			<ul>
				<?php foreach ( $nt_children as $nt_child ) : ?>
					<?php $nt_link = get_term_link( $nt_child ); ?>
					<?php if ( is_string( $nt_link ) ) : ?>
						<li><a class="nt-chip nt-chip--lg" href="<?php echo esc_url( $nt_link ); ?>"><?php echo esc_html( html_entity_decode( $nt_child->name, ENT_QUOTES, 'UTF-8' ) ); ?> <span class="nt-chip__count"><?php echo esc_html( (string) $nt_child->count ); ?></span></a></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>
</header>
