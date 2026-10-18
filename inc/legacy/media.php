<?php
/**
 * Legacy media shortcodes: image, image box, gallery, video, slider, map.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Map Flatsome image size names to WordPress sizes.
 *
 * @param string $size Attribute value.
 */
function nt_lg_wp_size( string $size ): string {
	$size = strtolower( trim( $size ) );
	if ( 'original' === $size || 'full' === $size ) {
		return 'full';
	}
	return in_array( $size, array( 'thumbnail', 'medium', 'medium_large', 'large' ), true ) ? $size : 'large';
}

/**
 * [ux_image]
 *
 * @param mixed $atts Attributes.
 */
function nt_lg_image( $atts ): string {
	$a   = nt_lg_atts(
		array(
			'id'         => '',
			'image_size' => 'large',
			'width'      => '',
			'link'       => '',
			'target'     => '',
			'caption'    => '',
			'class'      => '',
			'visibility' => '',
			'height'     => '',
			'margin'     => '',
			'position_x' => '',
		),
		$atts,
		'ux_image'
	);
	$img = nt_lg_image_html(
		$a['id'],
		array(
			'size'  => nt_lg_wp_size( $a['image_size'] ),
			'class' => 'nt-lg-img',
			'sizes' => '(min-width: 850px) 50vw, 100vw',
		)
	);
	if ( '' === $img ) {
		return '';
	}
	if ( '' !== $a['link'] ) {
		$img = sprintf( '<a href="%s"%s>%s</a>', esc_url( $a['link'] ), nt_lg_link_attrs( $a['target'] ), $img );
	}
	$style   = nt_css_style(
		array(
			'max-width' => preg_match( '/^\d{1,3}$/', $a['width'] ) ? $a['width'] . '%' : '',
			'margin'    => nt_css_box( $a['margin'] ),
		)
	);
	$caption = '' !== $a['caption'] ? '<figcaption>' . esc_html( $a['caption'] ) . '</figcaption>' : '';
	return sprintf(
		'<figure class="%s"%s>%s%s</figure>',
		nt_lg_class( array_merge( array( 'nt-lg-image' ), nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) ) ),
		'' !== $style ? ' style="' . esc_attr( $style ) . '"' : '',
		$img,
		$caption
	);
}

/**
 * [ux_image_box]: image with text, optionally one link around the whole card.
 *
 * @param mixed  $atts    Attributes.
 * @param string $content Text content.
 */
function nt_lg_image_box( $atts, $content = '' ): string {
	$a           = nt_lg_atts(
		array(
			'img'          => '',
			'img_width'    => '',
			'image_size'   => 'medium_large',
			'image_radius' => '',
			'style'        => 'default',
			'link'         => '',
			'target'       => '',
			'text_align'   => '',
			'text_padding' => '',
			'text_color'   => '',
			'class'        => '',
			'visibility'   => '',
		),
		$atts,
		'ux_image_box'
	);
	$img         = nt_lg_image_html(
		$a['img'],
		array(
			'size'  => nt_lg_wp_size( $a['image_size'] ),
			'class' => 'nt-lg-imagebox__img',
			'sizes' => '(min-width: 850px) 33vw, 100vw',
		)
	);
	$text        = nt_lg_inner( $content );
	$style_name  = preg_replace( '/[^a-z0-9\-]/', '', strtolower( $a['style'] ) ) ?: 'default';
	$has_link    = '' !== $a['link'];
	$inner_links = false !== stripos( $text, '<a ' );

	$classes = array( 'nt-lg-imagebox', 'nt-lg-imagebox--' . $style_name );
	if ( in_array( $a['text_align'], array( 'left', 'center', 'right' ), true ) ) {
		$classes[] = 'nt-lg-align-' . $a['text_align'];
	}
	if ( in_array( $a['text_color'], array( 'light', 'dark' ), true ) ) {
		$classes[] = 'light' === $a['text_color'] ? 'nt-lg-dark' : 'nt-lg-on-light';
	}
	$media_style = nt_css_style(
		array(
			'width'         => preg_match( '/^\d{1,3}$/', $a['img_width'] ) ? $a['img_width'] . '%' : '',
			'border-radius' => nt_css_len( $a['image_radius'] ),
		)
	);
	$media       = '' !== $img ? '<div class="nt-lg-imagebox__media"' . ( '' !== $media_style ? ' style="' . esc_attr( $media_style ) . '"' : '' ) . '>' . $img . '</div>' : '';
	$body        = '<div class="nt-lg-imagebox__text"' . ( '' !== nt_css_box( $a['text_padding'] ) ? ' style="padding:' . esc_attr( nt_css_box( $a['text_padding'] ) ) . '"' : '' ) . '>' . $text . '</div>';
	$class       = nt_lg_class( array_merge( $classes, nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) ) );

	// One link for the whole card when the text has no links of its own (nested links are invalid).
	// It is a stretched link inside a block wrapper: an <a> wrapped around block elements is split
	// by wpautop into "<p><a></p> ... <p></a>", which leaves an empty, nameless link.
	if ( $has_link && ! $inner_links ) {
		return sprintf(
			'<div class="%s">%s%s<div class="nt-lg-imagebox__hit"><a class="nt-lg-imagebox__link" href="%s"%s aria-label="%s"></a></div></div>',
			$class,
			$media,
			$body,
			esc_url( $a['link'] ),
			nt_lg_link_attrs( $a['target'] ),
			esc_attr( nt_lg_link_label( $text, $img, $a['link'] ) )
		);
	}
	if ( $has_link ) {
		$media = '' !== $img ? '<div class="nt-lg-imagebox__media"><a href="' . esc_url( $a['link'] ) . '"' . nt_lg_link_attrs( $a['target'] ) . ' tabindex="-1" aria-hidden="true">' . $img . '</a></div>' : '';
	}
	return sprintf( '<div class="%s">%s%s</div>', $class, $media, $body );
}

