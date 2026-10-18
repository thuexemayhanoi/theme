<?php
/**
 * Legacy layout shortcodes: section, row, col, gap, divider, title, banner, text box, text.
 *
 * Output uses the theme's own "nt-lg-*" classes and CSS variables (assets/css/legacy.css).
 * Every attribute that reaches CSS goes through the strict sanitisers in inc/lib/css.php.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * [section]
 *
 * @param mixed  $atts    Attributes.
 * @param string $content Inner content.
 */
function nt_lg_section( $atts, $content = '' ): string {
	$a = nt_lg_atts(
		array(
			'label'      => '',
			'bg'         => '',
			'bg_color'   => '',
			'bg_overlay' => '',
			'bg_pos'     => '',
			'dark'       => '',
			'padding'    => '',
			'height'     => '',
			'class'      => '',
			'id'         => '',
			'visibility' => '',
		),
		$atts,
		'section'
	);

	$classes = array_merge(
		array( 'nt-lg-section' ),
		nt_lg_bool( $a['dark'] ) ? array( 'nt-lg-dark' ) : array(),
		'' !== $a['bg'] ? array( 'has-bg' ) : array(),
		nt_css_classes( $a['class'] ),
		nt_lg_visibility_classes( $a['visibility'] )
	);
	$style   = nt_css_style(
		array(
			'--lg-pad'         => nt_css_len( $a['padding'] ),
			'--lg-min-h'       => nt_css_len( $a['height'] ),
			'background-color' => nt_css_color( $a['bg_color'] ),
		)
	);

	$bg = '';
	if ( '' !== $a['bg'] ) {
		$img = nt_lg_image_html(
			$a['bg'],
			array(
				'class'      => 'nt-lg-bg__img',
				'decorative' => true,
			)
		);
		if ( '' !== $img ) {
			$pos = nt_css_position( $a['bg_pos'] );
			$bg  = '<div class="nt-lg-bg" aria-hidden="true"' . ( '' !== $pos ? ' style="--lg-pos:' . esc_attr( $pos ) . '"' : '' ) . '>' . $img . '</div>';
		}
	}
	$overlay_color = nt_css_color( $a['bg_overlay'] );
	$overlay       = '' !== $overlay_color ? '<div class="nt-lg-overlay" aria-hidden="true" style="background-color:' . esc_attr( $overlay_color ) . '"></div>' : '';

	return sprintf(
		'<section class="%1$s"%2$s%3$s>%4$s%5$s<div class="nt-lg-section__inner">%6$s</div></section>',
		nt_lg_class( $classes ),
		'' !== $style ? ' style="' . esc_attr( $style ) . '"' : '',
		nt_lg_id_attr( $a['id'] ),
		$bg,
		$overlay,
		nt_lg_inner( $content )
	);
}

/**
 * [row] and [row_inner]
 *
 * @param mixed  $atts    Attributes.
 * @param string $content Inner content.
 */
function nt_lg_row( $atts, $content = '' ): string {
	$a      = nt_lg_atts(
		array(
			'style'      => '',
			'width'      => '',
			'v_align'    => '',
			'h_align'    => '',
			'class'      => '',
			'visibility' => '',
			'padding'    => '',
			'id'         => '',
			'label'      => '',
		),
		$atts,
		'row'
	);
	$styles = array( 'collapse', 'small', 'large', 'xlarge' );
	$widths = array( 'full-width', 'large', 'small', 'xlarge' );
	$valign = array( 'top', 'middle', 'bottom', 'equal' );
	$halign = array( 'left', 'right', 'center' );

	$classes = array( 'nt-lg-row' );
	if ( in_array( $a['style'], $styles, true ) ) {
		$classes[] = 'nt-lg-row--' . $a['style'];
	}
	if ( in_array( $a['width'], $widths, true ) ) {
		$classes[] = 'nt-lg-row--w-' . $a['width'];
	}
	if ( in_array( $a['v_align'], $valign, true ) ) {
		$classes[] = 'nt-lg-v-' . $a['v_align'];
	}
	if ( in_array( $a['h_align'], $halign, true ) ) {
		$classes[] = 'nt-lg-h-' . $a['h_align'];
	}
	$classes = array_merge( $classes, nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) );
	$pad     = nt_css_box( $a['padding'] );

	return sprintf(
		'<div class="%1$s"%2$s%3$s>%4$s</div>',
		nt_lg_class( $classes ),
		'' !== $pad ? ' style="padding:' . esc_attr( $pad ) . '"' : '',
		nt_lg_id_attr( $a['id'] ),
		nt_lg_inner( $content )
	);
}

