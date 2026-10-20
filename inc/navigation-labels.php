<?php
/**
 * Shared short display labels for navigation. Stored titles and URLs are untouched.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Human-written labels, shared by the header, mobile drawer and curated footer.
 * Unknown labels pass through so future menu items are never cut off arbitrarily.
 * Keys normalise spelling/case only; they are never used to build a URL.
 *
 * @param string $title Original navigation label.
 */
function nt_navigation_label( string $title ): string {
	static $labels = array(
		'trang-chu'                        => 'Trang chủ',
		'gioi-thieu'                       => 'Giới thiệu',
		've-chung-toi'                     => 'Về chúng tôi',
		'su-menh-tam-nhin'                 => 'Định hướng',
		'blog'                             => 'Cẩm nang',
		'huong-dan-thue-xe'                 => 'Hướng dẫn',
		'thue-xe-may-theo-phuong-ha-noi'     => 'Thuê theo phường',
		'thu-tuc-va-giao-nhan'              => 'Thủ tục',
		'chi-phi-thue-xe'                   => 'Chi phí thuê',
		'su-dung-xe-thue-50cc'              => 'Dùng xe 50cc',
		'chia-se'                          => 'Kinh nghiệm',
		'chia-se-kinh-nghiem'               => 'Kinh nghiệm',
		'lap-ke-hoach-chuyen-di'            => 'Lịch trình',
		'di-chuyen-do-thi'                  => 'Đi trong phố',
		'du-lich-xanh'                      => 'Du lịch xanh',
		'diem-den'                         => 'Điểm đến',
		'diem-den-du-lich'                  => 'Điểm đến',
		'hanh-trinh-viet-nam'               => 'Khắp Việt Nam',
		'am-thuc-va-van-hoa'                => 'Văn hóa',
		'kham-pha-ha-noi'                   => 'Hà Nội',
		'danh-gia-so-sanh-xe'               => 'So sánh xe',
		'xe-dien'                          => 'Xe điện',
		'xe-dien-kinh-nghiem-su-dung'        => 'Xe điện',
		'xe-dap-dien'                      => 'Xe đạp điện',
		'pin-va-sac'                       => 'Sạc pin',
		'cong-nghe-xe-dien'                 => 'Công nghệ',
		'dien-hoa-va-ha-tang'               => 'Hạ tầng điện',
		'cham-soc-xe-dien'                  => 'Chăm xe điện',
		'ky-thuat-bao-duong'                => 'Bảo dưỡng',
		'cham-soc-xe-may'                   => 'Chăm xe máy',
		'phap-ly-kinh-nghiem'               => 'Pháp lý',
		'giay-phep-va-giay-to'              => 'Giấy tờ',
		'an-toan-khi-di-chuyen'             => 'An toàn',
		'loai-xe'                          => 'Loại xe',
		'xe-so'                            => 'Xe số',
		'xe-ga'                            => 'Xe ga',
		'xe-ga-50cc'                       => 'Xe ga 50cc',
		'bang-gia'                         => 'Bảng giá',
		'bang-gia-thue-xe-may'              => 'Bảng giá',
		'thue-ngay'                        => 'Thuê ngày',
		'thue-theo-ngay'                   => 'Thuê ngày',
		'thue-tuan'                        => 'Thuê tuần',
		'thue-theo-tuan'                   => 'Thuê tuần',
		'thue-thang'                       => 'Thuê tháng',
		'thue-theo-thang'                  => 'Thuê tháng',
		'thue-xe-may-dien'                 => 'Xe máy điện',
		'thue-xe-dap-dien'                 => 'Xe đạp điện',
		'hoi-dap'                          => 'Hỏi đáp',
		'cau-hoi-thuong-gap'                => 'Hỏi đáp',
		'lien-he'                          => 'Liên hệ',
		'bao-mat'                          => 'Bảo mật',
		'chinh-sach-bao-mat'                => 'Bảo mật',
		'dieu-khoan'                       => 'Điều khoản',
		'dieu-khoan-su-dung'                => 'Điều khoản',
	);
	return $labels[ nt_slugify( nt_plain_text( $title ) ) ] ?? $title;
}
