<?php
/**
 * Author attribution from real user data only: name, link to their posts and a bio when the
 * profile has one. No avatar service is called and nothing is fabricated.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_author_id = (int) get_the_author_meta( 'ID' );
if ( $nt_author_id <= 0 ) {
	return;
}
$nt_name = get_the_author_meta( 'display_name', $nt_author_id );
$nt_bio  = trim( wp_strip_all_tags( (string) get_the_author_meta( 'description', $nt_author_id ) ) );
?>
<aside class="nt-authorbox" aria-label="Về tác giả">
	<span class="nt-authorbox__badge" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( (string) $nt_name, 0, 1 ) ) ); ?></span>
	<div class="nt-authorbox__text">
		<p class="nt-authorbox__name">Tác giả: <a href="<?php echo esc_url( get_author_posts_url( $nt_author_id ) ); ?>" rel="author"><?php echo esc_html( (string) $nt_name ); ?></a></p>
		<?php if ( '' !== $nt_bio ) : ?>
			<p class="nt-authorbox__bio"><?php echo esc_html( $nt_bio ); ?></p>
		<?php endif; ?>
	</div>
</aside>
