import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync } from 'node:fs';
import { chromium } from '@playwright/test';

// Audited WordPress inventory, 21 October 2026. Read-only public verification.
const targets = [
  {
    "id": 562,
    "url": "https://thuexemaynguyentu.com/"
  },
  {
    "id": 310,
    "url": "https://thuexemaynguyentu.com/gioi-thieu/"
  },
  {
    "id": 424,
    "url": "https://thuexemaynguyentu.com/gioi-thieu/ve-chung-toi/"
  },
  {
    "id": 432,
    "url": "https://thuexemaynguyentu.com/gioi-thieu/su-menh-tam-nhin/"
  },
  {
    "id": 372,
    "url": "https://thuexemaynguyentu.com/lien-he/"
  },
  {
    "id": 484,
    "url": "https://thuexemaynguyentu.com/faq/"
  },
  {
    "id": 326,
    "url": "https://thuexemaynguyentu.com/chinh-sach-bao-mat/"
  },
  {
    "id": 449,
    "url": "https://thuexemaynguyentu.com/dieu-khoan-su-dung/"
  },
  {
    "id": 494,
    "url": "https://thuexemaynguyentu.com/bo-suu-tap/"
  },
  {
    "id": 3040,
    "url": "https://thuexemaynguyentu.com/shop/"
  },
  {
    "id": 476,
    "url": "https://thuexemaynguyentu.com/thue-xe-may-ha-noi-gia-re/"
  },
  {
    "id": 823,
    "url": "https://thuexemaynguyentu.com/kinh-nghiem-thue-xe-may-ha-noi/"
  },
  {
    "id": 829,
    "url": "https://thuexemaynguyentu.com/huong-dan-thu-tuc-thue-xe-may-ha-noi-don-gian-tien-loi/"
  },
  {
    "id": 836,
    "url": "https://thuexemaynguyentu.com/thue-xe-may-o-pho-co/"
  },
  {
    "id": 841,
    "url": "https://thuexemaynguyentu.com/thue-xe-may-gan-ho-hoan-kiem/"
  },
  {
    "id": 847,
    "url": "https://thuexemaynguyentu.com/cho-thue-xe-may-long-bien/"
  },
  {
    "id": 851,
    "url": "https://thuexemaynguyentu.com/thue-xe-may-gia-lam/"
  },
  {
    "id": 857,
    "url": "https://thuexemaynguyentu.com/thue-xe-may-ba-dinh/"
  },
  {
    "id": 862,
    "url": "https://thuexemaynguyentu.com/cho-thue-xe-may-tay-ho/"
  },
  {
    "id": 868,
    "url": "https://thuexemaynguyentu.com/thue-xe-may-dong-da/"
  },
  {
    "id": 875,
    "url": "https://thuexemaynguyentu.com/thue-xe-may-ben-xe-gia-lam/"
  },
  {
    "id": 880,
    "url": "https://thuexemaynguyentu.com/thue-xe-may-vinhomes-ocean-park/"
  },
  {
    "id": 887,
    "url": "https://thuexemaynguyentu.com/chi-phi-giao-xe-may-tan-noi-ha-noi/"
  },
  {
    "id": 891,
    "url": "https://thuexemaynguyentu.com/hop-dong-thue-xe-may/"
  },
  {
    "id": 895,
    "url": "https://thuexemaynguyentu.com/thue-xe-may-50cc-ha-noi/"
  },
  {
    "id": 901,
    "url": "https://thuexemaynguyentu.com/chi-phi-thue-xe-may-50cc-tai-ha-noi/"
  },
  {
    "id": 906,
    "url": "https://thuexemaynguyentu.com/thue-xe-may-50cc-tai-ha-noi-10-meo-khong-the-bo-qua/"
  },
  {
    "id": 912,
    "url": "https://thuexemaynguyentu.com/thue-xe-may-ha-noi-gan-quan-hai-ba-trung/"
  },
  {
    "id": 920,
    "url": "https://thuexemaynguyentu.com/cho-thue-xe-may-gan-time-city/"
  },
  {
    "id": 925,
    "url": "https://thuexemaynguyentu.com/cho-thue-xe-dien-ha-noi/"
  },
  {
    "id": 931,
    "url": "https://thuexemaynguyentu.com/so-sanh-gia-thue-xe-may-dien-va-xe-dap-dien-tai-ha-noi/"
  },
  {
    "id": 940,
    "url": "https://thuexemaynguyentu.com/ho-hoan-kiem/"
  },
  {
    "id": 943,
    "url": "https://thuexemaynguyentu.com/pho-co-ha-noi/"
  },
  {
    "id": 952,
    "url": "https://thuexemaynguyentu.com/chua-mot-cot/"
  },
  {
    "id": 1060,
    "url": "https://thuexemaynguyentu.com/thue-xe-may-theo-thang-ha-noi-gia-re-xe-ga-xe-so/"
  },
  {
    "id": 1061,
    "url": "https://thuexemaynguyentu.com/thue-xe-may-san-bay-noi-bai-giao-nhan-thuan-tien/"
  },
  {
    "id": 1062,
    "url": "https://thuexemaynguyentu.com/thue-xe-ga-xe-so-ha-noi-cach-chon-xe-phu-hop/"
  },
  {
    "id": 2055,
    "url": "https://thuexemaynguyentu.com/app-thue-xe-may-ha-noi/"
  }
];
const selected = new Set([562,372,494,3040,895,880,1060,1061]);
const output = 'reports/seo-matrix-live';
mkdirSync(output, { recursive: true });
const proof = { started: new Date().toISOString(), commit: process.env.GITHUB_SHA,
  engine: 'Chromium viewport emulation; native iPhone Safari NOT_TESTED',
  scope: '38 URLs at 390x844; eight representative URLs at 360x800, 844x390 and 1440x900',
  checks: [], errors: [] };
