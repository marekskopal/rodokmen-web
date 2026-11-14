// Copies the front-end libraries installed by pnpm into public/assets/vendor (served by the website, not committed).
// Run after `pnpm install`: `pnpm build`.
import { cpSync, existsSync, mkdirSync, rmSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const vendor = join(root, 'public', 'assets', 'vendor');

// package → files (or directories) copied from node_modules/<package> into public/assets/vendor/<package>
// (dist/images: leaflet.css refers to images/*.png for the default marker and the layers control)
const assets = {
	leaflet: ['dist/leaflet.js', 'dist/leaflet.css', 'dist/images', 'LICENSE'],
};

// Check the sources first, so that a failed build does not leave the website without its libraries.
for (const [name, files] of Object.entries(assets)) {
	for (const file of files) {
		if (!existsSync(join(root, 'node_modules', name, file))) {
			console.error(`node_modules/${name}/${file} is missing – run \`pnpm install --frozen-lockfile\` first.`);
			process.exit(1);
		}
	}
}

rmSync(vendor, { recursive: true, force: true });
for (const [name, files] of Object.entries(assets)) {
	mkdirSync(join(vendor, name), { recursive: true });
	for (const file of files) {
		const target = join(vendor, name, file.split('/').pop());
		cpSync(join(root, 'node_modules', name, file), target, { recursive: true });
		console.log(`${name}/${file} → ${target.slice(root.length + 1)}`);
	}
}
