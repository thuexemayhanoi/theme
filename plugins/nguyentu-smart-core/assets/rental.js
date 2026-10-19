/* Offline, read-only estimate. No cookies, chat data, tracking or API. */
document.addEventListener('DOMContentLoaded', () => {
  for (const panel of document.querySelectorAll('.ntsc-rental')) {
    const vehicle = panel.querySelector('[data-ntsc-vehicle]');
    const days = panel.querySelector('[data-ntsc-days]');
    const result = panel.querySelector('[data-ntsc-result]');
    if (!vehicle || !days || !result) continue;
    const update = () => {
      const selected = vehicle.selectedOptions[0]?.dataset || {};
      const dayRate = Number(selected.dayRate || 0);
      const weekPrice = (selected.weekPrice || '').trim();
      const monthPrice = (selected.monthPrice || '').trim();
      const deposit = (selected.deposit || '').trim();
      const count = Number(days.value);
      const format = (amount) => new Intl.NumberFormat('vi-VN').format(amount) + 'đ';
      const hasPublishedPrice = (price) => /\d/.test(price) && /(?:đ|₫|vnđ|vnd|triệu)/i.test(price);
      const depositNote = deposit ? ' Tiền cọc tham khảo: ' + deposit + ' (không cộng vào tiền thuê).' : ' Tiền cọc cần xác nhận.';
      const qualifier = ' (chưa gồm cọc và phụ phí).';
      if (!Number.isInteger(count) || count < 1 || count > 30) {
        result.textContent = 'Chọn từ 1 đến 30 ngày.';
      } else if (count === 30) {
        result.textContent = hasPublishedPrice(monthPrice)
          ? 'Giá tham khảo gói 30 ngày: ' + monthPrice + qualifier + depositNote
          : 'Gói 30 ngày cần liên hệ để xác nhận báo giá.' + depositNote;
      } else if (count === 7) {
        result.textContent = hasPublishedPrice(weekPrice)
          ? 'Giá tham khảo gói 7 ngày: ' + weekPrice + qualifier + depositNote
          : 'Gói 7 ngày cần liên hệ để xác nhận báo giá.' + depositNote;
      } else if (count > 7) {
        result.textContent = 'Thuê ' + count + ' ngày: liên hệ để xác nhận giá theo gói; không tự nhân giá ngày cho kỳ thuê dài.' + depositNote;
      } else if (!Number.isFinite(dayRate) || dayRate <= 0) {
        result.textContent = 'Dòng xe này cần liên hệ để nhận báo giá.' + depositNote;
      } else {
        result.textContent = 'Tạm tính ' + format(dayRate * count) + ' / ' + count + ' ngày' + qualifier + depositNote;
      }
    };
    vehicle.addEventListener('change', update);
    days.addEventListener('input', update);
    update();
  }
});
