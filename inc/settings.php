<?php
/**
 * Admin settings page (Giao diện > Nguyễn Tú) built on the Settings API.
 *
 * Only presentation options live here (contact overrides, reference price table, home
 * section toggles). Nothing on this screen can publish content, call an external API or
 * touch SEO metadata, and no secret is ever stored.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Business fields an admin may override (blank keeps the verified default).
 *
 * @return array<string,array{label:string,type:string}>
 */
function nt_settings_business_fields(): array {
	return array(
		'brand'           => array(
			'label' => 'Tên thương hiệu',
			'type'  => 'text',
		),
		'phone_display'   => array(
			'label' => 'Số điện thoại hiển thị',
			'type'  => 'text',
		),
		'phone_tel'       => array(
			'label' => 'Số điện thoại cho liên kết tel:',
			'type'  => 'tel',
		),
		'phone_intl'      => array(
			'label' => 'Số điện thoại quốc tế (schema)',
			'type'  => 'tel',
		),
		'zalo_url'        => array(
			'label' => 'Liên kết Zalo',
			'type'  => 'url',
		),
		'facebook_url'    => array(
			'label' => 'Trang Facebook chính thức',
			'type'  => 'url',
		),
		'tripadvisor_url' => array(
			'label' => 'Trang Tripadvisor chính thức',
			'type'  => 'url',
		),
		'maps_url'        => array(
			'label' => 'Liên kết Google Maps',
			'type'  => 'url',
		),
		'street'          => array(
			'label' => 'Số nhà, đường',
			'type'  => 'text',
		),
		'ward'            => array(
			'label' => 'Phường/Xã',
			'type'  => 'text',
		),
		'district'        => array(
			'label' => 'Quận/Huyện',
			'type'  => 'text',
		),
		'city'            => array(
			'label' => 'Tỉnh/Thành phố',
			'type'  => 'text',
		),
		'open'            => array(
			'label' => 'Giờ mở cửa (HH:MM)',
			'type'  => 'text',
		),
		'close'           => array(
			'label' => 'Giờ đóng cửa (HH:MM)',
			'type'  => 'text',
		),
	);
}

/**
 * Home sections that can be forced on/off (default "auto" avoids duplicating page content).
 *
 * @return array<string,string>
 */
function nt_settings_home_sections(): array {
	return array(
		'quickfacts' => 'Dải thông tin nhanh (giờ mở cửa, địa chỉ, gọi/Zalo)',
		'rental'     => 'Lựa chọn thuê theo ngày / tuần / tháng',
		'prices'     => 'Bảng giá tham khảo',
		'categories' => 'Danh mục xe (xe số / xe ga / xe điện)',
		'products'   => 'Sản phẩm WooCommerce',
		'process'    => 'Quy trình thuê xe',
		'posts'      => 'Bài viết mới',
		'faq'        => 'Câu hỏi thường gặp',
		'local'      => 'Địa chỉ, giờ mở cửa và bản đồ',
	);
}

/**
 * Sanitize the whole option array.
 *
 * @param mixed $input Raw submitted value.
 * @return array<string,mixed>
 */
function nt_sanitize_options( $input ): array {
	$input = is_array( $input ) ? $input : array();
	$out   = array();

	$out['business'] = array();
	foreach ( nt_settings_business_fields() as $key => $field ) {
		$raw = isset( $input['business'][ $key ] ) ? wp_unslash( (string) $input['business'][ $key ] ) : '';
		if ( 'url' === $field['type'] ) {
			$val = esc_url_raw( trim( $raw ), array( 'https', 'http' ) );
		} elseif ( 'tel' === $field['type'] ) {
			$val = nt_phone_digits( $raw );
		} else {
			$val = sanitize_text_field( $raw );
		}
		if ( in_array( $key, array( 'open', 'close' ), true ) && ! preg_match( '/^\d{2}:\d{2}$/', $val ) ) {
			$val = '';
		}
		$out['business'][ $key ] = $val;
	}

	$out['prices'] = isset( $input['prices'] ) ? sanitize_textarea_field( wp_unslash( (string) $input['prices'] ) ) : '';

	// Footer copy is presentation-only. It does not update the WP site description or SEO fields.
	$out['footer'] = array(
		'tagline' => isset( $input['footer']['tagline'] ) ? sanitize_text_field( wp_unslash( (string) $input['footer']['tagline'] ) ) : '',
	);

	$toggle = static function ( $value, array $allowed, string $default ): string {
		$value = is_string( $value ) ? $value : '';
		return in_array( $value, $allowed, true ) ? $value : $default;
	};

	$out['home'] = array();
	foreach ( array_keys( nt_settings_home_sections() ) as $section ) {
		$out['home'][ $section ] = $toggle( $input['home'][ $section ] ?? '', array( 'auto', 'on', 'off' ), 'auto' );
	}

	$out['cta_bar']          = $toggle( $input['cta_bar'] ?? '', array( 'auto', 'on', 'off' ), 'auto' );
	$out['native_quickcall'] = $toggle( $input['native_quickcall'] ?? '', array( 'auto', 'off' ), 'auto' );
	$out['native_assistant'] = $toggle( $input['native_assistant'] ?? '', array( 'auto', 'off' ), 'auto' );
	$out['assist_buttons']   = array();
	foreach ( array( 'call', 'zalo', 'whatsapp', 'chat', 'maps' ) as $key ) {
		$out['assist_buttons'][ $key ] = $toggle( $input['assist_buttons'][ $key ] ?? '', array( 'on', 'off' ), 'maps' === $key ? 'off' : 'on' );
	}
	$out['toc']         = $toggle( $input['toc'] ?? '', array( 'auto', 'off' ), 'auto' );
	$out['header_cart'] = $toggle( $input['header_cart'] ?? '', array( 'auto', 'off' ), 'auto' );
	$out['post_cta']    = $toggle( $input['post_cta'] ?? '', array( 'on', 'off' ), 'on' );
	$out['share']       = $toggle( $input['share'] ?? '', array( 'on', 'off' ), 'on' );
	$out['lean_woo_js'] = $toggle( $input['lean_woo_js'] ?? '', array( 'on', 'off' ), 'off' );
	$out['schema']      = array( 'local_business' => empty( $input['schema']['local_business'] ) ? '0' : '1' );

	return $out;
}

