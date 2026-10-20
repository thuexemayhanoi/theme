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
<body id="nt-page-top" <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#main">Bỏ qua và đến nội dung chính</a>

<?php get_template_part( 'template-parts/header/topbar' ); ?>

<header id="site-header" class="nt-header">
	<?php // The scrim lives inside the header's stacking context so the drawer (z-index above it) is not covered by it. ?>
	<div class="nt-scrim" data-nt-scrim hidden></div>
	<div class="nt-container nt-header__inner">
		<?php get_template_part( 'template-parts/header/brand' ); ?>

		<?php // A separate menu on the mobile location replaces the main one in the drawer; the markup carries both, CSS shows one. ?>
		<?php $nt_split_nav = nt_has_distinct_mobile_menu(); ?>
		<nav id="nt-nav" class="nt-nav<?php echo $nt_split_nav ? ' nt-nav--split' : ''; ?>" aria-label="Menu chính" data-nt-nav>
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
				<?php if ( $nt_split_nav ) : ?>
					<div class="nt-nav__mobile-only"><?php nt_render_menu( 'primary_mobile' ); ?></div>
				<?php endif; ?>
			</div>
		</nav>

		<div class="nt-header__actions">
			<span class="nt-hours-indicator" role="img" data-nt-hours-status
				data-open="<?php echo esc_attr( nt_business( 'open' ) ); ?>"
				data-close="<?php echo esc_attr( nt_business( 'close' ) ); ?>"
				aria-label="Đang kiểm tra giờ hoạt động" title="Đang kiểm tra giờ hoạt động">
				<span class="nt-hours-indicator__dot" aria-hidden="true"></span>
			</span>
			<button type="button" class="nt-iconbtn nt-theme-toggle" data-nt-theme-toggle
				aria-label="Đổi giao diện sáng hoặc tối" aria-pressed="false" title="Đổi giao diện sáng hoặc tối">
				<svg class="nt-mode-icon nt-mode-icon--moon" width="21" height="21" viewBox="0 0 24 24"
					fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
					stroke-linejoin="round" aria-hidden="true" focusable="false">
					<path d="M20.5 15.2A8.5 8.5 0 0 1 8.8 3.5 8.5 8.5 0 1 0 20.5 15.2Z"></path>
				</svg>
				<svg class="nt-mode-icon nt-mode-icon--sun" width="21" height="21" viewBox="0 0 24 24"
					fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
					stroke-linejoin="round" aria-hidden="true" focusable="false">
					<circle cx="12" cy="12" r="4"></circle>
					<path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"></path>
				</svg>
			</button>
			<button type="button" class="nt-iconbtn" data-nt-search-toggle aria-expanded="false" aria-controls="nt-search-panel">
				<?php nt_the_icon( 'search', array( 'size' => 22 ) ); ?>
				<span class="screen-reader-text">Mở ô tìm kiếm</span>
			</button>
			<?php
			if ( function_exists( 'nt_wc_header_cart' ) ) {
				nt_wc_header_cart();
			}
			?>
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
