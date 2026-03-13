import fs from 'node:fs';
import archiver from 'archiver';
import pkg from './package.json' with { type: 'json' };

const filename = `com_ishop-${pkg.version}.zip`;
const output = fs.createWriteStream(filename);
const archive = archiver('zip', { zlib: { level: 9 } });

archive.pipe(output);

for (const dir of ['backend', 'frontend', 'media']) {
  archive.directory(dir, dir);
}

for (const file of ['ishop.xml', 'script.php', 'README.md']) {
  archive.file(file, { name: file });
}

await archive.finalize();

console.log('\n✅ Создан архив для установки! Файл: ' + filename);