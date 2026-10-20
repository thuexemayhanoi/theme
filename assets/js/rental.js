/*
 * Theme-owned read-only estimate. Published package prices, separate deposit.
 * No requests, persistence or HTML injection.
 */
(function () {
	'use strict';
	function initRental() {
		document.querySelectorAll('[data-nt-native-rental]').forEach(function (panel) {
			var vehicle = panel.querySelector('[data-nt-rental-vehicle]');
			var days = panel.querySelector('[data-nt-rental-days]');
			var result = panel.querySelector('[data-nt-rental-result]');
			if (!vehicle || !days || !result) { return; }
			function update() {
				var data = vehicle.selectedOptions[0] ? vehicle.selectedOptions[0].dataset : {};
				var count = Number(days.value);
				var rate = Number(data.dayRate || 0);
				var deposit = data.deposit ? ' Tiền cọc tham khảo: ' + data.deposit + ' (không cộng vào tiền thuê).' : ' Tiền cọc cần xác nhận.';
				if (!Number.isInteger(count) || count < 1 || count > 30) {
					result.textContent = 'Chọn từ 1 đến 30 ngày.';
					return;
				}
				var price = count === 7 ? data.weekPrice : data.monthPrice;
				if (count === 7 || count === 30) {
					result.textContent = price && /\d/.test(price) && /(?:đ|₫|vnđ|vnd|triệu)/i.test(price)
						? 'Giá tham khảo gói ' + count + ' ngày: ' + price + ' (chưa gồm cọc và phụ phí).' + deposit
						: 'Gói ' + count + ' ngày cần liên hệ để xác nhận báo giá.' + deposit;
				} else if (count > 7) {
					result.textContent = 'Thuê ' + count + ' ngày: liên hệ để xác nhận giá theo gói.' + deposit;
				} else if (!Number.isFinite(rate) || rate <= 0) {
					result.textContent = 'Dòng xe này cần liên hệ để nhận báo giá.' + deposit;
				} else {
					result.textContent = 'Tạm tính ' + new Intl.NumberFormat('vi-VN').format(rate * count) + 'đ / ' + count + ' ngày (chưa gồm cọc và phụ phí).' + deposit;
				}
			}
			vehicle.addEventListener('change', update);
			days.addEventListener('input', update);
			update();
		});
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initRental); }
	else { initRental(); }
}());
