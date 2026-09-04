const { chromium } = require('playwright')
const path = require('path')
;(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'] })
  const page = await browser.newPage()
  const errors = []
  page.on('pageerror', (e) => errors.push('PAGEERROR ' + e.message))
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()) })
  await page.goto('file://' + path.join(__dirname, 'recorder.html'))
  await page.waitForFunction(() => window.InfinitoOnboarding?.captureTarget && window.Alpine)

  const scoring = await page.evaluate(() => {
    const c = window.InfinitoOnboarding.captureTarget
    return {
      dataTour: c(document.querySelector('#btn-tour')),
      id: c(document.querySelector('#data\\.email')),
      wireKey: c(document.querySelector('.inner')),
      deep: c(document.querySelectorAll('.deep')[1]),
      generated: c(document.querySelector('.generated')),
    }
  })
  console.log('scoring', JSON.stringify(scoring, null, 1))

  // UI flow: pick -> hover -> click -> fill -> add
  await page.click('#pick')
  const btn = await page.locator('#btn-tour').boundingBox()
  await page.mouse.move(btn.x + 5, btn.y + 5)
  await page.waitForTimeout(100)
  const hover = await page.evaluate(() => ({ visible: getComputedStyle(document.querySelector('.io-recorder-hover')).display !== 'none', label: document.querySelector('.io-recorder-hover-label').textContent, cls: document.querySelector('.io-recorder-hover').className }))
  console.log('hover', JSON.stringify(hover))
  await page.mouse.click(btn.x + 5, btn.y + 5)
  await page.waitForSelector('#io-step-title')
  const draft = await page.evaluate(() => ({ selector: document.querySelector('#draft-selector').textContent, score: document.querySelector('#draft-score').textContent, picking: document.body.classList.contains('io-recorder-picking') }))
  console.log('draft', JSON.stringify(draft))
  await page.fill('#io-step-title', 'Export step')
  await page.fill('#io-step-body', 'First line\n\nSecond paragraph')
  await page.click('#add')
  await page.waitForSelector('#steps li')
  // second step: deep element (red)
  await page.click('#pick')
  const deep = await page.locator('.deep >> nth=1').boundingBox()
  await page.mouse.move(deep.x + 2, deep.y + 2)
  await page.waitForTimeout(50)
  await page.mouse.click(deep.x + 2, deep.y + 2)
  await page.waitForSelector('#io-step-title')
  const hint = await page.evaluate(() => document.querySelector('#draft-hint').textContent)
  console.log('hint', hint)
  await page.fill('#io-step-title', 'Deep step')
  await page.click('#add')
  const steps = await page.evaluate(() => Array.from(document.querySelectorAll('#steps li')).map((li) => [li.dataset.score, li.textContent]))
  console.log('steps', JSON.stringify(steps))
  // preview
  await page.click('#preview')
  await page.waitForSelector('.driver-popover.io-popover', { timeout: 8000 })
  const preview = await page.evaluate(() => ({ title: document.querySelector('.driver-popover-title')?.textContent, body: document.querySelector('.driver-popover-description')?.innerHTML, panelHidden: getComputedStyle(document.querySelector('.io-recorder-panel')).display === 'none' }))
  console.log('preview', JSON.stringify(preview))
  await page.keyboard.press('Escape')
  await page.waitForTimeout(300)
  const after = await page.evaluate(() => ({ overlay: !!document.querySelector('.driver-overlay'), panelVisible: getComputedStyle(document.querySelector('.io-recorder-panel')).display !== 'none' }))
  console.log('after', JSON.stringify(after))
  console.log('errors', JSON.stringify(errors))
  await browser.close()
})().catch((e) => { console.error('FAILED', e.message); process.exit(1) })
