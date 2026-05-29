import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { defineConfig, type Plugin } from 'vite'
import viteCompression from 'vite-plugin-compression'
import { transform } from 'lightningcss'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

const SCSS_ENTRIES: string[] = [
  'admin.scss',
  'front.scss',
]

function getScssEntries(): Record<string, string> {
  const scssDir = path.resolve(__dirname, 'media/scss')

  const entries: Record<string, string> = {}
  for (const file of SCSS_ENTRIES) {
    const fullPath = path.resolve(scssDir, file)
    const name = path.basename(file, '.scss')
    entries[name] = fullPath
  }

  return entries
}

/**
 * Плагин:
 * - оставляет исходный *.css
 * - создаёт отдельный *.min.css в минифицированном виде
 * - удаляет JS-чанки
 */
function cssOutputsPlugin(): Plugin {
  return {
    name: 'ishop-css-outputs',
    generateBundle(_options, bundle) {
      const cssAssets: { fileName: string; source: string }[] = []

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

      for (const { fileName, source } of cssAssets) {
        const minName = fileName.replace(/\.css$/, '.min.css')

        if (!bundle[minName]) {
          const minified = transform({
            filename: fileName,
            code: Buffer.from(source),
            minify: true,
            sourceMap: false,
          })

          this.emitFile({
            type: 'asset',
            fileName: minName,
            source: minified.code,
          })
        }
      }

      for (const [fileName, item] of Object.entries(bundle)) {
        if (item.type === 'chunk') {
          delete bundle[fileName]
        }
      }
    },
  }
}

export default defineConfig({
  publicDir: false,

  css: {
    preprocessorOptions: {
      scss: {
        quietDeps: true,
        silenceDeprecations: ['import', 'global-builtin', 'if-function', 'color-functions']
        // additionalData: '@use "sass:math";',
      },
    },
  },

  build: {
    outDir: path.resolve(__dirname, 'media/css'),
    assetsDir: '.',
    emptyOutDir: true,
    sourcemap: false,
    minify: false,
    cssMinify: false,
    manifest: false,
    copyPublicDir: false,

    rolldownOptions: {
      input: getScssEntries(),
      output: {
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
      filter: (file: string) => file.endsWith('.min.css'),
    }),
  ],
})