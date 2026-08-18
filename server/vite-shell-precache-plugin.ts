/**
 * Vite plugin: post-process the Service Worker bundle and inject the shell
 * precache manifest (TASK-050).
 *
 * The SW is declared as a build input in vite.config.ts (via the Laravel Vite
 * plugin input list). After the bundle is written, this plugin:
 *  1. locates the emitted SW bundle (named `sw`);
 *  2. reads the Vite manifest to collect the hashed shell assets (JS/CSS/fonts);
 *  3. replaces the `__SHELL_PRECACHE__` placeholder with those URLs; and
 *  4. copies the final SW to the web root `public/sw.js` so it controls the
 *     whole origin for offline shell navigation.
 * No extra runtime dependency.
 */
import { readFile, rm, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import type { Plugin } from 'vite';

const PLACEHOLDER = '__SHELL_PRECACHE__';

export function viteShellPrecache(): Plugin {
    let swFileName: string | null = null;

    return {
        name: 'kinevo-shell-precache',
        apply: 'build',
        writeBundle(_options, bundle) {
            const swAsset = Object.entries(bundle).find(
                ([, chunk]) => 'isEntry' in chunk && chunk.isEntry && chunk.name === 'sw',
            );
            if (swAsset) {
                const [, chunk] = swAsset;
                swFileName = (chunk as { fileName: string }).fileName;
            }
        },
        async closeBundle() {
            if (swFileName === null) {
                return;
            }
            const buildDir = join(process.cwd(), 'public', 'build');
            let manifest: Record<string, { file: string }>;
            try {
                manifest = JSON.parse(await readFile(join(buildDir, 'manifest.json'), 'utf8'));
            } catch {
                return;
            }

            const assets = Object.values(manifest)
                .map((entry) => entry.file)
                .filter((file) => /\.(?:js|css|woff2?)$/i.test(file))
                .filter((file) => file !== swFileName) // exclude the SW's own bundle
                .map((file) => `/build/${file}`);

            const precache = JSON.stringify(
                assets.map((url) => ({ url, revision: 'shell' })),
            );

            const emittedSw = join(buildDir, swFileName);
            let source: string;
            try {
                source = await readFile(emittedSw, 'utf8');
            } catch {
                return;
            }
            source = source.replace(PLACEHOLDER, precache);

            // Write the final SW to the web root for full-origin scope.
            await writeFile(join(process.cwd(), 'public', 'sw.js'), source, 'utf8');
            // The build-dir copy is not needed for serving; remove it.
            await rm(emittedSw, { force: true });
        },
    };
}
