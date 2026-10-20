import axios from 'axios';
import { ref, triggerRef } from 'vue';
import { FeedUnreadCountState, runOptimisticMutation, type UnreadCountRequestToken } from './feedUnreadCounts';

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
/** feedIds where mark-all-read has been triggered — sidebar shows 0 for these */
const _zeroedFeeds = ref(new Set<number>());
const _unreadCountSnapshotVersion = ref(0);
const _unreadCounts = new FeedUnreadCountState();
let _markAllRequestId = 0;

// ---------------------------------------------------------------------------

export function useReadStatus() {
    function syncUnreadCountRefs(): void {
        _feedReadDelta.value = _unreadCounts.getReadDeltas();
        _zeroedFeeds.value = _unreadCounts.getZeroedFeeds();
        triggerRef(_feedReadDelta);
        triggerRef(_zeroedFeeds);
    }

    function beginUnreadCountRequest(): UnreadCountRequestToken {
        return _unreadCounts.beginSnapshotRequest();
    }

    function acceptUnreadCountSnapshot(counts: Record<number, number>, token: UnreadCountRequestToken): void {
        if (!_unreadCounts.acceptSnapshot(counts, token)) return;
        _unreadCountSnapshotVersion.value++;
        syncUnreadCountRefs();
    }

    function isRead(id: number): boolean {
        return _readIds.value.has(id);
    }

    function addRead(id: number, feedId?: number): void {
        _readIds.value.add(id);
        triggerRef(_readIds);
        if (feedId != null) {
            _unreadCounts.addRead(feedId);
            _feedReadDelta.value = _unreadCounts.getReadDeltas();
            triggerRef(_feedReadDelta);
        }
    }

    function addReads(ids: number[], feedIds?: number[]): void {
        for (let i = 0; i < ids.length; i++) {
            _readIds.value.add(ids[i]);
            const fid = feedIds?.[i];
            if (fid != null) _unreadCounts.addRead(fid);
        }
        triggerRef(_readIds);
        _feedReadDelta.value = _unreadCounts.getReadDeltas();
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
        await runOptimisticMutation(
            () => axios.patch(`/api/news/${id}/read`),
            () => {
                if (feedId != null) _unreadCounts.confirmRead(feedId);
            },
            () => {
                _readIds.value.delete(id);
                triggerRef(_readIds);
                if (feedId != null) {
                    _unreadCounts.rollbackRead(feedId);
                    syncUnreadCountRefs();
                }
            },
        );
    }

    async function markAllAsRead(params: MarkAllParams = {}): Promise<boolean> {
        const requestId = ++_markAllRequestId;

        return runOptimisticMutation(
            async () => {
                const response = await axios.post<{
                    unread_counts?: Record<number, number>;
                    affected_feed_ids?: number[];
                }>('/api/news/read-all', {
                    ...(params.feedId != null && { feed_id: params.feedId }),
                    ...(params.categoryId != null && { category_id: params.categoryId }),
                });

                if (response.data.unread_counts && response.data.affected_feed_ids) {
                    const applied = _unreadCounts.applyAuthoritativeSnapshot(response.data.unread_counts, response.data.affected_feed_ids, requestId);
                    if (applied) {
                        _unreadCountSnapshotVersion.value++;
                        syncUnreadCountRefs();
                    }
                }

                return response;
            },
            () => {
                _markAllSignal.value = { ...params, nonce: Date.now() };
            },
            () => {},
        );
    }

    /** Returns adjusted unread count: 0 if mark-all was triggered, otherwise serverCount minus local read delta. */
    function adjustedFeedCount(feedId: number, serverCount: number): number {
        void _unreadCountSnapshotVersion.value;

        return _unreadCounts.unreadCount(feedId, serverCount);
    }

    /** Mark a feed's unread count as zero (called after mark-all-read for that feed). */
    function zeroFeedUnread(feedId: number): void {
        _unreadCounts.markAll([feedId]);
        syncUnreadCountRefs();
    }

    /** Mark multiple feeds' unread counts as zero in one batched update. */
    function zeroFeedsUnread(feedIds: number[]): void {
        _unreadCounts.markAll(feedIds);
        syncUnreadCountRefs();
    }

    /** Zero out the delta for a feed (after mark-all). */
    function clearFeedDelta(feedId: number): void {
        _unreadCounts.clearReadDelta(feedId);
        _feedReadDelta.value = _unreadCounts.getReadDeltas();
        triggerRef(_feedReadDelta);
    }

    return {
        readIds: _readIds,
        feedReadDelta: _feedReadDelta,
        zeroedFeeds: _zeroedFeeds,
        markAllSignal: _markAllSignal,
        isRead,
        addRead,
        addReads,
        populateFromArticles,
        markAsRead,
        markAllAsRead,
        adjustedFeedCount,
        zeroFeedUnread,
        zeroFeedsUnread,
        clearFeedDelta,
        beginUnreadCountRequest,
        acceptUnreadCountSnapshot,
    };
}
