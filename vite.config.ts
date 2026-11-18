import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { existsSync } from 'node:fs';
import { local } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

// Circe Rounded tiene licencia comercial y no se guarda en git: hay que
// copiar los .otf en resources/fonts (ver resources/fonts/LEEME.md). Sin
// ellos el proyecto compila igual y usa la fuente del sistema.
const circeFonts = {
    medium: 'resources/fonts/CirceRounded-Medium.otf',
    bold: 'resources/fonts/CirceRounded-Bold.otf',
};
const hasCirce = existsSync(circeFonts.medium) && existsSync(circeFonts.bold);

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            // Tipografía institucional: Circe Rounded Medium para los textos
            // y Bold para los títulos. Solo hay dos archivos, así que los
            // pesos normales usan Medium y los gruesos usan Bold.
            fonts: hasCirce
                ? [
                      local('Circe Rounded', {
                          variants: [
                              { src: circeFonts.medium, weight: 400 },
                              { src: circeFonts.medium, weight: 500 },
                              { src: circeFonts.bold, weight: 600 },
                              { src: circeFonts.bold, weight: 700 },
                          ],
                      }),
                  ]
                : [],
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
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