/**
 * [col] and [col_inner]
 *
 * @param mixed  $atts    Attributes.
 * @param string $content Inner content.
 */
function nt_lg_col( $atts, $content = '' ): string {
	$a    = nt_lg_atts(
		array(
			'span'       => '12',
			'span__sm'   => '',
			'span__md'   => '',
			'align'      => '',
			'valign'     => '',
			'padding'    => '',
			'bg'         => '',
			'bg_color'   => '',
			'bg_radius'  => '',
			'color'      => '',
			'class'      => '',
			'visibility' => '',
			'max_width'  => '',
			'margin'     => '',
			'id'         => '',
		),
		$atts,
		'col'
	);
	$span = nt_css_int( $a['span'], 1, 12, 12 );
	$md   = '' !== $a['span__md'] ? nt_css_int( $a['span__md'], 1, 12, $span ) : $span;
	$sm   = '' !== $a['span__sm'] ? nt_css_int( $a['span__sm'], 1, 12, 12 ) : 12;

	$classes = array( 'nt-lg-col' );
	if ( in_array( $a['align'], array( 'left', 'center', 'right' ), true ) ) {
		$classes[] = 'nt-lg-align-' . $a['align'];
	}
	if ( in_array( $a['valign'], array( 'top', 'middle', 'bottom' ), true ) ) {
		$classes[] = 'nt-lg-valign-' . $a['valign'];
	}
	if ( in_array( $a['color'], array( 'light', 'dark' ), true ) ) {
		$classes[] = 'light' === $a['color'] ? 'nt-lg-dark' : 'nt-lg-on-light';
	}
	$classes = array_merge( $classes, nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) );

	$style = nt_css_style(
		array(
			'--lg-span'        => (string) $span,
			'--lg-span-md'     => (string) $md,
			'--lg-span-sm'     => (string) $sm,
			'padding'          => nt_css_box( $a['padding'] ),
			'margin'           => nt_css_box( $a['margin'] ),
			'max-width'        => nt_css_len( $a['max_width'] ),
			'background-color' => nt_css_color( $a['bg_color'] ),
			'border-radius'    => nt_css_len( $a['bg_radius'] ),
		)
	);

	return sprintf(
		'<div class="%1$s" style="%2$s"%3$s>%4$s</div>',
		nt_lg_class( $classes ),
		esc_attr( $style ),
		nt_lg_id_attr( $a['id'] ),
		nt_lg_inner( $content )
	);
}

/**
 * [gap]
 *
 * @param mixed $atts Attributes.
 */
function nt_lg_gap( $atts ): string {
	$a     = nt_lg_atts(
		array(
			'height'     => '30px',
			'height__sm' => '',
			'height__md' => '',
			'class'      => '',
			'visibility' => '',
		),
		$atts,
		'gap'
	);
	$h     = nt_css_len( $a['height'], '30px' );
	$sm    = nt_css_len( $a['height__sm'] );
	$md    = nt_css_len( $a['height__md'] );
	$style = nt_css_style(
		array(
			'--lg-gap'    => $h,
			'--lg-gap-md' => '' !== $md ? $md : $h,
			'--lg-gap-sm' => '' !== $sm ? $sm : ( '' !== $md ? $md : $h ),
		)
	);
	return sprintf(
		'<div class="%s" style="%s" aria-hidden="true"></div>',
		nt_lg_class( array_merge( array( 'nt-lg-gap' ), nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) ) ),
		esc_attr( $style )
	);
}

