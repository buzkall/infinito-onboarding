const { chromium } = require('playwright')
const path = require('path')
const state = (page) => page.evaluate(() => ({
  title: document.querySelector('.driver-popover-title')?.textContent ?? null,
  modalHidden: document.getElementById('modal').hidden,
  clicked: window.__clicked ?? 0,
  events: window.__events,
  overlay: !!document.querySelector('.driver-overlay'),
}))
;(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'] })
  const page = await browser.newPage()
  const warnings = []
  page.on('console', (m) => { if (['warning', 'error'].includes(m.type())) warnings.push(m.text()) })
  page.on('pageerror', (e) => warnings.push('PAGEERROR ' + e.message))
  await page.goto('file://' + path.join(__dirname, 'preconditions.html'))
  await page.waitForSelector('.driver-popover.io-popover', { timeout: 8000 })
  console.log('1', JSON.stringify(await state(page)))
  // click the highlighted element → should advance and run step 2's precondition (opens modal)
  await page.waitForTimeout(600)
  await page.click('[data-tour="advance-me"]')
  try {
    await page.waitForFunction(() => document.querySelector('.driver-popover-title')?.textContent === 'In the modal', null, { timeout: 5000 })
  } catch (e) {
    console.log('after-click', JSON.stringify(await state(page)))
    throw e
  }
  console.log('2', JSON.stringify(await state(page)))
  // next → step 3 target missing → skipped → End
  await page.click('.driver-popover-next-btn')
  await page.waitForFunction(() => document.querySelector('.driver-popover-title')?.textContent === 'End', null, { timeout: 8000 })
  console.log('3', JSON.stringify(await state(page)))
  // back → should return to 'In the modal' (skipping missing)
  await page.click('.driver-popover-prev-btn')
  await page.waitForFunction(() => document.querySelector('.driver-popover-title')?.textContent === 'In the modal', null, { timeout: 8000 })
  console.log('4', JSON.stringify(await state(page)))
  await page.click('.driver-popover-next-btn')
  await page.waitForFunction(() => document.querySelector('.driver-popover-title')?.textContent === 'End', null, { timeout: 8000 })
  await page.click('.driver-popover-next-btn')
  await page.waitForFunction(() => window.__events.some((e) => e[0] === 'completed'))
  console.log('5', JSON.stringify(await state(page)))
  console.log('warnings', JSON.stringify(warnings))
  await browser.close()
})().catch((e) => { console.error('FAILED', e.message); process.exit(1) })
