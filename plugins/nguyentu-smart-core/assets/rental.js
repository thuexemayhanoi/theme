/* Offline, read-only estimate. No cookies, chat data, tracking or API. */
document.addEventListener('DOMContentLoaded', () => {
  for (const panel of document.querySelectorAll('.ntsc-rental')) {
    const vehicle = panel.querySelector('[data-ntsc-vehicle]');
    const days = panel.querySelector('[data-ntsc-days]');
    const result = panel.querySelector('[data-ntsc-result]');
    if (!vehicle || !days || !result) continue;
    const update = () => {
      const amount = Number(vehicle.selectedOptions[0]?.dataset.dayRate || 0);
      const count = Number(days.value);
      if (!Number.isInteger(count) || count < 1 || count > 30) {
        result.textContent = 'Chọn từ 1 đến 30 ngày.';
      } else if (!Number.isFinite(amount) || amount <= 0) {
        result.textContent = 'Dòng xe này cần liên hệ để nhận báo giá.';
      } else {
        result.textContent = 'Tạm tính ' + new Intl.NumberFormat('vi-VN').format(amount * count) + 'đ / ' + count + ' ngày (chưa gồm cọc và phụ phí).';
      }
    };
    vehicle.addEventListener('change', update);
    days.addEventListener('input', update);
    update();
  }
});
