import { useStorage } from '@vueuse/core';
import { computed } from 'vue';

export type ViewMode = 'list' | 'grid' | 'compact';

export function useViewPreferences() {
    const viewMode = useStorage<ViewMode>('feedarium.view_mode', 'grid');
    const gridColumns = useStorage<number>('feedarium.grid_columns', 3);

    const gridClass = computed<string>(() => {
        const cols = Math.min(4, Math.max(2, gridColumns.value));
        if (cols === 2) return 'grid grid-cols-1 gap-4 sm:grid-cols-2';
        if (cols === 3) return 'grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3';
        return 'grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4';
    });

    return { viewMode, gridColumns, gridClass };
}
