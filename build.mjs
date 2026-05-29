#!/usr/bin/env node

/**
 * Скрипт для генерации полного набора файлов CSS и JS.
 * Запускается как: node build.mjs
 */

import { execSync } from 'child_process';

console.log('🔨 Сборка CSS (из /media/scss/*.scss)...');
execSync('vite build --config vite.config.css.mts', { stdio: 'inherit' });
console.log('\n✅ Сборка CSS завершена!');

console.log('\n🔨 Сборка JS (minified, gzip)...');
execSync('vite build --config vite.config.js.mts', { stdio: 'inherit' });
console.log('\n✅ Сборка JS завершена!');
