import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { defineConfig, type Plugin } from 'vite'
import viteCompression from 'vite-plugin-compression'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

function getScssEntries(): Record<string, string> {
  const scssDir = path.resolve(__dirname, 'media/scss')
  if (!fs.existsSync(scssDir)) return {}

  const files = fs.readdirSync(scssDir).filter((f) => f.endsWith('.scss'))

  const entries: Record<string, string> = {}
  for (const file of files) {
    const name = path.basename(file, '.scss')
    entries[name] = path.resolve(scssDir, file)
  }
  return entries
}

/**
 * Плагин:
 * - удаляет JS‑чанки, чтобы в media/css оставались только CSS
 * - для каждого *.css создаёт дубликат *.min.css (контент такой же, как у исходного *.css)
 */
function cssOutputsPlugin(): Plugin {
  return {
    name: 'ishop-css-outputs',
    generateBundle(_options, bundle) {
      const cssAssets: { fileName: string; source: string | Uint8Array }[] = []

      for (const [fileName, item] of Object.entries(bundle)) {
        if (
            item.type === 'asset' &&
            typeof item.source === 'string' &&
            fileName.endsWith('.css') &&
            !fileName.endsWith('.min.css')
        ) {
          cssAssets.push({ fileName, source: item.source })
        }
      }

      // Дублируем *.css в *.min.css
      for (const { fileName, source } of cssAssets) {
        const minName = fileName.replace(/\.css$/, '.min.css')
        if (!bundle[minName]) {
          this.emitFile({
            type: 'asset',
            fileName: minName,
            source,
          })
        }
      }

      // Убираем все JS‑чанки — нам нужны только CSS‑файлы
      for (const [fileName, item] of Object.entries(bundle)) {
        if (item.type === 'chunk') {
          delete bundle[fileName]
        }
      }
    },
  }
}

export default defineConfig({
  // HTML не используем, поэтому publicDir и base нам не нужны
  publicDir: false,

  css: {
    // стандартный modern‑pipeline; SASS подтянется из devDependency "sass"
    preprocessorOptions: {
      scss: {
        // сюда можно добавить глобальные импорты, если нужно
        // additionalData: '@use "sass:math";',
      },
    },
  },

  build: {
    outDir: path.resolve(__dirname, 'media/css'),
    assetsDir: '.',            // без подкаталога assets
    emptyOutDir: true,         // при сборке CSS чистим media/css
    sourcemap: false,
    minify: false,             // JS не нужен, поэтому выключаем
    cssMinify: 'lightningcss', // используем дефолтный минификатор Vite 8
    manifest: false,
    copyPublicDir: false,

    // Новый API Vite 8 — настраиваем Rolldown напрямую
    rolldownOptions: {
      input: getScssEntries(), // Record<string,string> — несколько CSS/SCSS-энтрипоинтов
      output: {
        // делаем читаемые имена: admin.scss -> admin.css
        assetFileNames: (assetInfo) => {
          const original = assetInfo.name ?? '[name][extname]'
          const ext = path.extname(original)
          const base = path.basename(original, ext)
          return `${base}${ext}`
        },
      },
    },
  },

  plugins: [
    cssOutputsPlugin(),
    (viteCompression as any)({
      algorithm: 'gzip',
      ext: '.gz',
      threshold: 0,
      // gzip только *.min.css (чтобы получались admin.min.css.gz и т.п.)
      filter: (file) => file.endsWith('.min.css'),
    }),
  ],
})
