import axios from 'axios';
import { ref, triggerRef } from 'vue';

export interface MarkAllParams {
    feedId?: number;
    categoryId?: number;
}

// ---------------------------------------------------------------------------
// Module-level singletons
// ---------------------------------------------------------------------------
const _readIds = ref(new Set<number>());
const _markAllSignal = ref<(MarkAllParams & { nonce: number }) | null>(null);
/** feedId → number of articles marked read this session (for badge decrement) */
const _feedReadDelta = ref(new Map<number, number>());

// ---------------------------------------------------------------------------

export function useReadStatus() {
    function isRead(id: number): boolean {
        return _readIds.value.has(id);
    }

    function addRead(id: number, feedId?: number): void {
        _readIds.value.add(id);
        triggerRef(_readIds);
        if (feedId != null) {
            _feedReadDelta.value.set(feedId, (_feedReadDelta.value.get(feedId) ?? 0) + 1);
            triggerRef(_feedReadDelta);
        }
    }

    function addReads(ids: number[], feedIds?: number[]): void {
        for (let i = 0; i < ids.length; i++) {
            _readIds.value.add(ids[i]);
            const fid = feedIds?.[i];
            if (fid != null) {
                _feedReadDelta.value.set(fid, (_feedReadDelta.value.get(fid) ?? 0) + 1);
            }
        }
        triggerRef(_readIds);
        triggerRef(_feedReadDelta);
    }

    function populateFromArticles(articles: Array<{ id: number; is_read: boolean }>): void {
        for (const a of articles) {
            if (a.is_read) _readIds.value.add(a.id);
        }
        triggerRef(_readIds);
    }

    async function markAsRead(id: number, feedId?: number): Promise<void> {
        if (_readIds.value.has(id)) return;
        addRead(id, feedId);
        await axios.patch(`/api/news/${id}/read`).catch(() => {});
    }

    async function markAllAsRead(params: MarkAllParams = {}): Promise<void> {
        await axios
            .post('/api/news/read-all', {
                ...(params.feedId != null && { feed_id: params.feedId }),
                ...(params.categoryId != null && { category_id: params.categoryId }),
            })
            .catch(() => {});
        _markAllSignal.value = { ...params, nonce: Date.now() };
    }

    /** Returns adjusted unread count: serverCount minus local read delta for that feed. */
    function adjustedFeedCount(feedId: number, serverCount: number): number {
        return Math.max(0, serverCount - (_feedReadDelta.value.get(feedId) ?? 0));
    }

    /** Zero out the delta for a feed (after mark-all). */
    function clearFeedDelta(feedId: number): void {
        _feedReadDelta.value.delete(feedId);
        triggerRef(_feedReadDelta);
    }

    return {
        readIds: _readIds,
        feedReadDelta: _feedReadDelta,
        markAllSignal: _markAllSignal,
        isRead,
        addRead,
        addReads,
        populateFromArticles,
        markAsRead,
        markAllAsRead,
        adjustedFeedCount,
        clearFeedDelta,
    };
}