/**
 * [divider]
 *
 * @param mixed $atts Attributes.
 */
function nt_lg_divider( $atts ): string {
	$a       = nt_lg_atts(
		array(
			'width'      => '',
			'height'     => '',
			'margin'     => '',
			'align'      => '',
			'color'      => '',
			'class'      => '',
			'visibility' => '',
		),
		$atts,
		'divider'
	);
	$width   = ( 'full' === $a['width'] || '100%' === $a['width'] ) ? '100%' : nt_css_len( $a['width'] );
	$style   = nt_css_style(
		array(
			'width'            => $width,
			'height'           => nt_css_len( $a['height'] ),
			'margin'           => nt_css_box( $a['margin'] ),
			'background-color' => nt_css_color( $a['color'] ),
		)
	);
	$classes = array( 'nt-lg-divider' );
	if ( in_array( $a['align'], array( 'center', 'right' ), true ) ) {
		$classes[] = 'nt-lg-align-' . $a['align'];
	}
	return sprintf(
		'<hr class="%s"%s>',
		nt_lg_class( array_merge( $classes, nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) ) ),
		'' !== $style ? ' style="' . esc_attr( $style ) . '"' : ''
	);
}

/**
 * [title] keeps the authored heading level (default h3, as in the original markup).
 *
 * @param mixed $atts Attributes.
 */
function nt_lg_title( $atts ): string {
	$a = nt_lg_atts(
		array(
			'text'       => '',
			'sub_text'   => '',
			'style'      => 'normal',
			'size'       => '',
			'tag_name'   => 'h3',
			'link'       => '',
			'link_text'  => '',
			'color'      => '',
			'class'      => '',
			'visibility' => '',
		),
		$atts,
		'title'
	);
	if ( '' === trim( $a['text'] ) ) {
		return '';
	}
	$tag        = strtolower( $a['tag_name'] );
	$tag        = in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p', 'span' ), true ) ? $tag : 'h3';
	$style_name = preg_replace( '/[^a-z0-9\-]/', '', strtolower( $a['style'] ) ) ?: 'normal';
	$size       = ( preg_match( '/^\d{2,3}$/', $a['size'] ) ) ? $a['size'] . '%' : '';
	$style      = nt_css_style(
		array(
			'font-size' => $size,
			'color'     => nt_css_color( $a['color'] ),
		)
	);
	$html       = sprintf(
		'<%1$s class="%2$s"%3$s><span class="nt-lg-title__text">%4$s</span>%5$s</%1$s>',
		$tag,
		nt_lg_class( array_merge( array( 'nt-lg-title', 'nt-lg-title--' . $style_name ), nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) ) ),
		'' !== $style ? ' style="' . esc_attr( $style ) . '"' : '',
		esc_html( $a['text'] ),
		'' !== $a['sub_text'] ? '<small class="nt-lg-title__sub">' . esc_html( $a['sub_text'] ) . '</small>' : ''
	);
	if ( '' !== $a['link'] && '' !== $a['link_text'] ) {
		$html .= sprintf( '<a class="nt-lg-title__link" href="%s">%s</a>', esc_url( $a['link'] ), esc_html( $a['link_text'] ) );
	}
	return $html;
}

/**
 * [ux_banner]
 *
 * @param mixed  $atts    Attributes.
 * @param string $content Inner content (text boxes, images, buttons).
 */
