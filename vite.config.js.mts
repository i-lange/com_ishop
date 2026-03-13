import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vite'
import viteCompression from 'vite-plugin-compression'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

function getJsEntries(): Record<string, string> {
  const jsDir = path.resolve(__dirname, 'media/js')
  if (!fs.existsSync(jsDir)) return {}

  const files = fs
      .readdirSync(jsDir)
      .filter((f) => f.endsWith('.js') && !f.endsWith('.min.js'))

  const entries: Record<string, string> = {}
  for (const file of files) {
    const name = path.basename(file, '.js')
    entries[name] = path.resolve(jsDir, file)
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
        // ЯВНО разрешаем код-сплиттинг, чтобы несколько входов работали корректно
        codeSplitting: true,

        // admin.js -> admin.min.js
        entryFileNames: '[name].min.js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: '[name][extname]',
        // format оставляем по умолчанию ('es'), IIFE не нужен
        // format: 'es',
      },
    },
  },

  plugins: [
    (viteCompression as any)({
      algorithm: 'gzip',
      ext: '.gz',
      threshold: 0,
      // gzip только *.min.js (чтобы получались admin.min.js.gz и т.п.)
      filter: (file) => file.endsWith('.min.js'),
    }),
  ],
})
