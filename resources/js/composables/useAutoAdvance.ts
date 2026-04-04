import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const _autoAdvanceEnabled = ref<boolean>(typeof window !== 'undefined' ? localStorage.getItem('feedarium.auto_advance') !== 'false' : true);

export function useAutoAdvance() {
    function setAutoAdvance(value: boolean): void {
        _autoAdvanceEnabled.value = value;
        if (typeof window !== 'undefined') {
            localStorage.setItem('feedarium.auto_advance', value ? 'true' : 'false');
        }
    }

    /**
     * After marking a category as read, navigate to the next category that still has
     * unread articles. Falls back to /feeds if all categories are read.
     */
    function navigateToNextUnreadCategory(
        currentCategoryId: number,
        categories: Array<{ id: number }>,
        categoryUnreadMap: Map<number, number>,
    ): void {
        if (!_autoAdvanceEnabled.value) {
            return;
        }

        const currentIndex = categories.findIndex((c) => c.id === currentCategoryId);
        if (currentIndex === -1) {
            return;
        }

        // Walk the list starting from the next category, wrapping around once
        const total = categories.length;
        for (let offset = 1; offset < total; offset++) {
            const nextIndex = (currentIndex + offset) % total;
            const nextCategory = categories[nextIndex];
            if ((categoryUnreadMap.get(nextCategory.id) ?? 0) > 0) {
                router.visit(route('feeds.index'), {
                    data: { category_id: nextCategory.id },
                    preserveScroll: false,
                });
                return;
            }
        }

        // All categories are read — go to the all-articles page
        router.visit('/feeds', { preserveScroll: false });
    }

    return {
        autoAdvanceEnabled: _autoAdvanceEnabled,
        setAutoAdvance,
        navigateToNextUnreadCategory,
    };
}
