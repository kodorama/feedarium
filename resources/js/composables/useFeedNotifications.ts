import axios from '@/lib/axios';
import { computed, ref } from 'vue';
import { FeedNotificationTracker, type FeedUpdateFilters, type FeedUpdateResponse } from './feedNotificationTracker';
import { useReadStatus } from './useReadStatus';

export type { FeedUpdateFilters } from './feedNotificationTracker';

export function useFeedNotifications() {
    const { beginUnreadCountRequest, acceptUnreadCountSnapshot } = useReadStatus();
    const tracker = new FeedNotificationTracker(async (params, method) => {
        const unreadCountRequest = beginUnreadCountRequest();
        const response =
            method === 'POST'
                ? await axios.post<{ data: FeedUpdateResponse }>('/api/news/updates', params)
                : await axios.get<{ data: FeedUpdateResponse }>('/api/news/updates', { params });

        const data = response.data.data;
        if (data.unread_counts) acceptUnreadCountSnapshot(data.unread_counts, unreadCountRequest);

        return data;
    });
    const state = ref(tracker.getState());
    const newArticleCount = computed(() => state.value.count);

    function syncState(): void {
        state.value = tracker.getState();
    }

    async function captureSnapshot(filters: FeedUpdateFilters = {}): Promise<number | null> {
        const snapshot = await tracker.captureSnapshot(filters);
        syncState();

        return snapshot;
    }

    async function checkForUpdates(filters: FeedUpdateFilters = {}): Promise<void> {
        await tracker.checkForUpdates(filters);
        syncState();
    }

    async function acknowledgeDisplayed(
        snapshotId: number,
        filters: FeedUpdateFilters,
        displayedIds: number[],
        generation: number,
    ): Promise<boolean> {
        const acknowledged = await tracker.acknowledgeDisplayed(snapshotId, filters, displayedIds, generation);
        syncState();

        return acknowledged;
    }

    function beginContext(): number {
        const generation = tracker.beginContext();
        syncState();

        return generation;
    }

    function initializeContext(filters: FeedUpdateFilters, snapshotId: number, generation: number): boolean {
        const initialized = tracker.initializeContext(filters, snapshotId, generation);
        syncState();

        return initialized;
    }

    return { newArticleCount, captureSnapshot, checkForUpdates, acknowledgeDisplayed, beginContext, initializeContext };
}
