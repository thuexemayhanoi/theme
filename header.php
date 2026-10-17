<?php
/**
 * Site header: skip link, optional top bar, sticky header with the stored primary menu, search
 * panel, call button and off-canvas behaviour (the same nav element restyled on small screens).
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#main">Bỏ qua và đến nội dung chính</a>

<?php get_template_part( 'template-parts/header/topbar' ); ?>

<header id="site-header" class="nt-header">
	<div class="nt-container nt-header__inner">
		<?php get_template_part( 'template-parts/header/brand' ); ?>

		<nav id="nt-nav" class="nt-nav" aria-label="Menu chính" data-nt-nav>
			<div class="nt-nav__head">
				<span class="nt-nav__title">Menu</span>
				<button type="button" class="nt-iconbtn nt-nav__close" data-nt-menu-close aria-label="Đóng menu">
					<?php nt_the_icon( 'close', array( 'size' => 24 ) ); ?>
				</button>
			</div>
			<div class="nt-nav__scroll">
				<?php
				if ( ! nt_render_menu( 'primary' ) ) {
					?>
					<p class="nt-nav__empty">Menu chính chưa được gán. Vào Giao diện &rsaquo; Menu để gán menu cho vị trí &ldquo;Menu chính&rdquo;.</p>
					<?php
				}
				?>
				<?php if ( nt_has_distinct_mobile_menu() ) : ?>
					<div class="nt-nav__mobile-only"><?php nt_render_menu( 'primary_mobile' ); ?></div>
				<?php endif; ?>
				<div class="nt-nav__foot">
					<?php nt_cta_buttons( array( 'size' => 'md', 'class' => 'nt-cta--stack' ) ); ?>
					<address class="nt-nav__nap">
						<strong><?php echo esc_html( nt_business( 'brand' ) ); ?></strong><br>
						<?php echo esc_html( nt_address() ); ?><br>
						Mở cửa <?php echo esc_html( nt_hours_range() ); ?> mỗi ngày
					</address>
				</div>
			</div>
		</nav>

		<div class="nt-header__actions">
			<button type="button" class="nt-iconbtn" data-nt-search-toggle aria-expanded="false" aria-controls="nt-search-panel">
				<?php nt_the_icon( 'search', array( 'size' => 22 ) ); ?>
				<span class="screen-reader-text">Mở ô tìm kiếm</span>
			</button>
			<?php
			if ( function_exists( 'nt_wc_header_cart' ) ) {
				nt_wc_header_cart();
			}
			?>
			<a class="nt-btn nt-btn--call nt-btn--sm nt-header__call" href="<?php echo esc_url( nt_tel_url() ); ?>">
				<?php nt_the_icon( 'phone', array( 'size' => 18 ) ); ?>
				<span class="nt-header__call-text">Gọi ngay</span>
			</a>
			<button type="button" class="nt-iconbtn nt-menu-toggle" data-nt-menu-toggle aria-expanded="false" aria-controls="nt-nav">
				<?php nt_the_icon( 'menu', array( 'size' => 26 ) ); ?>
				<span class="screen-reader-text">Mở menu</span>
			</button>
		</div>
	</div>

	<div id="nt-search-panel" class="nt-searchpanel" hidden>
		<div class="nt-container"><?php get_search_form(); ?></div>
	</div>
</header>
<div class="nt-scrim" data-nt-scrim hidden></div>
