<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static guards for the theme as a whole: it is an independent, self-contained theme, every PHP
 * file is guarded against direct access, identifiers are prefixed, and the SEO contract
 * (Yoast owns metadata and schema) cannot be broken by a careless edit.
 */
final class StructureTest extends TestCase {

	/**
	 * Theme + plugin PHP files that are shipped (not tests, tools, vendor).
	 *
	 * @return string[]
	 */
	private static function shipped_php(): array {
		$root  = NT_TEST_ROOT;
		$skip  = '#^(vendor|node_modules|tests|tools|docs|reports|dist|\.git|\.github)/#';
		$files = array();
		$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			$rel = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
			if ( 'php' === $file->getExtension() && ! preg_match( $skip, $rel ) ) {
				$files[] = $rel;
			}
		}
		sort( $files );
		return $files;
	}

	public function test_required_theme_files_exist(): void {
		foreach ( array( 'style.css', 'index.php', 'functions.php', 'front-page.php', 'single.php', 'page.php', 'home.php', 'archive.php', 'category.php', 'search.php', '404.php', 'header.php', 'footer.php', 'comments.php', 'searchform.php', 'theme.json', 'single-featured_item.php', 'taxonomy-featured_item_category.php' ) as $file ) {
			$this->assertFileExists( NT_TEST_ROOT . '/' . $file, $file );
		}
	}

	public function test_style_css_header_declares_an_independent_theme(): void {
		$css = (string) file_get_contents( NT_TEST_ROOT . '/style.css' );
		$this->assertMatchesRegularExpression( '/^Theme Name:\s*Nguyễn Tú Editorial Commerce$/mu', $css );
		$this->assertMatchesRegularExpression( '/^Text Domain:\s*nguyentu$/m', $css );
		$this->assertMatchesRegularExpression( '/^Requires PHP:\s*8\.2$/m', $css );
		$this->assertMatchesRegularExpression( '/^Requires at least:\s*7\.1$/m', $css );
		$this->assertDoesNotMatchRegularExpression( '/^\s*Template:/m', $css, 'A "Template:" header would make this a child theme.' );
	}

	public function test_no_flatsome_runtime_dependency(): void {
		foreach ( self::shipped_php() as $file ) {
			$code = (string) file_get_contents( NT_TEST_ROOT . '/' . $file );
			// Strip comments so prose mentioning the old theme does not count.
			$code = preg_replace( '#/\*.*?\*/|(?<![:\'"])//[^\n]*#s', '', $code ) ?? $code;
			$this->assertDoesNotMatchRegularExpression( '/\bflatsome_[a-z_]+\s*\(/i', $code, "{$file} calls a Flatsome function" );
			$this->assertDoesNotMatchRegularExpression( '#get_template_directory\(\)\s*\.\s*[\'"]/(?:inc/)?flatsome#i', $code, $file );
			$this->assertDoesNotMatchRegularExpression( '/wp_get_theme\(\s*[\'"]flatsome/i', $code, $file );
		}
	}

	public function test_every_shipped_php_file_is_guarded_against_direct_access(): void {
		foreach ( self::shipped_php() as $file ) {
			$head = implode( "\n", array_slice( explode( "\n", (string) file_get_contents( NT_TEST_ROOT . '/' . $file ) ), 0, 60 ) );
			$this->assertTrue(
				str_contains( $head, "defined( 'ABSPATH' )" ) || str_contains( $head, "defined('ABSPATH')" ) || str_contains( $head, 'WP_UNINSTALL_PLUGIN' ),
				"{$file} lacks the ABSPATH guard"
			);
		}
	}

	public function test_global_identifiers_are_prefixed(): void {
		foreach ( self::shipped_php() as $file ) {
			if ( str_starts_with( $file, 'template-parts/' ) || 'inc/icons-data.php' === $file ) {
				continue;
			}
			$code = (string) file_get_contents( NT_TEST_ROOT . '/' . $file );
			if ( preg_match_all( '/^function\s+(\w+)\s*\(/m', $code, $m ) ) {
				foreach ( $m[1] as $name ) {
					$this->assertMatchesRegularExpression( '/^nt_/', $name, "{$file}: function {$name}() is not prefixed" );
				}
			}
			if ( preg_match_all( '/^(?:final\s+)?class\s+(\w+)/m', $code, $m ) ) {
				foreach ( $m[1] as $name ) {
					$this->assertMatchesRegularExpression( '/^NT_/', $name, "{$file}: class {$name} is not prefixed" );
				}
			}
		}
	}

	public function test_theme_never_prints_its_own_structured_data_or_social_tags(): void {
		foreach ( self::shipped_php() as $file ) {
			if ( str_starts_with( $file, 'plugins/' ) ) {
				continue;
			}
			$code = (string) file_get_contents( NT_TEST_ROOT . '/' . $file );
			$this->assertStringNotContainsString( 'application/ld+json', $code, "{$file}: JSON-LD must come from Yoast only" );
			$this->assertStringNotContainsString( 'og:title', $code, $file );
			$this->assertStringNotContainsString( 'twitter:card', $code, $file );
			$this->assertDoesNotMatchRegularExpression( '/itemtype\s*=\s*["\']https?:\/\/schema\.org/i', $code, "{$file}: microdata would compete with Yoast's graph" );
		}
	}

	public function test_canonical_and_meta_description_are_only_emitted_by_the_guarded_fallback(): void {
		$offenders = array();
		foreach ( self::shipped_php() as $file ) {
			if ( str_starts_with( $file, 'plugins/' ) ) {
				continue;
			}
			$code = (string) file_get_contents( NT_TEST_ROOT . '/' . $file );
			if ( preg_match( '/rel="canonical"|name="description"/', $code ) && 'inc/seo.php' !== $file ) {
				$offenders[] = $file;
			}
		}
		$this->assertSame( array(), $offenders );

		$seo = (string) file_get_contents( NT_TEST_ROOT . '/inc/seo.php' );
		$this->assertMatchesRegularExpression( '/function nt_seo_fallback_head\(\): void \{\s*if \( nt_seo_plugin_active\(\) \) \{\s*return;/', $seo, 'The head fallback must return immediately when an SEO plugin is active.' );
	}

	public function test_banned_marketing_claims_are_absent_from_theme_copy(): void {
		$banned = array( 'miễn phí giao', 'không cần đặt cọc', 'không cần cọc', '24/7', 'mới 100%', 'hàng trăm đánh giá', '5 sao', 'aggregaterating', 'ratingvalue', 'reviewcount' );
		foreach ( self::shipped_php() as $file ) {
			$code = mb_strtolower( (string) file_get_contents( NT_TEST_ROOT . '/' . $file ) );
			foreach ( $banned as $phrase ) {
				$this->assertStringNotContainsString( $phrase, $code, "{$file} contains banned claim: {$phrase}" );
			}
		}
	}

	public function test_css_color_tokens_match_theme_json_palette(): void {
		$css  = (string) file_get_contents( NT_TEST_ROOT . '/assets/css/main.css' );
		$json = json_decode( (string) file_get_contents( NT_TEST_ROOT . '/theme.json' ), true );
		$map  = array(
			'cream'     => '--nt-cream',
			'paper'     => '--nt-paper',
			'sand'      => '--nt-sand',
			'ink'       => '--nt-ink',
			'navy'      => '--nt-navy',
			'navy-soft' => '--nt-navy-100',
			'accent'    => '--nt-accent',
			'success'   => '--nt-success',
		);
		foreach ( $json['settings']['color']['palette'] as $entry ) {
			$token = $map[ $entry['slug'] ] ?? null;
			$this->assertNotNull( $token, "palette slug {$entry['slug']} has no CSS token" );
			$this->assertSame( 1, preg_match( '/' . preg_quote( $token, '/' ) . ':\s*(#[0-9a-f]{3,6})\s*;/i', $css, $m ), $token );
			$this->assertSame( self::normalise_hex( $entry['color'] ), self::normalise_hex( $m[1] ), "{$token} differs from theme.json" );
		}
	}

	private static function normalise_hex( string $hex ): string {
		$hex = strtolower( ltrim( $hex, '#' ) );
		return 3 === strlen( $hex ) ? $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2] : $hex;
	}

	public function test_menu_locations_keep_the_legacy_slugs(): void {
		$setup = (string) file_get_contents( NT_TEST_ROOT . '/inc/setup.php' );
		foreach ( array( 'primary', 'primary_mobile', 'secondary', 'footer', 'top_bar_nav', 'my_account', 'vertical' ) as $slug ) {
			$this->assertStringContainsString( "'{$slug}'", $setup, "menu location {$slug} must stay registered" );
		}
	}
}
