import { wayfinder } from '@laravel/vite-plugin-wayfinder'
import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import { defineConfig, loadEnv } from 'vite'

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '')

    return {
        server: {
            host: '0.0.0.0',
            port: parseInt(env.VITE_DEV_SERVER_PORT, 10),
            strictPort: true,
            cors: true,
            hmr: {
                host: env.VITE_DEV_SERVER_HOST || 'localhost',
                port: parseInt(env.VITE_DEV_SERVER_PORT, 10),
            },
        },

        plugins: [
            laravel({
                input: ['resources/js/app.ts'],
                ssr: 'resources/js/ssr.ts',
                refresh: true,
            }),
            tailwindcss(),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            wayfinder({
                formVariants: true,
            }),
        ],
    }
})