function nt_lg_banner( $atts, $content = '' ): string {
	$a     = nt_lg_atts(
		array(
			'height'     => '300px',
			'height__sm' => '',
			'height__md' => '',
			'bg'         => '',
			'bg_color'   => '',
			'bg_overlay' => '',
			'bg_pos'     => '',
			'link'       => '',
			'target'     => '',
			'class'      => '',
			'visibility' => '',
			'hover'      => '',
			'id'         => '',
		),
		$atts,
		'ux_banner'
	);
	// Only the homepage legacy banner using attachment #369 changes. Stored page content stays untouched.
	$nt_home_photo = is_front_page() && '369' === trim( (string) $a['bg'] );
	if ( $nt_home_photo ) {
		$a['bg'] = 'https://thuexemaynguyentu.com/wp-content/uploads/2026/10/banner-thue-xe-may-ha-noi-nguyen-tu.webp';
	}

	$h     = nt_css_len( $a['height'], '300px' );
	$md    = nt_css_len( $a['height__md'] );
	$sm    = nt_css_len( $a['height__sm'] );
	$style = nt_css_style(
		array(
			'--lg-h'           => $h,
			'--lg-h-md'        => '' !== $md ? $md : $h,
			'--lg-h-sm'        => '' !== $sm ? $sm : ( '' !== $md ? $md : $h ),
			'background-color' => nt_css_color( $a['bg_color'] ),
		)
	);

	$bg = '';
	if ( '' !== $a['bg'] ) {
		// The first banner image of a page is the likely LCP element: eager + high priority.
		$eager = nt_lg_claim_lcp();
		$img   = $nt_home_photo
			? sprintf(
				'<img src="%s" class="nt-lg-bg__img" width="1200" height="800" alt="%s" loading="%s" decoding="async"%s>',
				esc_url( $a['bg'] ),
				esc_attr( 'Thuê xe máy Hà Nội Nguyễn Tú, 112 Nguyễn Văn Cừ, Bồ Đề, Long Biên, Hà Nội' ),
				$eager ? 'eager' : 'lazy',
				$eager ? ' fetchpriority="high"' : ''
			)
			: nt_lg_image_html(
				$a['bg'],
				array(
					'class'      => 'nt-lg-bg__img',
					'decorative' => true,
					'eager'      => $eager,
				)
			);
		if ( '' !== $img ) {
			$pos = nt_css_position( $a['bg_pos'] );
			$bg  = '<div class="nt-lg-bg"' . ( $nt_home_photo ? '' : ' aria-hidden="true"' ) . ( '' !== $pos ? ' style="--lg-pos:' . esc_attr( $pos ) . '"' : '' ) . '>' . $img . '</div>';
		}
	}
	$overlay_color = nt_css_color( $a['bg_overlay'] );
	$overlay       = '' !== $overlay_color ? '<div class="nt-lg-overlay" aria-hidden="true" style="background-color:' . esc_attr( $overlay_color ) . '"></div>' : '';

	$inner = nt_lg_inner( $content );
	$link  = '';
	if ( '' !== $a['link'] ) {
		$label = nt_html_first_h1_text( $inner );
		if ( '' === $label && preg_match( '#<h[1-6][^>]*>(.*?)</h[1-6]>#is', $inner, $hm ) ) {
			$label = nt_plain_text( $hm[1] );
		}
		$link = sprintf(
			'<a class="nt-lg-banner__link" href="%s" aria-label="%s"%s></a>',
			esc_url( $a['link'] ),
			esc_attr( '' !== $label ? $label : 'Xem chi tiết' ),
			nt_lg_link_attrs( $a['target'] )
		);
	}

	return sprintf(
		'<div class="%1$s" style="%2$s"%3$s>%4$s%5$s%6$s<div class="nt-lg-banner__layers">%7$s</div></div>',
		nt_lg_class( array_merge( array( 'nt-lg-banner' ), $nt_home_photo ? array( 'nt-lg-banner--nguyentu-home-photo' ) : array(), nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) ) ),
		esc_attr( $style ),
		nt_lg_id_attr( $a['id'] ),
		$bg,
		$overlay,
		$link,
		$inner
	);
}

/**
 * [text_box]: absolutely positioned content inside a banner.
 *
 * @param mixed  $atts    Attributes.
 * @param string $content Inner content.
 */
