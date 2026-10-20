import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { mergeArticlePages, mergeSnapshotPage } from '../../resources/js/composables/feedArticlePages.ts';
import { FeedNotificationTracker, loadAtSnapshot } from '../../resources/js/composables/feedNotificationTracker.ts';
import { FeedUnreadCountState, runOptimisticMutation, sumCategoryUnreadCounts } from '../../resources/js/composables/feedUnreadCounts.ts';

const filters = { feed_id: 7 };
const result = (count, latest_id) => ({ count, latest_id });

test('keeps the notice in the sticky toolbar and articles on document scrolling', async () => {
    const page = await readFile(new URL('../../resources/js/pages/feeds/Index.vue', import.meta.url), 'utf8');
    const appHeader = await readFile(new URL('../../resources/js/components/AppSidebarHeader.vue', import.meta.url), 'utf8');
    const readStatus = await readFile(new URL('../../resources/js/composables/useReadStatus.ts', import.meta.url), 'utf8');
    const sidebar = await readFile(new URL('../../resources/js/components/AppSidebar.vue', import.meta.url), 'utf8');
    const toolbarIndex = page.indexOf('<!-- ── Sticky feed toolbar and notice');
    const toolbarClassIndex = page.indexOf('class="sticky top-0 z-10', toolbarIndex);
    const toolbarRowIndex = page.indexOf('data-slot="feed-toolbar-row"', toolbarIndex);
    const noticeRowIndex = page.indexOf('data-slot="feed-notification-row"', toolbarIndex);
    const transitionIndex = page.indexOf('<Transition', toolbarIndex);
    const articleContentIndex = page.indexOf('<!-- Main article content flows with the document. -->', transitionIndex);
    const modalIndex = page.indexOf('<!-- ── Feed Customization Modal');
    const toolbar = page.slice(toolbarIndex, articleContentIndex);
    const articles = page.slice(articleContentIndex, modalIndex);

    assert.ok(toolbarIndex >= 0 && toolbarIndex < toolbarClassIndex);
    assert.ok(toolbarClassIndex < toolbarRowIndex && toolbarRowIndex < noticeRowIndex && noticeRowIndex < transitionIndex);
    assert.match(toolbar, /sticky top-0 z-10/);
    assert.match(toolbar, /key="new-articles"/);
    assert.match(toolbar, /data-slot="feed-toolbar-row"[\s\S]*border-b border-border bg-background\/95 px-4 py-3[\s\S]*md:px-6/);
    assert.match(toolbar, /data-slot="feed-notification-row" class="px-4 md:px-6"/);
    assert.match(
        toolbar,
        /class="pointer-events-auto mx-auto mt-2 flex w-full max-w-2xl flex-wrap items-center justify-between gap-2 rounded-xl border border-primary\/15 bg-background\/95 px-3 py-2 text-sm text-foreground shadow-lg backdrop-blur-md"/,
    );
    assert.match(toolbar, /data-slot="feed-toolbar-row"[\s\S]*data-slot="feed-notification-row"/);
    assert.match(toolbar, /v-if="newArticleCount > 0 && !isSearchMode && loadedFilterSignature === JSON\.stringify\(updateFilters\(\)\)"/);
    assert.doesNotMatch(toolbar, /v-if="[^"]*scroll/i);
    assert.match(toolbar, /role="status" aria-live="polite" aria-atomic="true"/);
    assert.match(toolbar, /@click="refresh"/);
    assert.match(toolbar, /duration-\[250ms\]/);
    assert.match(toolbar, /motion-reduce:transition-none/);
    assert.match(toolbar, /enter-from-class="-translate-y-2 opacity-0/);
    assert.match(toolbar, /leave-to-class="-translate-y-2 opacity-0/);
    assert.doesNotMatch(page, /listScrollArea|100dvh/);
    assert.doesNotMatch(articles, /overflow-y-auto/);
    assert.match(page, /document\.querySelectorAll<HTMLElement>\('\[data-article-id\]'\)/);
    assert.match(page, /window\.scrollBy\(0, element\.getBoundingClientRect\(\)\.top - anchor\.top\)/);
    assert.match(readStatus, /_readIds\.value\.delete\(id\)[\s\S]*_unreadCounts\.rollbackRead\(feedId\)/);
    assert.match(readStatus, /return runOptimisticMutation\([\s\S]*_markAllSignal\.value =/);
    assert.match(sidebar, /if \(\!\(await markAllAsRead\(\{ categoryId \}\)\)\) return/);
    assert.match(sidebar, /if \(\!\(await markAllAsRead\(\{ feedId \}\)\)\) return/);
    assert.match(page, /if \(\!marked\) return;/);
    assert.match(appHeader, /class="flex h-16/);
    assert.doesNotMatch(appHeader, /\bsticky\b/);
    assert.match(page.slice(modalIndex), /max-h-\[90vh\].*overflow-y-auto/);
    assert.match(toolbar, /data-slot="feed-toolbar-row"[\s\S]*data-slot="feed-notification-row"/);
    assert.match(page, /setInterval\(\(\) => \{[\s\S]*void checkForUpdates\(filters\);[\s\S]*\}, 30_000\)/);
    assert.match(page, /if \(entries\[0\]\.isIntersecting && hasMore\.value[\s\S]*loadArticles\(false\)/);
    assert.match(page, /if \(!reset && options\.snapshotId !== undefined\)[\s\S]*acknowledgeDisplayed\(/);
    const updatePollIndex = page.indexOf('updatePoll = setInterval');
    const updatePoll = page.slice(updatePollIndex, page.indexOf('window.addEventListener', updatePollIndex));
    assert.doesNotMatch(updatePoll, /loadArticles\(/);
});

test('reconciles unread snapshots with optimistic reads and repeated polls', () => {
    const counts = new FeedUnreadCountState();
    const initial = counts.beginSnapshotRequest();
    assert.equal(counts.acceptSnapshot({ 7: 5 }, initial), true);

    counts.addRead(7);
    const startedBeforeReadPatch = counts.beginSnapshotRequest();
    assert.equal(counts.acceptSnapshot({ 7: 5 }, startedBeforeReadPatch), true);
    assert.equal(counts.unreadCount(7, 99), 4);

    counts.confirmRead(7);
    const afterReadPatch = counts.beginSnapshotRequest();
    assert.equal(counts.acceptSnapshot({ 7: 4 }, afterReadPatch), true);
    assert.equal(counts.unreadCount(7, 99), 4);
    assert.deepEqual(counts.getReadDeltas(), new Map());

    const repeatedPoll = counts.beginSnapshotRequest();
    assert.equal(counts.acceptSnapshot({ 7: 4 }, repeatedPoll), true);
    assert.equal(counts.unreadCount(7, 99), 4);
});

test('rolls back rejected read mutations and permits a later retry', async () => {
    const counts = new FeedUnreadCountState();
    const readIds = new Set([42]);
    counts.addRead(7);
    const failed = await runOptimisticMutation(
        async () => {
            throw new Error('read request failed');
        },
        () => counts.confirmRead(7),
        () => {
            readIds.delete(42);
            counts.rollbackRead(7);
        },
    );

    assert.equal(failed, false);
    assert.equal(readIds.has(42), false);
    assert.equal(counts.unreadCount(7, 5), 5);

    readIds.add(42);
    counts.addRead(7);
    const retried = await runOptimisticMutation(
        async () => ({ ok: true }),
        () => counts.confirmRead(7),
        () => counts.rollbackRead(7),
    );
    assert.equal(retried, true);

    const afterRetry = counts.beginSnapshotRequest();
    assert.equal(counts.acceptSnapshot({ 7: 4 }, afterRetry), true);
    assert.equal(counts.unreadCount(7, 5), 4);
});

test('does not apply mark-all state after a rejected request', async () => {
    const counts = new FeedUnreadCountState();
    const initial = counts.beginSnapshotRequest();
    counts.acceptSnapshot({ 7: 5 }, initial);
    let markAllSignalSent = false;

    const failed = await runOptimisticMutation(
        async () => {
            throw new Error('mark-all request failed');
        },
        () => {
            markAllSignalSent = true;
            counts.markAll([7]);
        },
        () => {},
    );

    assert.equal(failed, false);
    assert.equal(markAllSignalSent, false);
    assert.equal(counts.unreadCount(7, 99), 5);
});

test('defers a count response if a read mutation changes while it is in flight', () => {
    const counts = new FeedUnreadCountState();
    const initial = counts.beginSnapshotRequest();
    counts.acceptSnapshot({ 7: 5 }, initial);
    counts.addRead(7);
    const requestStartedDuringRead = counts.beginSnapshotRequest();
    counts.confirmRead(7);

    assert.equal(counts.acceptSnapshot({ 7: 4 }, requestStartedDuringRead), true);
    assert.equal(counts.unreadCount(7, 5), 4);

    const laterRequest = counts.beginSnapshotRequest();
    assert.equal(counts.acceptSnapshot({ 7: 4 }, laterRequest), true);
    assert.equal(counts.unreadCount(7, 5), 4);
    assert.deepEqual(counts.getReadDeltas(), new Map());
});

test('ignores stale count responses and retains the last snapshot after a failed request', () => {
    const counts = new FeedUnreadCountState();
    const initial = counts.beginSnapshotRequest();
    counts.acceptSnapshot({ 7: 3 }, initial);
    const staleRequest = counts.beginSnapshotRequest();
    const latestRequest = counts.beginSnapshotRequest();

    assert.equal(counts.acceptSnapshot({ 7: 12 }, staleRequest), false);
    // A failed latest request never calls acceptSnapshot, leaving the prior successful state intact.
    assert.equal(counts.unreadCount(7, 99), 3);
    assert.equal(counts.acceptSnapshot({ 7: 2 }, latestRequest), true);
    assert.equal(counts.unreadCount(7, 99), 2);
});

test('keeps a mark-all result optimistic across older polls, then accepts a later count snapshot', () => {
    const counts = new FeedUnreadCountState();
    const initial = counts.beginSnapshotRequest();
    counts.acceptSnapshot({ 7: 8 }, initial);
    const oldPoll = counts.beginSnapshotRequest();

    counts.markAll([7]);
    assert.equal(counts.unreadCount(7, 99), 0);
    assert.equal(counts.acceptSnapshot({ 7: 8 }, oldPoll), true);
    assert.equal(counts.unreadCount(7, 99), 0);

    const pollAfterMarkAll = counts.beginSnapshotRequest();
    assert.equal(counts.acceptSnapshot({ 7: 2 }, pollAfterMarkAll), true);
    assert.equal(counts.unreadCount(7, 99), 2);
    assert.deepEqual(counts.getZeroedFeeds(), new Set());
});

test('sums unread counts by the category membership supplied for active sidebar feeds', () => {
    const feeds = [
        { id: 7, category_id: 2 },
        { id: 8, category_id: 2 },
        { id: 9, category_id: 3 },
        { id: 10, category_id: null },
    ];
    const counts = new Map([
        [7, 4],
        [8, 0],
        [9, 3],
        [10, 5],
    ]);

    assert.deepEqual(
        sumCategoryUnreadCounts(feeds, (feed) => counts.get(feed.id) ?? 0),
        new Map([
            [2, 4],
            [3, 3],
        ]),
    );
});

function trackerWith(handler) {
    const calls = [];
    const tracker = new FeedNotificationTracker(async (params, method) => {
        calls.push({ params, method });

        return handler(params, calls.length);
    });
    const generation = tracker.beginContext();
    tracker.initializeContext(filters, 100, generation);

    return { tracker, calls, generation };
}

test('keeps partial arrivals pending until every relevant article is displayed', async () => {
    const { tracker, generation } = trackerWith((params) => {
        if (params.through_id === 125 && params.exclude_ids.length === 20) return result(5, 125);
        return result(0, 125);
    });

    const firstPageIds = Array.from({ length: 20 }, (_, index) => 101 + index);
    assert.equal(await tracker.acknowledgeDisplayed(125, filters, firstPageIds, generation), true);
    assert.deepEqual(tracker.getState(), {
        count: 5,
        importedId: 125,
        acknowledgedId: 100,
        displayedIds: firstPageIds,
        initialized: true,
    });

    assert.equal(await tracker.acknowledgeDisplayed(125, filters, firstPageIds, generation), true);
    assert.equal(tracker.getState().count, 5);
    assert.equal(tracker.getState().acknowledgedId, 100);

    assert.equal(
        await tracker.acknowledgeDisplayed(
            125,
            filters,
            Array.from({ length: 25 }, (_, index) => 101 + index),
            generation,
        ),
        true,
    );
    assert.equal(tracker.getState().count, 0);
    assert.equal(tracker.getState().acknowledgedId, 125);
    assert.deepEqual(tracker.getState().displayedIds, []);
});

test('failed acknowledgement retains displayed IDs so a later poll does not reannounce them', async () => {
    let failAck = true;
    const { tracker, generation, calls } = trackerWith((params) => {
        if (params.through_id && failAck) {
            failAck = false;
            throw new Error('ack failed');
        }
        return result(3, 105);
    });

    assert.equal(await tracker.acknowledgeDisplayed(105, filters, [101, 102], generation), false);
    assert.deepEqual(tracker.getState().displayedIds, [101, 102]);
    await tracker.checkForUpdates(filters);

    assert.deepEqual(calls[1].params.exclude_ids, [101, 102]);
    assert.equal(calls[1].params.after_id, 100);
    assert.equal(calls[1].method, 'POST');
    assert.equal(tracker.getState().count, 3);
    assert.equal(tracker.getState().acknowledgedId, 100);
});

test('ignores overlapping poll requests and applies only the in-flight response', async () => {
    let resolveRequest;
    let callCount = 0;
    const pollingTracker = new FeedNotificationTracker(() => {
        callCount++;
        return new Promise((resolve) => (resolveRequest = resolve));
    });
    const generation = pollingTracker.beginContext();
    pollingTracker.initializeContext(filters, 100, generation);

    const first = pollingTracker.checkForUpdates(filters);
    const second = pollingTracker.checkForUpdates(filters);
    assert.equal(callCount, 1);
    resolveRequest(result(4, 104));
    await Promise.all([first, second]);

    assert.equal(pollingTracker.getState().count, 4);
});

test('keeps pending notices when scrolling triggers pagination at the acknowledged snapshot', async () => {
    const calls = [];
    const tracker = new FeedNotificationTracker(async (params, method) => {
        calls.push({ params, method });

        return params.after_id === 100 ? result(5, 105) : result(0, 100);
    });
    const generation = tracker.beginContext();
    tracker.initializeContext(filters, 100, generation);

    await tracker.checkForUpdates(filters);
    assert.equal(tracker.getState().count, 5);

    // Fast document scrolling reaches the load-more observer, which asks the
    // tracker to acknowledge articles from the already-acknowledged snapshot.
    assert.equal(await tracker.acknowledgeDisplayed(100, filters, [80, 81, 82], generation), false);
    assert.equal(calls.length, 1);
    assert.equal(tracker.getState().count, 5);
    assert.equal(tracker.getState().acknowledgedId, 100);
});

test('counts arrivals beyond an acknowledged older list snapshot before clearing the notice', async () => {
    const calls = [];
    let resolveRemaining;
    const tracker = new FeedNotificationTracker((params) => {
        calls.push(params);
        if (params.through_id === 105) return Promise.resolve(result(0, 110));
        if (params.after_id === 105) return new Promise((resolve) => (resolveRemaining = resolve));

        return Promise.resolve(result(10, 110));
    });
    const generation = tracker.beginContext();
    tracker.initializeContext(filters, 100, generation);

    await tracker.checkForUpdates(filters);
    assert.equal(tracker.getState().count, 10);

    const acknowledgement = tracker.acknowledgeDisplayed(105, filters, [101, 102, 103, 104, 105], generation);
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(calls.length, 3);
    assert.equal(calls[2].after_id, 105);
    assert.equal(tracker.getState().count, 10);
    assert.equal(tracker.getState().acknowledgedId, 100);

    resolveRemaining(result(5, 110));
    assert.equal(await acknowledgement, true);
    assert.equal(tracker.getState().count, 5);
    assert.equal(tracker.getState().acknowledgedId, 105);
});

test('keeps the prior count and watermark if the post-snapshot recount fails', async () => {
    const tracker = new FeedNotificationTracker(async (params) => {
        if (params.through_id === 105) return result(0, 110);
        if (params.after_id === 105) throw new Error('recount failed');

        return result(10, 110);
    });
    const generation = tracker.beginContext();
    tracker.initializeContext(filters, 100, generation);

    await tracker.checkForUpdates(filters);
    assert.equal(await tracker.acknowledgeDisplayed(105, filters, [101, 102, 103, 104, 105], generation), false);
    assert.equal(tracker.getState().count, 10);
    assert.equal(tracker.getState().acknowledgedId, 100);
});

test('keeps an existing notice visible when a poll request fails', async () => {
    let shouldFail = false;
    const { tracker } = trackerWith(() => {
        if (shouldFail) throw new Error('poll failed');

        return result(4, 104);
    });

    await tracker.checkForUpdates(filters);
    assert.equal(tracker.getState().count, 4);

    shouldFail = true;
    await tracker.checkForUpdates(filters);
    assert.equal(tracker.getState().count, 4);
    assert.equal(tracker.getState().acknowledgedId, 100);
});

test('routine polls recompute pending arrivals without adding the same articles twice', async () => {
    const { tracker } = trackerWith(() => result(2, 102));

    await tracker.checkForUpdates(filters);
    await tracker.checkForUpdates(filters);

    assert.equal(tracker.getState().count, 2);
    assert.equal(tracker.getState().importedId, 102);
    assert.equal(tracker.getState().acknowledgedId, 100);
});

test('failed snapshot stays uninitialized and a later snapshot can recover', async () => {
    let shouldFail = true;
    const snapshotTracker = new FeedNotificationTracker(async () => {
        if (shouldFail) throw new Error('snapshot failed');
        return result(0, 150);
    });

    assert.equal(await snapshotTracker.captureSnapshot(filters), null);
    assert.equal(snapshotTracker.getState().initialized, false);
    assert.equal(snapshotTracker.getState().acknowledgedId, null);

    shouldFail = false;
    const snapshot = await snapshotTracker.captureSnapshot(filters);
    const generation = snapshotTracker.beginContext();
    snapshotTracker.initializeContext(filters, snapshot, generation);
    assert.equal(snapshotTracker.getState().acknowledgedId, 150);
});

test('does not fetch an unbounded list when snapshot capture fails', async () => {
    let listCalls = 0;
    const snapshot = await loadAtSnapshot(
        async () => null,
        async () => {
            listCalls++;

            return true;
        },
    );

    assert.equal(snapshot, null);
    assert.equal(listCalls, 0);
});

test('stale snapshot completion never starts a page loader or changes its loading flags', async () => {
    let currentGeneration = 'A';
    let resolveA;
    let resolveB;
    const loaderCalls = [];
    let loadingGeneration = null;
    const loadA = loadAtSnapshot(
        () => new Promise((resolve) => (resolveA = resolve)),
        async (snapshot) => {
            loaderCalls.push(['A', snapshot]);
            loadingGeneration = 'A';
            return true;
        },
        () => currentGeneration === 'A',
    );
    currentGeneration = 'B';
    const loadB = loadAtSnapshot(
        () => new Promise((resolve) => (resolveB = resolve)),
        async (snapshot) => {
            loaderCalls.push(['B', snapshot]);
            loadingGeneration = 'B';
            return true;
        },
        () => currentGeneration === 'B',
    );

    resolveB(200);
    assert.equal(await loadB, 200);
    resolveA(100);
    assert.equal(await loadA, null);
    assert.deepEqual(loaderCalls, [['B', 200]]);
    assert.equal(loadingGeneration, 'B');
});

test('refresh page retains a cursor from the new snapshot and dedupes later pages', () => {
    const oldArticles = [
        { id: 1, published_at: '2025-01-01T00:00:00Z' },
        { id: 2, published_at: '2025-01-02T00:00:00Z' },
    ];
    const twentyNewest = Array.from({ length: 20 }, (_, index) => ({
        id: 106 + index,
        published_at: `2025-02-${String(20 - index).padStart(2, '0')}T00:00:00Z`,
    }));
    const refreshed = mergeSnapshotPage(oldArticles, twentyNewest, 'snapshot-cursor-after-20');

    assert.equal(refreshed.nextCursor, 'snapshot-cursor-after-20');
    assert.equal(refreshed.items.length, 22);

    const remainingFive = Array.from({ length: 5 }, (_, index) => ({
        id: 101 + index,
        published_at: `2025-02-${String(5 - index).padStart(2, '0')}T00:00:00Z`,
    }));
    const nextPage = mergeArticlePages(refreshed.items, [...remainingFive, oldArticles[0]]);

    assert.equal(nextPage.length, 27);
    assert.equal(new Set(nextPage.map((article) => article.id)).size, 27);
});

test('posts large displayed-ID sets without query-string encoding', async () => {
    let captured;
    const tracker = new FeedNotificationTracker(async (params, method) => {
        captured = { params, method };
        return result(1, 1201);
    });
    const generation = tracker.beginContext();
    tracker.initializeContext(filters, 100, generation);
    const displayedIds = Array.from({ length: 1100 }, (_, index) => 101 + index);

    await tracker.acknowledgeDisplayed(1201, filters, displayedIds, generation);

    assert.equal(captured.method, 'POST');
    assert.equal(captured.params.exclude_ids.length, 1100);
    assert.ok(JSON.stringify(captured.params).length < 16_000);
});

test('stale context generation cannot acknowledge a newer context', async () => {
    const { tracker, generation } = trackerWith(() => result(0, 120));
    tracker.beginContext();

    assert.equal(await tracker.acknowledgeDisplayed(120, filters, [101], generation), false);
    assert.equal(tracker.getState().acknowledgedId, 100);
});
