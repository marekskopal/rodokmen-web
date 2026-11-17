// Copies the front-end libraries and fonts installed by pnpm into public/assets/vendor (served by the website, not committed).
// Run after `pnpm install`: `pnpm build`.
import { cpSync, existsSync, mkdirSync, rmSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const vendor = join(root, 'public', 'assets', 'vendor');

// package → files (or directories) copied from node_modules/<package> into public/assets/vendor/<package>
// (dist/images: leaflet.css refers to images/*.png for the default marker and the layers control;
// EB Garamond is the typeface of the historical poster, src/View/Poster/PosterFonts.php)
const garamond = ['latin', 'latin-ext'].flatMap((subset) => ['400-normal', '400-italic', '600-normal', '600-italic']
	.map((face) => `files/eb-garamond-${subset}-${face}.woff2`));
const assets = {
	leaflet: ['dist/leaflet.js', 'dist/leaflet.css', 'dist/images', 'LICENSE'],
	'@fontsource/eb-garamond': [...garamond, 'LICENSE'],
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
