<?php
/**
 * SANDBOX HELPER (not theme code): procedural JPEG generation with PHP GD.
 * No external assets, no network. Used by seed.php; also runnable on its own for a preview:
 *   php -d display_errors=1 php/seed-images.php /abs/output/dir      (writes hero.jpg, card.jpg, logo.png)
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'NT_SEED_STANDALONE' ) ) {
	if ( PHP_SAPI === 'cli' && isset( $argv[0] ) && realpath( $argv[0] ) === __FILE__ ) {
		define( 'NT_SEED_STANDALONE', true );
	} else {
		exit( 1 );
	}
}

if ( ! function_exists( 'nt_seed_font' ) ) {

	/** First readable TTF/OTF with Vietnamese glyph coverage (override with NT_SEED_FONT). */
	function nt_seed_font() {
		$candidates = array_filter(
			array(
				getenv( 'NT_SEED_FONT' ),
				'/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
				'/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
				'/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
				'/usr/share/fonts/opentype/inter/Inter-Bold.otf',
			)
		);
		foreach ( $candidates as $f ) {
			if ( is_readable( $f ) ) {
				return $f;
			}
		}
		return '';
	}

	function nt_seed_hsl( $h, $s, $l ) {
		$h = fmod( $h, 360 ) / 360;
		$q = $l < 0.5 ? $l * ( 1 + $s ) : $l + $s - $l * $s;
		$p = 2 * $l - $q;
		$f = static function ( $t ) use ( $p, $q ) {
			if ( $t < 0 ) {
				$t += 1;
			}
			if ( $t > 1 ) {
				$t -= 1;
			}
			if ( $t < 1 / 6 ) {
				return $p + ( $q - $p ) * 6 * $t;
			}
			if ( $t < 1 / 2 ) {
				return $q;
			}
			if ( $t < 2 / 3 ) {
				return $p + ( $q - $p ) * ( 2 / 3 - $t ) * 6;
			}
			return $p;
		};
		return array( (int) round( $f( $h + 1 / 3 ) * 255 ), (int) round( $f( $h ) * 255 ), (int) round( $f( $h - 1 / 3 ) * 255 ) );
	}

	function nt_seed_mix( array $a, array $b, $t ) {
		$t = max( 0, min( 1, $t ) );
		return array(
			(int) round( $a[0] + ( $b[0] - $a[0] ) * $t ),
			(int) round( $a[1] + ( $b[1] - $a[1] ) * $t ),
			(int) round( $a[2] + ( $b[2] - $a[2] ) * $t ),
		);
	}

	/**
	 * Gradient "card" with a wrapped title and a sub label (used for products and blog posts).
	 */
	function nt_seed_make_card( $path, $title, $sub, $seed_text, $w = 1600, $h = 900 ) {
		$im  = imagecreatetruecolor( $w, $h );
		$hue = crc32( $seed_text ) % 360;
		$c1  = nt_seed_hsl( $hue, 0.55, 0.30 );
		$c2  = nt_seed_hsl( $hue + 40, 0.60, 0.52 );
		for ( $y = 0; $y < $h; $y++ ) {
			$c = nt_seed_mix( $c1, $c2, $y / ( $h - 1 ) );
			imageline( $im, 0, $y, $w, $y, imagecolorallocate( $im, $c[0], $c[1], $c[2] ) );
		}
		imagealphablending( $im, true );
		for ( $k = 0; $k < 5; $k++ ) {
			$cx = (int) ( ( ( crc32( $seed_text . $k ) % 1000 ) / 1000 ) * $w );
			$cy = (int) ( ( ( crc32( $k . $seed_text ) % 1000 ) / 1000 ) * $h );
			$r  = 160 + ( crc32( $seed_text . 'r' . $k ) % 260 );
			imagefilledellipse( $im, $cx, $cy, $r * 2, $r * 2, imagecolorallocatealpha( $im, 255, 255, 255, 118 ) );
		}
		$white  = imagecolorallocate( $im, 255, 255, 255 );
		$shadow = imagecolorallocatealpha( $im, 0, 0, 0, 80 );
		$font   = nt_seed_font();
		if ( $font && function_exists( 'imagettftext' ) ) {
			$size  = 58;
			$max_w = (int) ( $w * 0.82 );
			$lines = array();
			$cur   = '';
			foreach ( preg_split( '/\s+/u', $title ) as $word ) {
				$try = '' === $cur ? $word : $cur . ' ' . $word;
				$bb  = imagettfbbox( $size, 0, $font, $try );
				if ( ( $bb[2] - $bb[0] ) > $max_w && '' !== $cur ) {
					$lines[] = $cur;
					$cur     = $word;
				} else {
					$cur = $try;
				}
			}
			$lines[] = $cur;
			$lines   = array_slice( $lines, 0, 3 );
			$line_h  = (int) ( $size * 1.45 );
			$y       = (int) ( ( $h - count( $lines ) * $line_h ) / 2 + $size );
			foreach ( $lines as $line ) {
				$bb = imagettfbbox( $size, 0, $font, $line );
				$x  = (int) ( ( $w - ( $bb[2] - $bb[0] ) ) / 2 );
				imagettftext( $im, $size, 0, $x + 3, $y + 3, $shadow, $font, $line );
				imagettftext( $im, $size, 0, $x, $y, $white, $font, $line );
				$y += $line_h;
			}
			$bb = imagettfbbox( 28, 0, $font, $sub );
			imagettftext( $im, 28, 0, (int) ( ( $w - ( $bb[2] - $bb[0] ) ) / 2 ), $h - 70, $white, $font, $sub );
		} else { // no TTF font available: built-in bitmap font, ASCII only
			$ascii = function_exists( 'remove_accents' ) ? remove_accents( $title ) : $title;
			imagestring( $im, 5, 40, (int) ( $h / 2 ), $ascii, $white );
			imagestring( $im, 3, 40, $h - 50, function_exists( 'remove_accents' ) ? remove_accents( $sub ) : $sub, $white );
		}
		$ok = imagejpeg( $im, $path, 82 );
		imagedestroy( $im );
		if ( ! $ok ) {
			throw new RuntimeException( "Could not write image $path" );
		}
	}

	/**
	 * Square site logo (PNG): navy disc, teal ring and an "NT" monogram. Used as the Yoast Organization
	 * logo so the schema graph contains Organization (Yoast skips it for a company without a logo).
	 */
	function nt_seed_make_logo( $path, $size = 512 ) {
		$im = imagecreatetruecolor( $size, $size );
		imagealphablending( $im, true );
		if ( function_exists( 'imageantialias' ) ) {
			imageantialias( $im, true );
		}
		$bg   = imagecolorallocate( $im, 255, 255, 255 );
		imagefilledrectangle( $im, 0, 0, $size, $size, $bg );
		$navy = imagecolorallocate( $im, 12, 38, 82 );
		$teal = imagecolorallocate( $im, 36, 134, 146 );
		$c    = (int) ( $size / 2 );
		imagefilledellipse( $im, $c, $c, (int) ( $size * 0.94 ), (int) ( $size * 0.94 ), $teal );
		imagefilledellipse( $im, $c, $c, (int) ( $size * 0.86 ), (int) ( $size * 0.86 ), $navy );
		$font = nt_seed_font();
		$cream = imagecolorallocate( $im, 255, 244, 214 );
		if ( $font && function_exists( 'imagettftext' ) ) {
			$fs = (int) ( $size * 0.30 );
			$bb = imagettfbbox( $fs, 0, $font, 'NT' );
			imagettftext( $im, $fs, 0, (int) ( ( $size - ( $bb[2] - $bb[0] ) ) / 2 - $bb[0] ), (int) ( ( $size + ( $bb[1] - $bb[7] ) ) / 2 - $bb[1] ), $cream, $font, 'NT' );
		} else {
			imagestring( $im, 5, $c - 10, $c - 8, 'NT', $cream );
		}
		$ok = imagepng( $im, $path );
		imagedestroy( $im );
		if ( ! $ok ) {
			throw new RuntimeException( "Could not write image $path" );
		}
	}

	/**
	 * Hero image, NO text: dark navy -> teal dusk sky, soft sun on the horizon, layered hills and a
	 * perspective road with lane dashes. Meant to be shown full width (1600x900) under white text.
	 */
	function nt_seed_make_hero( $path, $w = 1600, $h = 900 ) {
		$im = imagecreatetruecolor( $w, $h );
		imagealphablending( $im, true );
		if ( function_exists( 'imageantialias' ) ) {
			imageantialias( $im, true );
		}
		$horizon = (int) round( $h * 0.60 );

		// Sky: deep navy -> blue -> teal glow at the horizon.
		$navy = array( 5, 14, 36 );
		$blue = array( 13, 48, 88 );
		$teal = array( 36, 134, 146 );
		for ( $y = 0; $y <= $horizon; $y++ ) {
			$t = $y / $horizon;
			$c = $t < 0.58 ? nt_seed_mix( $navy, $blue, $t / 0.58 ) : nt_seed_mix( $blue, $teal, ( $t - 0.58 ) / 0.42 );
			imageline( $im, 0, $y, $w - 1, $y, imagecolorallocate( $im, $c[0], $c[1], $c[2] ) );
		}

		// A few faint stars (own LCG so global mt_rand state is untouched).
		$seed = 77;
		$rnd  = static function () use ( &$seed ) {
			$seed = ( $seed * 1103515245 + 12345 ) & 0x7fffffff;
			return $seed / 0x7fffffff;
		};
		for ( $i = 0; $i < 110; $i++ ) {
			$x = (int) ( $rnd() * ( $w - 1 ) );
			$y = (int) ( $rnd() * $horizon * 0.5 );
			$a = 92 + (int) ( $rnd() * 28 );
			imagesetpixel( $im, $x, $y, imagecolorallocatealpha( $im, 255, 255, 255, $a ) );
			if ( $rnd() > 0.82 ) {
				imagefilledellipse( $im, $x, $y, 3, 3, imagecolorallocatealpha( $im, 255, 255, 255, min( 127, $a + 10 ) ) );
			}
		}

		// Sun: subtle warm glow (stacked translucent discs) + soft disc, half sunk behind the hills.
		$sx    = (int) ( $w * 0.70 );
		$sy    = $horizon - (int) ( $h * 0.05 );
		$sun_r = (int) ( $h * 0.085 );
		$glow  = (int) ( $h * 0.46 );
		for ( $r = $glow; $r > $sun_r; $r -= 5 ) {
			$k     = 1 - ( $r - $sun_r ) / ( $glow - $sun_r ); // 0 outside .. 1 near the disc
			$alpha = (int) round( 126.4 - 3.6 * $k * $k - 0.8 * $k );
			imagefilledellipse( $im, $sx, $sy, $r * 2, $r * 2, imagecolorallocatealpha( $im, 255, 205, 150, $alpha ) );
		}
		imagefilledellipse( $im, $sx, $sy, $sun_r * 2 + 14, $sun_r * 2 + 14, imagecolorallocatealpha( $im, 255, 222, 176, 92 ) );
		imagefilledellipse( $im, $sx, $sy, $sun_r * 2, $sun_r * 2, imagecolorallocatealpha( $im, 255, 240, 214, 10 ) );

		// Hills: two translucent ridges in front of the sun.
		$ridges = array(
			array( 0.070, 1.15, 1.9, array( 20, 70, 98 ), 46 ),
			array( 0.048, 1.70, 0.5, array( 9, 36, 58 ), 8 ),
		);
		foreach ( $ridges as $rg ) {
			list( $amp, $freq, $phase, $col, $alpha ) = $rg;
			$pts = array( 0, $horizon + 3 );
			for ( $x = 0; $x <= $w; $x += 8 ) {
				$u     = $x / $w;
				$yy    = $horizon - $amp * $h * ( 0.55 + 0.45 * sin( $u * 6.2832 * $freq + $phase ) ) * ( 0.72 + 0.28 * sin( $u * 17 + $phase * 3 ) );
				$pts[] = $x;
				$pts[] = (int) $yy;
			}
			$pts[] = $w;
			$pts[] = $horizon + 3;
			imagefilledpolygon( $im, $pts, imagecolorallocatealpha( $im, $col[0], $col[1], $col[2], $alpha ) );
		}

		// Ground: dark teal -> near black.
		$g1 = array( 10, 38, 54 );
		$g2 = array( 3, 11, 22 );
		for ( $y = $horizon + 1; $y < $h; $y++ ) {
			$c = nt_seed_mix( $g1, $g2, ( $y - $horizon ) / ( $h - $horizon ) );
			imageline( $im, 0, $y, $w - 1, $y, imagecolorallocate( $im, $c[0], $c[1], $c[2] ) );
		}
		// faint reflection of the sun on the ground
		for ( $r = (int) ( $h * 0.34 ); $r > 20; $r -= 8 ) {
			imagefilledellipse( $im, $sx, $horizon + (int) ( $h * 0.02 ), $r * 3, (int) ( $r * 0.35 ), imagecolorallocatealpha( $im, 255, 170, 100, 125 ) );
		}

		// Road in perspective, vanishing near the sun.
		$vx   = (int) ( $w * 0.64 );
		$bl   = (int) ( $w * 0.02 );
		$br   = (int) ( $w * 0.90 );
		$cxb  = (int) ( ( $bl + $br ) / 2 );
		$roadc = imagecolorallocatealpha( $im, 18, 33, 48, 4 );
		imagefilledpolygon( $im, array( $vx - 5, $horizon, $vx + 5, $horizon, $br, $h, $bl, $h ), $roadc );
		// soft lighter band close to the horizon (haze on the asphalt)
		for ( $i = 0; $i < 28; $i++ ) {
			$y0 = $horizon + $i * 2;
			$t  = ( $y0 - $horizon ) / ( $h - $horizon );
			$xl = (int) ( ( $vx - 5 ) + ( $bl - ( $vx - 5 ) ) * $t );
			$xr = (int) ( ( $vx + 5 ) + ( $br - ( $vx + 5 ) ) * $t );
			imageline( $im, $xl, $y0, $xr, $y0, imagecolorallocatealpha( $im, 120, 190, 190, min( 127, 112 + (int) ( $i * 15 / 27 ) ) ) );
		}
		imagesetthickness( $im, 3 );
		$edge = imagecolorallocatealpha( $im, 150, 205, 210, 78 );
		imageline( $im, $vx - 5, $horizon, $bl, $h, $edge );
		imageline( $im, $vx + 5, $horizon, $br, $h, $edge );
		imagesetthickness( $im, 1 );
		// lane dashes (grow with distance)
		$dash = imagecolorallocatealpha( $im, 255, 244, 214, 50 );
		$n    = 16;
		for ( $i = 1; $i < $n; $i++ ) {
			$u0 = pow( $i / $n, 2.1 );
			$u1 = $u0 + 0.012 + $u0 * 0.075;
			if ( $u1 > 1 ) {
				break;
			}
			$pt = static function ( $u ) use ( $vx, $cxb, $horizon, $h ) {
				return array( $vx + ( $cxb - $vx ) * $u, $horizon + ( $h - $horizon ) * $u, 0.8 + 11 * $u );
			};
			list( $x0, $y0, $w0 ) = $pt( $u0 );
			list( $x1, $y1, $w1 ) = $pt( $u1 );
			imagefilledpolygon( $im, array( (int) ( $x0 - $w0 ), (int) $y0, (int) ( $x0 + $w0 ), (int) $y0, (int) ( $x1 + $w1 ), (int) $y1, (int) ( $x1 - $w1 ), (int) $y1 ), $dash );
		}

		// Vignette: darken the border so white overlay text stays readable.
		$vn = 150;
		for ( $i = 0; $i < $vn; $i++ ) {
			$alpha = 127 - (int) ( 22 * pow( 1 - $i / $vn, 2 ) );
			imagerectangle( $im, $i, $i, $w - 1 - $i, $h - 1 - $i, imagecolorallocatealpha( $im, 2, 8, 20, $alpha ) );
		}

		$ok = imagejpeg( $im, $path, 88 );
		imagedestroy( $im );
		if ( ! $ok ) {
			throw new RuntimeException( "Could not write image $path" );
		}
	}
}

if ( defined( 'NT_SEED_STANDALONE' ) && isset( $argv[1] ) ) {
	$out = rtrim( $argv[1], '/' );
	nt_seed_make_hero( $out . '/hero.jpg' );
	nt_seed_make_card( $out . '/card.jpg', 'Honda Wave', 'Sản phẩm (sandbox)', 'sandbox-product-honda-wave' );
	nt_seed_make_logo( $out . '/logo.png' );
	fwrite( STDOUT, "wrote $out/hero.jpg, $out/card.jpg and $out/logo.png\n" );
}
