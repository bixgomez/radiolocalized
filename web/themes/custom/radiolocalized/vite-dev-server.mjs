#!/usr/bin/env node

import { spawn } from 'child_process'
import { watch } from 'chokidar'
import browserSync from 'browser-sync'
import { fileURLToPath } from 'url'
import { dirname, join } from 'path'

const __filename = fileURLToPath(import.meta.url)
const __dirname = dirname(__filename)

const bs = browserSync.create()

// Start BrowserSync
bs.init({
  proxy: 'http://radiolocalized.ddev.site',
  open: false,
  notify: false,
  logLevel: 'info',
  snippetOptions: {
    rule: {
      match: /<\/body>/i,
      fn: function (snippet, match) {
        return snippet + match
      }
    }
  }
})

console.log('\nBrowserSync ready at: http://localhost:3000')
console.log('Starting Vite in watch mode...\n')

// Watch dist/ directory for Vite outputs
const distPath = join(__dirname, 'dist')
let distWatcher
let initialBuildComplete = false

function startDistWatcher() {
  if (distWatcher) return

  distWatcher = watch(distPath, {
    ignored: /(^|[\/\\])\../,
    persistent: true,
    ignoreInitial: true,
    depth: 2,
    awaitWriteFinish: {
      stabilityThreshold: 200,
      pollInterval: 100
    }
  })

  let reloadTimeout = null
  let pendingCssFiles = new Set()

  distWatcher
    .on('ready', () => {
      console.log('\nWatching dist/ for changes...')
      const watched = distWatcher.getWatched()
      const fileCount = Object.values(watched).flat().filter(f => f.endsWith('.css') || f.endsWith('.js')).length
      console.log(`Watching ${fileCount} files in dist/\n`)
    })
    .on('change', path => {
      const relativePath = path.replace(distPath + '/', '')

      // Ignore sourcemaps, minified files, and CSS entry artifacts
      if (relativePath.endsWith('.map') ||
          relativePath.endsWith('.min.css') ||
          relativePath.endsWith('.min.js') ||
          relativePath === 'js/styles.js') {
        return
      }

      if (relativePath.endsWith('.css') || relativePath.endsWith('.js')) {
        console.log(`\n[${new Date().toLocaleTimeString()}] Changed: ${relativePath}`)

        if (relativePath.endsWith('.css')) {
          console.log('Injecting CSS...')
          pendingCssFiles.add(relativePath)

          if (reloadTimeout) clearTimeout(reloadTimeout)

          reloadTimeout = setTimeout(() => {
            bs.reload(Array.from(pendingCssFiles))
            pendingCssFiles.clear()
          }, 100)
        } else {
          console.log('Reloading browser...')
          bs.reload()
        }
      }
    })
    .on('add', path => {
      const relativePath = path.replace(distPath + '/', '')

      if (relativePath.endsWith('.map') || relativePath.endsWith('.min.css')) {
        return
      }

      if (relativePath.endsWith('.css')) {
        console.log(`\n[${new Date().toLocaleTimeString()}] Added: ${relativePath}`)
        console.log('Injecting CSS...')
        bs.reload([relativePath])
      }
    })
    .on('error', error => {
      console.error('Watcher error:', error)
    })
}

// Start Vite in watch mode
const viteProcess = spawn('npx', ['vite', 'build', '--watch'], {
  stdio: ['inherit', 'pipe', 'pipe'],
  shell: true
})

viteProcess.stdout.on('data', (data) => {
  const output = data.toString()
  process.stdout.write(output)

  if (!initialBuildComplete && output.includes('built in')) {
    initialBuildComplete = true
    console.log('\nInitial build complete, starting file watcher...')
    startDistWatcher()
  }
})

viteProcess.stderr.on('data', (data) => {
  process.stderr.write(data)
})

viteProcess.on('error', (error) => {
  console.error('Failed to start Vite:', error)
  process.exit(1)
})

viteProcess.on('close', (code) => {
  if (code !== 0) {
    console.error(`Vite process exited with code ${code}`)
  }
  cleanup()
})

function cleanup() {
  console.log('\nShutting down...')
  if (distWatcher) {
    distWatcher.close()
  }
  bs.exit()
  if (viteProcess) {
    viteProcess.kill()
  }
  process.exit(0)
}

process.on('SIGINT', cleanup)
process.on('SIGTERM', cleanup)