/**
 * [ux_gallery ids="1,2,3"]
 *
 * @param mixed $atts Attributes.
 */
function nt_lg_gallery( $atts ): string {
	$a   = nt_lg_atts(
		array(
			'ids'        => '',
			'columns'    => '3',
			'image_size' => 'medium_large',
			'class'      => '',
			'visibility' => '',
		),
		$atts,
		'ux_gallery'
	);
	$ids = array_filter( array_map( 'intval', explode( ',', $a['ids'] ) ) );
	if ( array() === $ids ) {
		return '';
	}
	$cols  = nt_css_int( $a['columns'], 1, 6, 3 );
	$items = '';
	foreach ( array_slice( $ids, 0, 24 ) as $id ) {
		$img = nt_lg_image_html(
			(string) $id,
			array(
				'size'  => nt_lg_wp_size( $a['image_size'] ),
				'class' => 'nt-lg-img',
				'sizes' => '(min-width: 850px) 30vw, 50vw',
			)
		);
		if ( '' !== $img ) {
			$items .= '<li>' . $img . '</li>';
		}
	}
	return sprintf(
		'<ul class="%s" style="--lg-cols:%d">%s</ul>',
		nt_lg_class( array_merge( array( 'nt-lg-gallery' ), nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) ) ),
		$cols,
		$items
	);
}

/**
 * [ux_video url="https://youtu.be/..."] as a click-to-load facade (no iframe, no request,
 * until the visitor asks for the video). A plain link remains for no-JS and crawlers.
 *
 * @param mixed $atts Attributes.
 */
function nt_lg_video( $atts ): string {
	$a   = nt_lg_atts(
		array(
			'url'        => '',
			'class'      => '',
			'visibility' => '',
		),
		$atts,
		'ux_video'
	);
	$url = esc_url( $a['url'] );
	if ( '' === $url ) {
		return '';
	}
	$provider = '';
	$vid      = '';
	if ( preg_match( '#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $url, $m ) ) {
		$provider = 'youtube';
		$vid      = $m[1];
	} elseif ( preg_match( '#vimeo\.com/(?:video/)?(\d+)#', $url, $m ) ) {
		$provider = 'vimeo';
		$vid      = $m[1];
	}
	if ( '' === $provider ) {
		return sprintf( '<p class="nt-lg-video-link"><a href="%1$s" target="_blank" rel="noopener">%1$s</a></p>', $url );
	}
	return sprintf(
		'<div class="%s" data-nt-video="%s" data-id="%s"><a class="nt-lg-video__link" href="%s" target="_blank" rel="noopener"><span class="nt-lg-video__play">%s<span class="screen-reader-text">Xem video</span></span></a></div>',
		nt_lg_class( array_merge( array( 'nt-lg-video' ), nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) ) ),
		esc_attr( $provider ),
		esc_attr( $vid ),
		$url,
		nt_icon( 'arrow-right', array( 'size' => 28 ) )
	);
}

/**
 * [ux_slider]: a native scroll-snap strip. Every slide stays in the DOM, so no text is hidden
 * from crawlers; no JavaScript is required.
 *
 * @param mixed  $atts    Attributes.
 * @param string $content Slides.
 */
function nt_lg_slider( $atts, $content = '' ): string {
	$a = nt_lg_atts(
		array(
			'class'      => '',
			'visibility' => '',
		),
		$atts,
		'ux_slider'
	);
	return sprintf(
		'<div class="%s" role="region" aria-label="Trình chiếu, cuộn ngang để xem thêm" tabindex="0">%s</div>',
		nt_lg_class( array_merge( array( 'nt-lg-slider' ), nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) ) ),
		nt_lg_inner( $content )
	);
}

/**
 * [map]: never loads a third-party script. Shows the authored text plus the verified Maps link.
 *
 * @param mixed  $atts    Attributes.
 * @param string $content Address text.
 */
function nt_lg_map( $atts, $content = '' ): string {
	$a    = nt_lg_atts(
		array(
			'class'      => '',
			'visibility' => '',
		),
		$atts,
		'map'
	);
	$text = nt_lg_inner( $content );
	return sprintf(
		'<div class="%s">%s<p><a class="nt-btn nt-btn--ghost nt-btn--md" href="%s" target="_blank" rel="noopener">%s<span>Mở Google Maps</span></a></p></div>',
		nt_lg_class( array_merge( array( 'nt-lg-map' ), nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) ) ),
		$text,
		esc_url( nt_maps_url() ),
		nt_icon( 'pin', array( 'size' => 20 ) )
	);
}