function nt_lg_text_box( $atts, $content = '' ): string {
	$a     = nt_lg_atts(
		array(
			'width'          => '50',
			'width__sm'      => '',
			'position_x'     => '50',
			'position_y'     => '50',
			'position_x__sm' => '',
			'position_y__sm' => '',
			'text_align'     => 'center',
			'text_color'     => '',
			'padding'        => '',
			'class'          => '',
			'visibility'     => '',
		),
		$atts,
		'text_box'
	);
	$w     = nt_css_int( $a['width'], 5, 100, 50 );
	$wsm   = '' !== $a['width__sm'] ? nt_css_int( $a['width__sm'], 5, 100, 100 ) : 100;
	$x     = nt_css_int( $a['position_x'], 0, 100, 50 );
	$y     = nt_css_int( $a['position_y'], 0, 100, 50 );
	$xsm   = '' !== $a['position_x__sm'] ? nt_css_int( $a['position_x__sm'], 0, 100, $x ) : $x;
	$ysm   = '' !== $a['position_y__sm'] ? nt_css_int( $a['position_y__sm'], 0, 100, $y ) : $y;
	$align = in_array( $a['text_align'], array( 'left', 'center', 'right' ), true ) ? $a['text_align'] : 'center';

	// Percent positions become start/center/end placement inside the banner grid, so a tall text
	// block grows the banner instead of overflowing it.
	$place = static function ( int $pct ): string {
		return $pct <= 33 ? 'start' : ( $pct >= 67 ? 'end' : 'center' );
	};

	$classes = array( 'nt-lg-textbox', 'nt-lg-align-' . $align );
	if ( in_array( $a['text_color'], array( 'light', 'dark' ), true ) ) {
		$classes[] = 'light' === $a['text_color'] ? 'nt-lg-dark' : 'nt-lg-on-light';
	}
	$style = nt_css_style(
		array(
			'--lg-w'     => $w . '%',
			'--lg-w-sm'  => $wsm . '%',
			'--lg-jx'    => $place( $x ),
			'--lg-ay'    => $place( $y ),
			'--lg-jx-sm' => $place( $xsm ),
			'--lg-ay-sm' => $place( $ysm ),
			'padding'    => nt_css_box( $a['padding'] ),
		)
	);
	return sprintf(
		'<div class="%s" style="%s"><div class="nt-lg-textbox__inner">%s</div></div>',
		nt_lg_class( array_merge( $classes, nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) ) ),
		esc_attr( $style ),
		nt_lg_inner( $content )
	);
}

/**
 * [ux_text]
 *
 * @param mixed  $atts    Attributes.
 * @param string $content Inner content.
 */
function nt_lg_text( $atts, $content = '' ): string {
	$a       = nt_lg_atts(
		array(
			'font_size'   => '',
			'text_align'  => '',
			'text_color'  => '',
			'line_height' => '',
			'width'       => '',
			'class'       => '',
			'visibility'  => '',
		),
		$atts,
		'ux_text'
	);
	$classes = array( 'nt-lg-text' );
	if ( in_array( $a['text_align'], array( 'left', 'center', 'right' ), true ) ) {
		$classes[] = 'nt-lg-align-' . $a['text_align'];
	}
	$style = nt_css_style(
		array(
			'font-size'   => nt_lg_font_size( $a['font_size'] ),
			'color'       => nt_css_color( $a['text_color'] ),
			'line-height' => preg_match( '/^\d(?:\.\d+)?$/', $a['line_height'] ) ? $a['line_height'] : '',
			'max-width'   => preg_match( '/^\d{1,3}$/', $a['width'] ) ? $a['width'] . '%' : '',
		)
	);
	return sprintf(
		'<div class="%s"%s>%s</div>',
		nt_lg_class( array_merge( $classes, nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) ) ),
		'' !== $style ? ' style="' . esc_attr( $style ) . '"' : '',
		nt_lg_inner( $content )
	);
}
