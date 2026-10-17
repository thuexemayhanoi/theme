<?php
/**
 * Comments. Only rendered when comments are open or exist; nothing is fabricated.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="nt-comments" aria-labelledby="nt-comments-title">
	<?php if ( have_comments() ) : ?>
		<h2 id="nt-comments-title" class="nt-comments__title"><?php echo esc_html( number_format_i18n( (int) get_comments_number() ) ); ?> bình luận</h2>
		<ol class="nt-comments__list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 40,
				)
			);
			?>
		</ol>
		<?php the_comments_pagination(); ?>
	<?php endif; ?>

	<?php if ( ! comments_open() && get_comments_number() ) : ?>
		<p class="nt-note">Bình luận đã đóng.</p>
	<?php endif; ?>

	<?php comment_form(); ?>
</section>
