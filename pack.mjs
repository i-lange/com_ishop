import fs from 'node:fs';
import path from 'node:path';
import archiver from 'archiver';
import pkg from './package.json' with { type: 'json' };

const filename = `com_ishop-${pkg.version}.zip`;
const buildDir = 'build';
const outputPath = path.join(buildDir, filename);

fs.mkdirSync(buildDir, { recursive: true });

const output = fs.createWriteStream(outputPath);
const archive = archiver('zip', { zlib: { level: 9 } });

archive.pipe(output);

for (const dir of ['backend', 'frontend', 'media']) {
  archive.directory(dir, dir);
}

for (const file of ['ishop.xml', 'script.php', 'README.md']) {
  archive.file(file, { name: file });
}

await archive.finalize();

console.log('\n✅ Создан архив для установки! Файл: ' + outputPath);
