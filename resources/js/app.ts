import { createInertiaApp } from '@inertiajs/vue3';
import Antd from 'ant-design-vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import 'ant-design-vue/dist/reset.css';
import { initializeTheme } from '@/composables/useAppearance';
import axios from 'axios';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// ===== ADD THIS SECTION =====
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withCredentials = true;

function getCookie(name: string): string | undefined {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) {
        return parts.pop()?.split(';').shift();
    }
}

const xsrfToken = getCookie('XSRF-TOKEN');
if (xsrfToken) {
    axios.defaults.headers.common['X-XSRF-TOKEN'] = decodeURIComponent(xsrfToken);
}
// ===== END OF NEW SECTION =====

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(Antd)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
