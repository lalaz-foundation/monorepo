import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Root of the monorepo
const rootDir = path.resolve(__dirname, '..');
const packagesDir = path.join(rootDir, 'packages');
const docsDir = path.join(rootDir, 'docs');
const docsPackagesDir = path.join(docsDir, 'packages');

console.log('🔄 Syncing documentation...');

// 1. Clean docs/packages directory
if (fs.existsSync(docsPackagesDir)) {
    console.log('🧹 Cleaning existing packages docs...');
    fs.rmSync(docsPackagesDir, { recursive: true, force: true });
}
fs.mkdirSync(docsPackagesDir, { recursive: true });

// 2. Find all packages
const packages = fs.readdirSync(packagesDir).filter(pkg => {
    return fs.statSync(path.join(packagesDir, pkg)).isDirectory();
});

// 3. Copy docs from each package
let count = 0;
packages.forEach(pkg => {
    const pkgDocsDir = path.join(packagesDir, pkg, 'docs');
    const targetDir = path.join(docsPackagesDir, pkg);

    if (fs.existsSync(pkgDocsDir)) {
        console.log(`📦 Copying docs for ${pkg}...`);

        // Create target directory
        fs.mkdirSync(targetDir, { recursive: true });

        // Copy files recursively
        fs.cpSync(pkgDocsDir, targetDir, { recursive: true });
        count++;
    }
});

console.log(`✅ Documentation synced! (${count} packages processed)`);
console.log(`📂 Docs location: ${docsPackagesDir}`);
