import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vite'
import viteCompression from 'vite-plugin-compression'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

const JS_ENTRY_FILES = [  
  'addtocart.js',
  'addtocompare.js',
  'addtowishlist.js',
  'admin-filter.js',
  'admin-product-batch.js',
  'admin-product-batch-es5.js',
  'products-loader.js',
]

function getJsEntries(): Record<string, string> {
  const jsDir = path.resolve(__dirname, 'media/js')
  if (!fs.existsSync(jsDir)) return {}

  const entries: Record<string, string> = {}

  for (const file of JS_ENTRY_FILES) {
    if (!file.endsWith('.js') || file.endsWith('.min.js')) continue

    const fullPath = path.resolve(jsDir, file)

    if (!fs.existsSync(fullPath)) {
      console.warn(`[vite] JS entry not found: ${fullPath}`)
      continue
    }

    const name = path.basename(file, '.js')
    entries[name] = fullPath
  }

  return entries
}

export default defineConfig({
  publicDir: false,

  build: {
    outDir: path.resolve(__dirname, 'media/js'),
    assetsDir: '.',
    emptyOutDir: false,
    sourcemap: false,
    minify: 'oxc',
    cssMinify: false,
    manifest: false,
    copyPublicDir: false,

    rolldownOptions: {
      input: getJsEntries(),
      output: {
        codeSplitting: true,
        entryFileNames: '[name].min.js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: '[name][extname]',
      },
    },
  },

  plugins: [
    (viteCompression as any)({
      algorithm: 'gzip',
      ext: '.gz',
      threshold: 0,
      filter: (file) => file.endsWith('.min.js'),
    }),
  ],
})
