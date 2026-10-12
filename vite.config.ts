import { readFile, writeFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.tsx',
                'resources/terrain.html',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        {
            name: 'terrain-offline-shell',
            apply: 'build',
            async closeBundle() {
                const html = await readFile(
                    'public/build/resources/terrain.html',
                    'utf8',
                );
                await writeFile('public/terrain.html', html);
                const manifestText = await readFile(
                    'public/build/manifest.json',
                    'utf8',
                );
                const manifest = JSON.parse(manifestText) as Record<
                    string,
                    { file: string; css?: string[]; assets?: string[] }
                >;
                const urls = [
                    ...new Set([
                        '/terrain.html',
                        '/favicon.svg',
                        '/manifest.webmanifest',
                        ...Object.values(manifest).flatMap((entry) =>
                            [
                                entry.file,
                                ...(entry.css ?? []),
                                ...(entry.assets ?? []),
                            ].map((file) => '/build/' + file),
                        ),
                    ]),
                ];
                const version = createHash('sha256')
                    .update(manifestText)
                    .digest('hex')
                    .slice(0, 12);
                await writeFile(
                    'public/terrain-sw.js',
                    'const CACHE = "tout-en-main-terrain-' +
                        version +
                        '";\n' +
                        'const ASSETS = ' +
                        JSON.stringify(urls) +
                        ';\n' +
                        'self.addEventListener("install", event => event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(ASSETS))));\n' +
                        'self.addEventListener("activate", event => event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key.startsWith("tout-en-main-terrain-") && key !== CACHE).map(key => caches.delete(key))))));\n' +
                        'self.addEventListener("fetch", event => { const url = new URL(event.request.url); if (event.request.method !== "GET" || url.origin !== self.location.origin || !ASSETS.includes(url.pathname)) return; event.respondWith(caches.open(CACHE).then(async cache => (await cache.match(url.pathname)) || fetch(event.request))); });\n',
                );
            },
        },
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
