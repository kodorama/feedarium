export interface UnreadCountRequestToken {
    id: number;
    confirmedReads: Record<number, number>;
    mutationRevisions: Record<number, number>;
}

export async function runOptimisticMutation(mutation: () => Promise<unknown>, onSuccess: () => void, onFailure: () => void): Promise<boolean> {
    try {
        await mutation();
        onSuccess();

        return true;
    } catch {
        onFailure();

        return false;
    }
}

export function sumCategoryUnreadCounts<T extends { id: number; category_id: number | null }>(
    feeds: T[],
    unreadCountForFeed: (feed: T) => number,
): Map<number, number> {
    const counts = new Map<number, number>();
    for (const feed of feeds) {
        if (feed.category_id === null) continue;
        counts.set(feed.category_id, (counts.get(feed.category_id) ?? 0) + unreadCountForFeed(feed));
    }

    return counts;
}

/** Reconciles periodic server counts with optimistic read and mark-all actions. */
export class FeedUnreadCountState {
    private snapshot: Map<number, number> | null = null;
    private readDeltas = new Map<number, number>();
    private confirmedReads = new Map<number, number>();
    private zeroedFeeds = new Set<number>();
    private mutationRevisions = new Map<number, number>();
    private deferredSnapshotFeeds = new Set<number>();
    private requestId = 0;

    addRead(feedId: number): void {
        this.readDeltas.set(feedId, (this.readDeltas.get(feedId) ?? 0) + 1);
        this.bumpMutationRevision(feedId);
    }

    confirmRead(feedId: number): void {
        this.confirmedReads.set(feedId, (this.confirmedReads.get(feedId) ?? 0) + 1);
        this.bumpMutationRevision(feedId);
    }

    rollbackRead(feedId: number): void {
        const remainingReadDelta = (this.readDeltas.get(feedId) ?? 0) - 1;
        if (remainingReadDelta > 0) this.readDeltas.set(feedId, remainingReadDelta);
        else this.readDeltas.delete(feedId);
        this.bumpMutationRevision(feedId);
    }

    markAll(feedIds: number[]): void {
        for (const feedId of feedIds) {
            this.zeroedFeeds.add(feedId);
            this.readDeltas.delete(feedId);
            this.confirmedReads.delete(feedId);
            this.bumpMutationRevision(feedId);
        }
    }

    clearReadDelta(feedId: number): void {
        this.readDeltas.delete(feedId);
        this.confirmedReads.delete(feedId);
        this.bumpMutationRevision(feedId);
    }

    beginSnapshotRequest(): UnreadCountRequestToken {
        this.requestId++;

        return {
            id: this.requestId,
            confirmedReads: Object.fromEntries(this.confirmedReads),
            mutationRevisions: Object.fromEntries(this.mutationRevisions),
        };
    }

    acceptSnapshot(counts: Record<number, number>, token: UnreadCountRequestToken): boolean {
        if (token.id !== this.requestId) return false;

        const nextSnapshot = new Map<number, number>();
        const deferredFeeds = new Set<number>();
        for (const [feedIdText, count] of Object.entries(counts)) {
            const feedId = Number(feedIdText);
            const currentMutationRevision = this.mutationRevisions.get(feedId) ?? 0;
            const requestedMutationRevision = token.mutationRevisions[feedId] ?? 0;

            if (currentMutationRevision > requestedMutationRevision) {
                const priorCount = this.snapshot?.get(feedId);
                if (priorCount !== undefined) nextSnapshot.set(feedId, priorCount);
                else deferredFeeds.add(feedId);
                continue;
            }

            nextSnapshot.set(feedId, Math.max(0, Number(count) || 0));
            this.deferredSnapshotFeeds.delete(feedId);

            if (this.zeroedFeeds.delete(feedId)) {
                this.readDeltas.delete(feedId);
                this.confirmedReads.delete(feedId);
                continue;
            }

            const confirmedAtRequest = token.confirmedReads[feedId] ?? 0;
            const confirmedNow = this.confirmedReads.get(feedId) ?? 0;
            const reconciledReads = Math.min(confirmedAtRequest, confirmedNow);
            if (reconciledReads > 0) {
                const remainingReadDelta = (this.readDeltas.get(feedId) ?? 0) - reconciledReads;
                if (remainingReadDelta > 0) this.readDeltas.set(feedId, remainingReadDelta);
                else this.readDeltas.delete(feedId);
                const remainingConfirmedReads = confirmedNow - reconciledReads;
                if (remainingConfirmedReads > 0) this.confirmedReads.set(feedId, remainingConfirmedReads);
                else this.confirmedReads.delete(feedId);
            }
        }

        for (const [feedId, currentMutationRevision] of this.mutationRevisions) {
            if (Object.hasOwn(counts, feedId) || currentMutationRevision <= (token.mutationRevisions[feedId] ?? 0)) continue;

            const priorCount = this.snapshot?.get(feedId);
            if (priorCount !== undefined) nextSnapshot.set(feedId, priorCount);
            else deferredFeeds.add(feedId);
        }

        this.snapshot = nextSnapshot;
        this.deferredSnapshotFeeds = deferredFeeds;

        return true;
    }

    unreadCount(feedId: number, fallback: number): number {
        if (this.zeroedFeeds.has(feedId)) return 0;

        const serverCount = this.snapshot === null || this.deferredSnapshotFeeds.has(feedId) ? fallback : (this.snapshot.get(feedId) ?? 0);

        return Math.max(0, serverCount - (this.readDeltas.get(feedId) ?? 0));
    }

    getReadDeltas(): Map<number, number> {
        return new Map(this.readDeltas);
    }

    getZeroedFeeds(): Set<number> {
        return new Set(this.zeroedFeeds);
    }

    private bumpMutationRevision(feedId: number): void {
        this.mutationRevisions.set(feedId, (this.mutationRevisions.get(feedId) ?? 0) + 1);
    }
}
