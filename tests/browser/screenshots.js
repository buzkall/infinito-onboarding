const { chromium } = require('playwright')
const path = require('path')
const out = path.resolve(__dirname, '../../docs/screenshots')
const base = process.env.APP_URL ?? 'http://127.0.0.1:8000'
const sleep = (ms) => new Promise((r) => setTimeout(r, ms))

;(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'] })
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 }, deviceScaleFactor: 2, colorScheme: 'light' })
  const page = await context.newPage()
  const errors = []
  page.on('pageerror', (e) => errors.push('PAGEERROR ' + e.message))
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()) })

  // Login
  await page.goto(base + '/admin/login')
  await page.fill('input[type="email"]', 'alicia@example.com')
  await page.fill('input[type="password"]', 'password')
  await page.click('button[type="submit"]')
  await page.waitForURL((u) => !u.pathname.includes('login'), { timeout: 20000 }); await page.waitForLoadState('networkidle')
  await page.evaluate(() => localStorage.setItem('theme', 'light'))

  // 1. Guided tour on the orders page
  await page.goto(base + '/admin/orders?onboarding-preview=orders-q3')
  await page.waitForSelector('.driver-popover.io-popover', { timeout: 15000 })
  await sleep(700)
  await page.screenshot({ path: path.join(out, 'tour.png') })
  await page.click('.driver-popover-next-btn'); await sleep(700)
  await page.screenshot({ path: path.join(out, 'tour-step-2.png') })
  await page.keyboard.press('Escape'); await sleep(300)

  // 2. Hints (beacons)
  await page.goto(base + '/admin/orders?onboarding-preview=order-hints')
  await page.waitForSelector('.io-beacon', { timeout: 15000 })
  await sleep(500)
  await page.click('[data-io-beacon]')
  await page.waitForSelector('.io-hint-got-it')
  await sleep(700)
  await page.screenshot({ path: path.join(out, 'hints.png') })
  await page.keyboard.press('Escape'); await sleep(300)

  // 3. Changelog modal
  await page.goto(base + '/admin/orders?onboarding-preview=release-2-4')
  await page.waitForSelector('[data-tour-overlay] .fi-modal-window', { state: 'visible', timeout: 15000 })
  await sleep(700)
  await page.screenshot({ path: path.join(out, 'changelog.png') })

  // 4. Record mode
  await page.goto(base + '/admin/orders?onboarding-record=orders-q3')
  await page.waitForSelector('.io-recorder-panel', { timeout: 15000 })
  await sleep(500)
  await page.click('.io-recorder-panel button.io-btn-primary')
  const target = await page.locator('[data-tour="customer-field"]').boundingBox()
  await page.mouse.move(target.x + 40, target.y + 30)
  await sleep(300)
  await page.screenshot({ path: path.join(out, 'record-mode-pick.png') })
  await page.mouse.click(target.x + 40, target.y + 30)
  await page.waitForSelector('#io-step-title')
  await page.fill('#io-step-title', 'Customer name')
  await page.fill('#io-step-body', 'Start typing to search existing customers.')
  await sleep(300)
  await page.screenshot({ path: path.join(out, 'record-mode-editor.png') })

  // 5. Resource: list and edit with analytics
  await page.goto(base + '/admin/onboarding-tours')
  await page.waitForSelector('table', { timeout: 15000 })
  await sleep(700)
  await page.screenshot({ path: path.join(out, 'resource-list.png') })
  await page.goto(base + '/admin/onboarding-tours/1/edit')
  await page.waitForSelector('[data-tour-analytics]', { timeout: 15000 })
  await sleep(700)
  await page.screenshot({ path: path.join(out, 'resource-edit.png'), fullPage: true })
  await page.locator('[data-tour-analytics]').screenshot({ path: path.join(out, 'analytics.png') })

  // 6. Topbar trigger with unseen badge
  await page.goto(base + '/admin')
  await page.waitForSelector('[data-changelog-trigger]', { timeout: 15000 })
  await sleep(500)
  await page.screenshot({ path: path.join(out, 'topbar-trigger.png'), clip: { x: 0, y: 0, width: 1280, height: 64 } })
  await page.click('.io-changelog-trigger-btn')
  await page.waitForSelector('[data-changelog-trigger] .fi-modal-window', { state: 'visible' })
  await sleep(700)
  await page.screenshot({ path: path.join(out, 'topbar-changelog.png') })

  // 7. Dark mode tour
  await page.evaluate(() => localStorage.setItem('theme', 'dark'))
  await page.goto(base + '/admin/orders?onboarding-preview=orders-q3')
  await page.waitForSelector('.driver-popover.io-popover', { timeout: 15000 })
  await page.click('.driver-popover-next-btn'); await sleep(700)
  await page.screenshot({ path: path.join(out, 'tour-dark.png') })
  await page.evaluate(() => localStorage.setItem('theme', 'light'))

  console.log('errors', JSON.stringify(errors.slice(0, 5)))
  await browser.close()
})().catch((e) => { console.error('FAILED', e.message); process.exit(1) })
