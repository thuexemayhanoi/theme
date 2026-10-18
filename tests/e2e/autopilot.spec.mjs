import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
const SIMS = (process.env.NT_SIMS ?? 'on') === 'on';
for (const width of [360,390,768,1440]) {
 test(`display preference and native assistant @ ${width}`, async ({page}) => {
  await page.setViewportSize({width,height:900});
  await page.goto('/');
  const mode=page.getByRole('combobox',{name:'Chế độ hiển thị'});
  await mode.selectOption('dark');
  await expect(page.locator('html')).toHaveAttribute('data-nt-theme','dark');
  const headerAxe = await new AxeBuilder({page}).include('#site-header').withTags(['wcag2a','wcag2aa']).analyze();
  expect(headerAxe.violations.filter(v => ['critical','serious'].includes(v.impact)).map(v=>v.id)).toEqual([]);
  await page.reload();
  await expect(mode).toHaveValue('dark');
  await page.emulateMedia({colorScheme:'light'});
  await mode.selectOption('auto');
  await expect(page.locator('html')).toHaveAttribute('data-nt-theme','light');
  await page.emulateMedia({colorScheme:'dark'});
  await expect(page.locator('html')).toHaveAttribute('data-nt-theme','dark');
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBeTruthy();
  if (!SIMS) {
   const toggle=page.getByRole('button',{name:'Hỏi Nguyễn Tú'});
   await toggle.click();
   await page.getByRole('textbox',{name:'Câu hỏi / Your question'}).fill('Giá Vision theo tháng?');
   await page.getByRole('button',{name:'Gửi',exact:true}).click();
   await expect(page.locator('[data-nt-messages]')).toContainText('1.800.000–2.000.000đ');
   await page.getByRole('textbox',{name:'Câu hỏi / Your question'}).fill('Còn tuần?');
   await page.getByRole('button',{name:'Gửi',exact:true}).click();
   await expect(page.locator('[data-nt-messages]')).toContainText('1.000.000đ');
   await page.getByRole('button',{name:'Đóng trợ lý'}).click();
   await expect(toggle).toBeFocused();
  } else await expect(page.locator('#nt-assistant')).toHaveCount(0);
 });
}