/**
 * Register the option and the page.
 */
function nt_settings_register(): void {
	register_setting(
		'nguyentu',
		'nguyentu_options',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'nt_sanitize_options',
			'default'           => array(),
			'show_in_rest'      => false,
		)
	);
}
add_action( 'admin_init', 'nt_settings_register' );

/**
 * Add the menu entry under Appearance.
 */
function nt_settings_menu(): void {
	add_theme_page( 'Nguyễn Tú', 'Nguyễn Tú', 'manage_options', 'nguyentu', 'nt_settings_page' );
}
add_action( 'admin_menu', 'nt_settings_menu' );

/**
 * One <select> for a toggle.
 *
 * @param string               $name    Field name.
 * @param string               $current Current value.
 * @param array<string,string> $options value => label.
 */
function nt_settings_select( string $name, string $current, array $options ): void {
	printf( '<select name="%s">', esc_attr( $name ) );
	foreach ( $options as $value => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
	}
	echo '</select>';
}

/**
 * Environment checks shown on the settings page (and used by WP-CLI `nguyentu status`).
 *
 * @return array<int,array{label:string,ok:bool,detail:string}>
 */
function nt_status_checks(): array {
	$seo      = nt_seo_status();
	$checks   = array();
	$checks[] = array(
		'label'  => 'Plugin SEO sở hữu thẻ <head>',
		'ok'     => '' !== $seo['seo_plugin'],
		'detail' => '' !== $seo['seo_plugin'] ? $seo['seo_plugin'] . ' ' . $seo['yoast_version'] : 'Không có: theme đang dùng bản dự phòng tối thiểu',
	);
	$checks[] = array(
		'label'  => 'Yoast breadcrumbs',
		'ok'     => true,
		'detail' => $seo['yoast_breadcrumbs'] ? 'Bật (dùng breadcrumb của Yoast)' : 'Tắt (dùng breadcrumb của theme, không có schema)',
	);
	$checks[] = array(
		'label'  => 'WooCommerce',
		'ok'     => class_exists( 'WooCommerce' ),
		'detail' => class_exists( 'WooCommerce' ) ? 'Đang hoạt động' : 'Chưa kích hoạt',
	);
	$checks[] = array(
		'label'  => 'Featured item (bảng giá) đã đăng ký',
		'ok'     => nt_featured_available(),
		'detail' => nt_featured_available() ? 'Có: post type + taxonomy hoạt động' : 'Chưa: cài và bật plugin nguyentu-legacy-compat TRƯỚC khi đổi theme',
	);
	$checks[] = array(
		'label'  => 'Moto QuickCall',
		'ok'     => true,
		'detail' => nt_quickcall_active() ? 'Có: theme không vẽ thanh liên hệ cố định thứ hai' : 'Không có: theme tự hiển thị thanh liên hệ mobile',
	);
	foreach ( array( 'primary', 'primary_mobile', 'footer', 'top_bar_nav' ) as $location ) {
		$id       = nt_resolve_menu_id( $location );
		$count    = $id > 0 ? count( (array) wp_get_nav_menu_items( $id ) ) : 0;
		$checks[] = array(
			'label'  => 'Vị trí menu: ' . $location,
			'ok'     => 'footer' === $location || 'top_bar_nav' === $location ? true : $id > 0,
			'detail' => $id > 0 ? sprintf( 'Menu #%d, %d mục', $id, $count ) : 'Chưa gán menu',
		);
	}
	return $checks;
}