const browser = await chromium.launch();
try {
  // One page and one request at a time to avoid stressing shared hosting.
  for (const [width,height] of [[390,844],[360,800],[844,390],[1440,900]]) {
    const context = await browser.newContext({ viewport: { width,height }, locale:'vi-VN',
      timezoneId:'Asia/Ho_Chi_Minh', isMobile:width<1000, hasTouch:width<1000 });
    const page = await context.newPage();
    for (const target of targets.filter(x=>width===390||selected.has(x.id))) {
      const result = { ...target, width,height, status:'PENDING', pageErrors:[] };
      proof.checks.push(result);
      const onError = error=>result.pageErrors.push(String(error));
      page.on('pageerror',onError);
      try {
        const response = await page.goto(target.url,{waitUntil:'domcontentloaded',timeout:45000});
        assert.equal(response?.status(),200,'HTTP status');
        await page.locator('main').waitFor();
        await page.evaluate(()=>document.fonts.ready);
        result.meta = await page.evaluate(()=>{
          const main=document.querySelector('main');
          return {
            title:document.title,
            h1:[...document.querySelectorAll('h1')].map(e=>e.textContent.trim()),
            canonicals:[...document.querySelectorAll('link[rel="canonical"]')].map(e=>e.href),
            robots:document.querySelector('meta[name="robots"]')?.content||'',
            viewport:document.querySelector('meta[name="viewport"]')?.content||'',
            overflow:document.documentElement.scrollWidth>innerWidth+1,
            rawShortcodes:/\[\/?(?:ux_\w+|section|row|col)(?=[\s\]/])/.test(main.innerText),
            mainCount:document.querySelectorAll('main').length,
            quickCallCount:document.querySelectorAll('#nt-native-quickcall').length,
            contact:[...main.querySelectorAll('a[href^="tel:"],a[href*="zalo.me"]')].map(e=>({text:e.textContent.trim(),href:e.href})),
          };
        });
        assert.equal(page.url(),target.url,'Unexpected URL change');
        assert.equal(result.meta.h1.length,1,'One H1');
        assert.deepEqual(result.meta.canonicals,[target.url],'Self canonical');
        assert.ok(!/\bnoindex\b/i.test(result.meta.robots),'No accidental noindex');
        assert.ok(/device-width/.test(result.meta.viewport),'Mobile viewport meta');
        assert.equal(result.meta.overflow,false,'No horizontal overflow');
        assert.equal(result.meta.rawShortcodes,false,'No raw legacy shortcodes');
        assert.equal(result.meta.mainCount,1,'One main landmark');
        assert.equal(result.meta.quickCallCount,1,'Preserve one existing Quick Call');
        if ([880,1061].includes(target.id)) {
          assert.ok(result.meta.contact.some(x=>x.href==='tel:0942467674'),'Published phone link');
          assert.ok(result.meta.contact.some(x=>x.href==='https://zalo.me/0942467674'),'Published Zalo link');
        }
        assert.deepEqual(result.pageErrors,[],'No page JS exceptions');
        result.status='PASS';
      } catch(error) {
        result.status='FAIL';
        result.error=String(error.stack||error);
        proof.errors.push({id:target.id,width,height,error:result.error});
      } finally {
        await page.screenshot({path:output+'/'+target.id+'-'+width+'x'+height+'.png'}).catch(()=>{});
        page.off('pageerror',onError);
      }
    }
    await context.close();
  }
} finally {
  proof.finished=new Date().toISOString();
  proof.status=proof.errors.length?'FAIL':'PASS';
  writeFileSync(output+'/proof.json',JSON.stringify(proof,null,2)+'\n');
  await browser.close();
}
console.log(JSON.stringify({status:proof.status,checks:proof.checks.length,errors:proof.errors},null,2));
assert.equal(proof.errors.length,0,'Review failed viewport checks in proof.json');
