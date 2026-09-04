const { chromium } = require('playwright')
const path = require('path')
const state = async (page) => page.evaluate(() => ({
  title: document.querySelector('.driver-popover-title')?.textContent ?? null,
  progress: document.querySelector('.driver-popover-progress-text')?.textContent ?? null,
  next: document.querySelector('.driver-popover-next-btn')?.textContent ?? null,
  overlay: !!document.querySelector('.driver-overlay'),
  events: window.__events, completed: window.__completed, dismissed: window.__dismissed,
}))
;(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'] })
  const page = await browser.newPage()
  const warnings = []
  page.on('console', (m) => { if (m.type() === 'warning' || m.type() === 'error') warnings.push(m.text()) })
  page.on('pageerror', (e) => warnings.push('PAGEERROR ' + e.message))
  await page.goto('file://' + path.join(__dirname, 'tour.html'))
  try {
    await page.waitForSelector('.driver-popover.io-popover', { timeout: 8000 })
    console.log('1', JSON.stringify(await state(page)))
    await page.click('.driver-popover-next-btn')
    await page.waitForTimeout(700)
    console.log('2', JSON.stringify(await state(page)))
    await page.click('.driver-popover-next-btn')
    await page.waitForTimeout(700)
    console.log('3', JSON.stringify(await state(page)))
    await page.click('.driver-popover-next-btn')
    await page.waitForTimeout(700)
    console.log('4', JSON.stringify(await state(page)))
    await page.evaluate(() => { window.__events = []; window.__dismissed = false; document.querySelector('[x-data]').dispatchEvent(new CustomEvent('infinito-onboarding:start')) })
    await page.waitForSelector('.driver-popover.io-popover', { timeout: 8000 })
    console.log('5a', JSON.stringify(await state(page)))
    await page.keyboard.press('Escape')
    await page.waitForTimeout(700)
    console.log('5b', JSON.stringify(await state(page)))
    await page.evaluate(() => { window.__events = []; window.__dismissed = false; document.querySelector('[x-data]').dispatchEvent(new CustomEvent('infinito-onboarding:start')) })
    await page.waitForSelector('.driver-popover.io-popover', { timeout: 8000 })
    await page.click('.driver-popover-close-btn')
    await page.waitForTimeout(700)
    console.log('6', JSON.stringify(await state(page)))
  } finally {
    console.log('warnings', JSON.stringify(warnings))
    await browser.close()
  }
})().catch((e) => { console.error('FAILED', e.message); process.exit(1) })
