const fs = require('fs');
const path = require('path');

const source = path.join(__dirname, 'public/build/.vite/manifest.json');
const dest = path.join(__dirname, 'public/build/manifest.json');

if (fs.existsSync(source)) {
    fs.copyFileSync(source, dest);
    console.log('✅ Manifest copied successfully!');
} else {
    console.error('❌ Source manifest not found at:', source);
    process.exit(1);
}
