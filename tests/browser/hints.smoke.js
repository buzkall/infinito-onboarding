const { chromium } = require('playwright')
const path = require('path')
const state = (page) => page.evaluate(() => ({
  beacons: Array.from(document.querySelectorAll('.io-beacon')).map((b) => [b.dataset.ioBeacon, b.hidden, b.style.top, b.style.left]),
  popover: document.querySelector('.driver-popover-title')?.textContent ?? null,
  gotIt: !!document.querySelector('.io-hint-got-it'),
  dismissed: window.__dismissed, completed: window.__completed, events: window.__events,
}))
;(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'] })
  const page = await browser.newPage()
  const warnings = []
  page.on('console', (m) => { if (['warning', 'error'].includes(m.type())) warnings.push(m.text()) })
  page.on('pageerror', (e) => warnings.push('PAGEERROR ' + e.message))
  await page.goto('file://' + path.join(__dirname, 'hints.html'))
  await page.waitForFunction(() => document.querySelectorAll('.io-beacon').length === 2, null, { timeout: 8000 })
  console.log('1', JSON.stringify(await state(page)))
  await page.click('[data-io-beacon="1"]')
  await page.waitForSelector('.io-hint-got-it')
  console.log('2', JSON.stringify(await state(page)))
  await page.click('.io-hint-got-it')
  await page.waitForFunction(() => document.querySelectorAll('.io-beacon').length === 1)
  console.log('3', JSON.stringify(await state(page)))
  await page.click('[data-io-beacon="2"]')
  await page.waitForSelector('.io-hint-got-it')
  await page.keyboard.press('Escape')
  await page.waitForTimeout(300)
  console.log('4 (escape keeps beacon)', JSON.stringify(await state(page)))
  await page.click('[data-io-beacon="2"]')
  await page.waitForSelector('.io-hint-got-it')
  await page.click('.io-hint-got-it')
  await page.waitForFunction(() => document.querySelectorAll('.io-beacon').length === 0)
  console.log('5', JSON.stringify(await state(page)))
  console.log('warnings', JSON.stringify(warnings))
  await browser.close()
})().catch((e) => { console.error('FAILED', e.message); process.exit(1) })
