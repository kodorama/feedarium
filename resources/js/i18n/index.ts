import { createI18n } from 'vue-i18n';
import en from './locales/en.json';
import tr from './locales/tr.json';

const i18n = createI18n({
    legacy: false,
    locale: 'en',
    fallbackLocale: 'en',
    messages: {
        en,
        tr,
    },
});

export default i18n;
