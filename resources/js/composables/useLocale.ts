import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { AppPageProps, LocaleMeta } from '@/types';

export type { LocaleMeta };

/**
 * Syncs vue-i18n's active locale with the locale shared via Inertia page props.
 * Call once in the root App component (or app entry point).
 */
export function useLocale() {
    const page = usePage<AppPageProps>();
    const { locale } = useI18n({ useScope: 'global' });

    // Apply locale immediately and whenever it changes via Inertia navigation.
    watch(
        () => page.props.locale,
        (incoming) => {
            if (incoming && incoming !== locale.value) {
                locale.value = incoming;
            }
        },
        { immediate: true },
    );

    const availableLocales = (): LocaleMeta[] => page.props.availableLocales ?? [];

    const currentLocale = (): string => page.props.locale ?? 'en';

    return { availableLocales, currentLocale };
}


