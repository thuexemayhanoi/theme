<?php
/**
 * Table of contents built from the real H2/H3 headings of the post (never invented).
 * Two variants share one data set: a collapsible block inside the text on small screens (single.php
 * puts it right after the first <h2>) and a sticky list in the sidebar on wide screens (CSS switches
 * between them, so only one of them is ever visible).
 *
 * Args: items (array of id/text/level), variant (inline|sticky), placement (after-first-h2 |
 * after-intro | before-content: where single.php put the inline block; exposed as a data attribute).
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_items     = (array) ( $args['items'] ?? array() );
$nt_variant   = 'sticky' === ( $args['variant'] ?? 'inline' ) ? 'sticky' : 'inline';
$nt_placement = in_array( $args['placement'] ?? '', array( 'after-first-h2', 'after-intro', 'before-content' ), true ) ? (string) $args['placement'] : 'before-content';
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
	<details class="nt-toc nt-toc--inline" data-nt-toc-placement="<?php echo esc_attr( $nt_placement ); ?>">
		<summary class="nt-toc__summary">Mục lục bài viết</summary>
		<?php $nt_list( $nt_items ); ?>
	</details>
<?php else : ?>
	<nav class="nt-toc nt-toc--sticky" aria-label="Mục lục bài viết" data-nt-toc>
		<p class="nt-toc__title">Mục lục</p>
		<?php $nt_list( $nt_items ); ?>
	</nav>
<?php endif; ?>
