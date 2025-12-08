import { existsSync, copyFileSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const source = join(__dirname, 'public/build/.vite/manifest.json');
const dest = join(__dirname, 'public/build/manifest.json');

if (existsSync(source)) {
    copyFileSync(source, dest);
    console.log('✅ Manifest copied successfully!');
} else {
    console.error('❌ Source manifest not found at:', source);
    process.exit(1);
}