/**
 * Render the settings page.
 */
function nt_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$opts     = get_option( 'nguyentu_options', array() );
	$opts     = is_array( $opts ) ? $opts : array();
	$defaults = nt_business_defaults();
	$modes    = array(
		'auto' => 'Tự động (ẩn nếu nội dung trang đã có)',
		'on'   => 'Luôn hiện',
		'off'  => 'Luôn ẩn',
	);
	?>
	<div class="wrap">
		<h1>Nguyễn Tú Editorial Commerce</h1>
		<p>Cài đặt trình bày. Theme không ghi vào bài viết, danh mục, menu, SEO hay cơ sở dữ liệu nội dung.</p>

		<h2>Trạng thái tương thích</h2>
		<table class="widefat striped" style="max-width:900px">
			<tbody>
			<?php foreach ( nt_status_checks() as $check ) : ?>
				<tr>
					<td style="width:32%"><?php echo esc_html( $check['label'] ); ?></td>
					<td style="width:6%"><?php echo $check['ok'] ? '✅' : '⚠️'; ?></td>
					<td><?php echo esc_html( $check['detail'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<form method="post" action="options.php">
			<?php settings_fields( 'nguyentu' ); ?>

			<h2>Thông tin doanh nghiệp</h2>
			<p>Để trống để dùng dữ liệu chuẩn đã xác nhận. Chỉ thay đổi khi có thông tin mới từ chủ cửa hàng.</p>
			<table class="form-table" role="presentation"><tbody>
			<?php foreach ( nt_settings_business_fields() as $key => $field ) : ?>
				<tr>
					<th scope="row"><label for="nt-b-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
					<td><input type="text" class="regular-text" id="nt-b-<?php echo esc_attr( $key ); ?>" name="nguyentu_options[business][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) ( $opts['business'][ $key ] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( $defaults[ $key ] ?? '' ); ?>"></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>

			<h2>Mô tả riêng tại chân trang (footer)</h2>
			<p>Chỉ thay đổi dòng mô tả dưới thương hiệu tại chân trang trên mọi trang và bài viết. Không sửa mô tả website chung, SEO Title hoặc Meta Description. Để trống để dùng câu chuẩn của Nguyễn Tú.</p>
			<p><label for="nt-footer-tagline">Mô tả dịch vụ tại footer</label></p>
			<textarea id="nt-footer-tagline" name="nguyentu_options[footer][tagline]" rows="3" class="large-text" placeholder="<?php echo esc_attr( nt_footer_tagline_default() ); ?>"><?php echo esc_textarea( (string) ( $opts['footer']['tagline'] ?? '' ) ); ?></textarea>

			<h2>Quick Call và trợ lý tư vấn</h2>
			<p>Chế độ tự động giữ nguyên plugin cũ cho đến khi chủ website vô hiệu hóa plugin ấy. Không hiển thị hai giao diện trùng nhau. Trợ lý tra cứu bài viết công khai, không phải AI tạo sinh trả phí.</p>
			<table class="form-table" role="presentation"><tbody>
			<tr><th scope="row">Quick Call của theme</th><td><?php nt_settings_select( 'nguyentu_options[native_quickcall]', (string) ( $opts['native_quickcall'] ?? 'auto' ), array(
					'auto' => 'Tự bật khi plugin Moto QuickCall ngừng',
					'off'  => 'Tắt hoàn toàn',
				) ); ?></td></tr>
			<tr><th scope="row">Trợ lý tra cứu của theme</th><td><?php nt_settings_select( 'nguyentu_options[native_assistant]', (string) ( $opts['native_assistant'] ?? 'auto' ), array(
					'auto' => 'Tự bật khi plugin MotoAI ngừng',
					'off'  => 'Tắt hoàn toàn',
				) ); ?></td></tr>
			<?php foreach ( array(
				'call'     => 'Gọi điện',
				'zalo'     => 'Zalo',
				'whatsapp' => 'WhatsApp',
				'chat'     => 'Trợ lý tư vấn',
				'maps'     => 'Google Maps',
			) as $nt_key => $nt_label ) : ?>
				<tr><th scope="row"><?php echo esc_html( $nt_label ); ?></th><td><?php nt_settings_select( 'nguyentu_options[assist_buttons][' . $nt_key . ']', (string) ( $opts['assist_buttons'][ $nt_key ] ?? ( 'maps' === $nt_key ? 'off' : 'on' ) ), array(
					'on'  => 'Bật',
					'off' => 'Tắt',
				) ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>

			<h2>Bảng giá tham khảo</h2>
			<p>Mỗi dòng một loại xe, các cột cách nhau bằng dấu <code>|</code>: <code>Loại xe | Theo ngày | Theo tuần | Theo tháng | Tiền cọc</code>. Dòng bắt đầu bằng <code>#</code> bị bỏ qua. Để trống để dùng bảng giá chuẩn của cửa hàng. Chỉ nhập giá đã công bố; giá xe điện chưa có thì ghi "Liên hệ".</p>
			<textarea name="nguyentu_options[prices]" rows="9" class="large-text code" placeholder="<?php echo esc_attr( nt_format_price_lines( nt_price_defaults() ) ); ?>"><?php echo esc_textarea( (string) ( $opts['prices'] ?? '' ) ); ?></textarea>

			<h2>Trang chủ</h2>
			<p>Chế độ "Tự động" chỉ thêm một mục khi nội dung của trang chủ (page #562) chưa có mục đó, nhờ vậy không lặp nội dung.</p>
			<table class="form-table" role="presentation"><tbody>
			<?php foreach ( nt_settings_home_sections() as $key => $label ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $label ); ?></th>
					<td><?php nt_settings_select( 'nguyentu_options[home][' . $key . ']', (string) ( $opts['home'][ $key ] ?? 'auto' ), $modes ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>

			<h2>Hiển thị</h2>
			<table class="form-table" role="presentation"><tbody>
				<tr><th scope="row">Thanh liên hệ cố định trên mobile</th><td>
				<?php
				nt_settings_select(
					'nguyentu_options[cta_bar]',
					(string) ( $opts['cta_bar'] ?? 'auto' ),
					array(
						'auto' => 'Tự động (ẩn nếu có Moto QuickCall)',
						'on'   => 'Luôn bật',
						'off'  => 'Tắt',
					)
				);
				?>
																				</td></tr>
				<tr><th scope="row">Mục lục bài viết</th><td>
				<?php
				nt_settings_select(
					'nguyentu_options[toc]',
					(string) ( $opts['toc'] ?? 'auto' ),
					array(
						'auto' => 'Tự động (khi có từ 3 tiêu đề H2/H3)',
						'off'  => 'Tắt',
					)
				);
				?>
																</td></tr>
				<tr><th scope="row">Liên kết giỏ hàng trên header</th><td>
				<?php
				nt_settings_select(
					'nguyentu_options[header_cart]',
					(string) ( $opts['header_cart'] ?? 'auto' ),
					array(
						'auto' => 'Tự động (chỉ khi giỏ có hàng)',
						'off'  => 'Tắt',
					)
				);
				?>
																			</td></tr>
				<tr><th scope="row">Khung liên hệ cuối bài viết</th><td>
				<?php
				nt_settings_select(
					'nguyentu_options[post_cta]',
					(string) ( $opts['post_cta'] ?? 'on' ),
					array(
						'on'  => 'Bật',
						'off' => 'Tắt',
					)
				);
				?>
																		</td></tr>
				<tr><th scope="row">Nút chia sẻ</th><td>
				<?php
				nt_settings_select(
					'nguyentu_options[share]',
					(string) ( $opts['share'] ?? 'on' ),
					array(
						'on'  => 'Bật',
						'off' => 'Tắt',
					)
				);
				?>
														</td></tr>
				<tr><th scope="row">Script jQuery của WooCommerce</th><td>
				<?php
				nt_settings_select(
					'nguyentu_options[lean_woo_js]',
					(string) ( $opts['lean_woo_js'] ?? 'off' ),
					array(
						'off' => 'Nạp trên mọi trang (mặc định của WooCommerce, an toàn nhất)',
						'on'  => 'Chỉ nạp ở trang cửa hàng, sản phẩm, giỏ hàng, thanh toán, tài khoản',
					)
				);
				?>
				<p class="description">Bật để bỏ jQuery và các script Woo phụ thuộc jQuery khỏi trang chủ, blog, trang thường (nhanh hơn). Theo dõi nguồn đơn hàng của Woo được giữ nguyên. Chỉ bật sau khi kiểm tra trên staging rằng mọi đoạn mã tùy chỉnh (WPCode, plugin chat/gọi) không cần jQuery.</p>
				</td></tr>
			</tbody></table>

			<h2>SEO</h2>
			<p>Yoast SEO vẫn là nguồn duy nhất của title, meta, canonical, robots, sitemap và schema. Tùy chọn dưới đây chỉ bổ sung các thuộc tính LocalBusiness đã xác minh còn thiếu vào nút Organization của Yoast (không tạo thực thể thứ hai).</p>
			<label><input type="checkbox" name="nguyentu_options[schema][local_business]" value="1" <?php checked( '0' !== (string) ( $opts['schema']['local_business'] ?? '1' ) ); ?>> Bổ sung LocalBusiness (điện thoại, địa chỉ, giờ mở cửa, bản đồ) vào schema Organization của Yoast</label>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
