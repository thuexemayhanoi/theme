<?php
/**
 * No-checkout rental estimate calculator. Does not promise stock, fees or terms.
 *
 * @package NguyenTuSmartCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Customer-facing shortcode for transparent reference-price estimates.
 */
final class NT_Smart_Rental {

	/**
	 * Register an optional shortcode and lightweight frontend assets.
	 */
	public static function register(): void {
		add_shortcode( 'nguyentu_rental_calculator', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Load self-hosted styles and JavaScript, no external services.
	 */
	public static function assets(): void {
		wp_enqueue_style( 'nt-smart-rental', plugins_url( 'assets/rental.css', NTSC_MAIN_FILE ), array(), NTSC_VERSION );
		wp_enqueue_script( 'nt-smart-rental', plugins_url( 'assets/rental.js', NTSC_MAIN_FILE ), array(), NTSC_VERSION, true );
	}

	/**
	 * Auto-place the estimate in the Nguyễn Tú homepage price table.
	 * Hook: nt_home_prices_after_table. It renders nothing on other hosts.
	 */
	public static function echo_calculator(): void {
		echo self::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All markup and URLs are escaped by render().
	}

	/**
	 * Parse a VND daily-price label, never assume "Liên hệ" is numeric.
	 *
	 * @param mixed $text Published day price.
	 * @return int|null
	 */
	public static function daily_rate( $text ): ?int {
		if ( ! is_string( $text ) || ! preg_match( '/^\s*([\d.,]+)\s*(?:đ|vnđ)/iu', $text, $match ) ) {
			return null;
		}
		$number = (int) preg_replace( '/[^0-9]/', '', $match[1] );
		return 50000 <= $number && $number <= 5000000 ? $number : null;
	}

	/**
	 * Shortcode output. No submission, payments, bookings or stored information.
	 *
	 * @param array<string,mixed> $atts Shortcode arguments (reserved).
	 * @return string
	 */
	public static function render( $atts = array() ): string {
		unset( $atts );
		if ( ! nt_smart_site_allowed() ) {
			return '';
		}
		$b    = nt_smart_business();
		$id   = wp_unique_id( 'ntsc-rental-' );
		$rows = nt_smart_reference_prices();
		ob_start();
		?>
		<section class="ntsc-rental" aria-label="Tính giá thuê xe máy tham khảo">
			<h3>Ước tính tiền thuê xe</h3>
			<div class="ntsc-rental__fields">
				<label for="<?php echo esc_attr( $id . '-vehicle' ); ?>">Dòng xe
					<select id="<?php echo esc_attr( $id . '-vehicle' ); ?>" data-ntsc-vehicle>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							if ( ! is_array( $row ) ) {
								continue;
							}
							$name = (string) ( $row['name'] ?? '' );
							$day  = (string) ( $row['day'] ?? 'Liên hệ' );
							$rate = self::daily_rate( $day );
							?>
							<option value="<?php echo esc_attr( $name ); ?>" data-day-rate="<?php echo esc_attr( null === $rate ? '' : (string) $rate ); ?>"><?php echo esc_html( $name . ' — ' . $day . '/ngày' ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label for="<?php echo esc_attr( $id . '-days' ); ?>">Số ngày dự kiến
					<input id="<?php echo esc_attr( $id . '-days' ); ?>" data-ntsc-days type="number" min="1" max="30" value="1" step="1" inputmode="numeric">
				</label>
			</div>
			<p class="ntsc-rental__result" data-ntsc-result role="status" aria-live="polite">Giá tham khảo theo ngày, chưa tính tiền cọc.</p>
			<p class="ntsc-rental__note">Giá tuần/tháng, tiền cọc, phí giao nhận và tình trạng xe cần cửa hàng xác nhận trước khi thuê.</p>
			<a class="ntsc-rental__link" href="<?php echo esc_url( (string) ( $b['zalo_url'] ?? '' ) ); ?>" target="_blank" rel="noopener noreferrer">Nhắn Zalo xác nhận giá</a>
		</section>
		<?php
		return (string) ob_get_clean();
	}
}
