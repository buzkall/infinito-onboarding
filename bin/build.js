#!/usr/bin/env node
/**
 * Bundles the package's JavaScript and CSS with esbuild.
 *
 *   npm run build      → minified production bundles in resources/dist
 *   npm run dev        → unminified bundles with inline sourcemaps
 *
 * Everything (including driver.js) is bundled locally; nothing is loaded
 * from a CDN at runtime. The dist output is committed so consumers do not
 * need npm.
 */
const esbuild = require('esbuild')
const fs = require('fs')
const path = require('path')

const isDev = process.argv.includes('--dev')
const root = path.resolve(__dirname, '..')

const shared = {
    bundle: true,
    minify: !isDev,
    sourcemap: isDev ? 'inline' : false,
    logLevel: 'info',
    target: ['es2020'],
    legalComments: 'none',
}

const scripts = [
    { in: 'resources/js/infinito-onboarding.js', out: 'resources/dist/infinito-onboarding.js' },
    { in: 'resources/js/recorder.js', out: 'resources/dist/recorder.js' },
]

const styles = [
    { in: 'resources/css/infinito-onboarding.css', out: 'resources/dist/infinito-onboarding.css' },
]

async function build() {
    for (const script of scripts) {
        if (!fs.existsSync(path.join(root, script.in))) continue

        await esbuild.build({
            ...shared,
            entryPoints: [path.join(root, script.in)],
            outfile: path.join(root, script.out),
            format: 'iife',
            platform: 'browser',
        })
    }

    for (const style of styles) {
        if (!fs.existsSync(path.join(root, style.in))) continue

        await esbuild.build({
            ...shared,
            entryPoints: [path.join(root, style.in)],
            outfile: path.join(root, style.out),
            loader: { '.css': 'css' },
        })
    }
}

build().catch((error) => {
    console.error(error)
    process.exit(1)
})
