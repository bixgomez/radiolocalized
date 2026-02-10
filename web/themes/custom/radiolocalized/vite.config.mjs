import { defineConfig } from 'vite'
import { resolve } from 'path'
import fs from 'fs'
import CleanCSS from 'clean-css'
import sassGlobImports from 'vite-plugin-sass-glob-import'

const __dirname = import.meta.dirname

// Plugin to generate both regular and minified CSS
function dualCssOutput() {
  return {
    name: 'dual-css-output',
    async writeBundle(options, bundle) {
      const minifier = new CleanCSS({ sourceMap: false })

      for (const fileName of Object.keys(bundle)) {
        if (fileName.endsWith('.css') && !fileName.endsWith('.min.css')) {
          const cssPath = resolve(__dirname, 'dist', fileName)
          const minPath = cssPath.replace(/\.css$/, '.min.css')

          // Read the unminified CSS
          const cssContent = fs.readFileSync(cssPath, 'utf-8')

          // Minify
          const minified = minifier.minify(cssContent)

          // Write minified CSS
          fs.writeFileSync(minPath, minified.styles, 'utf-8')

          console.log(`Generated: ${fileName} and ${fileName.replace('.css', '.min.css')}`)
        }
      }
    }
  }
}

export default defineConfig({
  base: '/themes/custom/radiolocalized/dist/',
  css: {
    preprocessorOptions: {
      scss: {
        api: 'modern-compiler',
        silenceDeprecations: ['legacy-js-api'],
        loadPaths: [
          resolve(__dirname, 'node_modules/breakpoint-sass/stylesheets'),
        ],
      },
    },
    devSourcemap: true,
  },
  build: {
    outDir: './dist',
    emptyOutDir: true,
    cssMinify: false,
    sourcemap: true,
    rollupOptions: {
      input: {
        styles: resolve(__dirname, 'entries/styles.js'),
        script: resolve(__dirname, 'js/script.js'),
      },
      output: {
        assetFileNames: (assetInfo) => {
          if (assetInfo.name.endsWith('.css')) {
            return 'css/[name][extname]'
          }
          return 'assets/[name]-[hash][extname]'
        },
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/[name]-[hash].js',
      },
    },
  },
  plugins: [
    sassGlobImports(),
    dualCssOutput(),
  ],
})
