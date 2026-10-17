<?php
/**
 * Table of contents built from the real H2/H3 headings of the post (never invented).
 * Two variants share one data set: a collapsible block above the text on small screens and a
 * sticky list in the sidebar on wide screens (CSS switches between them).
 *
 * Args: items (array of id/text/level), variant (inline|sticky).
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_items   = (array) ( $args['items'] ?? array() );
$nt_variant = 'sticky' === ( $args['variant'] ?? 'inline' ) ? 'sticky' : 'inline';
if ( array() === $nt_items ) {
	return;
}

/**
 * Print the ordered list.
 *
 * @param array<int,array{id:string,text:string,level:int}> $items Items.
 */
$nt_list = static function ( array $items ): void {
	echo '<ol class="nt-toc__list">';
	foreach ( $items as $item ) {
		printf(
			'<li class="nt-toc__item nt-toc__item--l%1$d"><a href="#%2$s" data-nt-toc-link>%3$s</a></li>',
			(int) $item['level'],
			esc_attr( $item['id'] ),
			esc_html( $item['text'] )
		);
	}
	echo '</ol>';
};
?>
<?php if ( 'inline' === $nt_variant ) : ?>
	<details class="nt-toc nt-toc--inline">
		<summary class="nt-toc__summary">Mục lục bài viết</summary>
		<?php $nt_list( $nt_items ); ?>
	</details>
<?php else : ?>
	<nav class="nt-toc nt-toc--sticky" aria-label="Mục lục bài viết" data-nt-toc>
		<p class="nt-toc__title">Mục lục</p>
		<?php $nt_list( $nt_items ); ?>
	</nav>
<?php endif; ?>
