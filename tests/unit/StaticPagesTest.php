<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StaticPagesTest extends TestCase {
	public function test_decorative_images_leave_no_empty_boxes_but_keep_links_and_captions(): void {
		$html = '<h1>Hỏi đáp</h1><figure><img alt="A > B" src="old.jpg"></figure>'
			. '<picture><source srcset="old.webp"><img src="old.jpg"></picture>'
			. '<figure><img src="old.jpg"><figcaption>Thông tin cần giữ</figcaption></figure>'
			. '<a href="/shop/"><img src="old.jpg"></a><a href="/lien-he/">Liên hệ</a>';
		$out = nt_page_without_decorative_images( $html );
		$this->assertStringNotContainsString( '<img', $out );
		$this->assertStringNotContainsString( '<picture', $out );
		$this->assertStringNotContainsString( '<figure></figure>', $out );
		$this->assertStringContainsString( '<figcaption>Thông tin cần giữ</figcaption>', $out );
		$this->assertStringContainsString( '<a href="/lien-he/">Liên hệ</a>', $out );
		$this->assertStringContainsString( '<h1>Hỏi đáp</h1>', $out );
	}

	public function test_contact_form_images_and_svg_icons_are_functional_and_preserved(): void {
		$form = '<form action="/lien-he/" method="post"><input type="hidden" value="token"><img src="captcha.png" alt="Mã xác minh"><button>Gửi</button></form>';
		$icon = '<svg aria-hidden="true"><path d="M0 0"></path></svg>';
		$this->assertSame( $form . $icon, nt_page_without_decorative_images( $form . $icon ) );
	}
}
