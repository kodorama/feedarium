import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import axios from 'axios';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';
import i18n from './i18n';

axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common.Accept = 'application/json';

function getCookie(name: string): string | null {
    const match = document.cookie.match(
        new RegExp('(?:^|;\\s*)' + name + '=([^;]*)'),
    );

    return match ? decodeURIComponent(match[1]) : null;
}

axios.interceptors.request.use((config) => {
    const token = getCookie('XSRF-TOKEN');

    if (token) {
        config.headers['X-XSRF-TOKEN'] = token;
    }

    return config;
});

const appName = import.meta.env.VITE_APP_NAME || 'Feedarium';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),

    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),

    setup({ el, App, props, plugin }) {
        const supportedLocales = ['en', 'tr'] as const;

        type SupportedLocale = (typeof supportedLocales)[number];

        const isSupportedLocale = (locale: unknown): locale is SupportedLocale =>
            typeof locale === 'string' && supportedLocales.includes(locale as SupportedLocale);

        const applyLocale = (locale: unknown): void => {
            if (isSupportedLocale(locale)) {
                i18n.global.locale.value = locale;
            }
        };

        applyLocale(props.initialPage.props.locale);

        router.on('navigate', (event) => {
            applyLocale(event.detail.page.props.locale);
        });

        createApp({
            render: () => h(App, props),
        })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .mount(el);
    },

    progress: {
        color: '#4B5563',
    },
});

initializeTheme();
