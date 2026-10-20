export interface FeedUpdateFilters {
    feed_id?: number;
    category_id?: number;
    unread_only?: number;
}

export interface FeedUpdateResponse {
    count: number;
    latest_id: number;
    unread_counts?: Record<number, number>;
}

export type FeedUpdateRequest = (params: Record<string, unknown>, method: 'GET' | 'POST') => Promise<FeedUpdateResponse>;

export async function loadAtSnapshot(
    capture: () => Promise<number | null>,
    load: (snapshotId: number) => Promise<boolean>,
    isCurrent: () => boolean = () => true,
): Promise<number | null> {
    const snapshotId = await capture();
    if (snapshotId === null || !isCurrent() || !(await load(snapshotId))) return null;

    return snapshotId;
}

/** Tracks the imported high-water mark separately from articles displayed and acknowledged. */
export class FeedNotificationTracker {
    private importedId: number | null = null;
    private acknowledgedId: number | null = null;
    private displayedIds = new Set<number>();
    private activeFilters = '';
    private generation = 0;
    private revision = 0;
    private checking = false;
    private initialized = false;
    private count = 0;
    private readonly request: FeedUpdateRequest;

    constructor(request: FeedUpdateRequest) {
        this.request = request;
    }

    getState() {
        return {
            count: this.count,
            importedId: this.importedId,
            acknowledgedId: this.acknowledgedId,
            displayedIds: [...this.displayedIds],
            initialized: this.initialized,
        };
    }

    beginContext(): number {
        this.generation++;
        this.revision++;

        return this.generation;
    }

    initializeContext(filters: FeedUpdateFilters, snapshotId: number, generation: number): boolean {
        if (generation !== this.generation) return false;

        this.activeFilters = JSON.stringify(filters);
        this.importedId = snapshotId;
        this.acknowledgedId = snapshotId;
        this.displayedIds.clear();
        this.count = 0;
        this.initialized = true;
        this.revision++;

        return true;
    }

    async captureSnapshot(filters: FeedUpdateFilters): Promise<number | null> {
        try {
            const response = await this.request({ ...filters }, 'GET');

            return response.latest_id;
        } catch {
            return null;
        }
    }

    async checkForUpdates(filters: FeedUpdateFilters): Promise<void> {
        if (this.checking || !this.initialized || this.activeFilters !== JSON.stringify(filters) || this.acknowledgedId === null) return;

        this.checking = true;
        const generation = this.generation;
        const revision = this.revision;
        const acknowledgedId = this.acknowledgedId;

        try {
            const response = await this.request(
                {
                    ...filters,
                    after_id: acknowledgedId,
                    exclude_ids: [...this.displayedIds],
                },
                'POST',
            );
            if (generation !== this.generation || revision !== this.revision) return;

            this.importedId = Math.max(this.importedId ?? 0, response.latest_id);
            this.count = response.count;
            if (this.count === 0) this.acknowledgeThrough(response.latest_id);
        } catch {
            // Keep the previous imported watermark and count; a later poll retries.
        } finally {
            this.checking = false;
        }
    }

    async acknowledgeDisplayed(snapshotId: number, filters: FeedUpdateFilters, displayedIds: number[], generation: number): Promise<boolean> {
        if (
            generation !== this.generation ||
            !this.initialized ||
            this.activeFilters !== JSON.stringify(filters) ||
            this.acknowledgedId === null ||
            snapshotId <= this.acknowledgedId
        )
            return false;

        for (const id of displayedIds) {
            if (id > this.acknowledgedId && id <= snapshotId) this.displayedIds.add(id);
        }
        this.revision++;
        if (this.checking) return false;

        this.checking = true;
        const revision = this.revision;
        const acknowledgedId = this.acknowledgedId;

        try {
            const response = await this.request(
                {
                    ...filters,
                    after_id: acknowledgedId,
                    through_id: snapshotId,
                    exclude_ids: [...this.displayedIds],
                },
                'POST',
            );
            if (generation !== this.generation || revision !== this.revision) return false;

            let pendingCount = response.count;
            const latestId = Math.max(this.importedId ?? 0, response.latest_id);
            if (latestId > snapshotId) {
                const remaining = await this.request(
                    {
                        ...filters,
                        after_id: snapshotId,
                        exclude_ids: [...this.displayedIds].filter((id) => id > snapshotId),
                    },
                    'POST',
                );
                if (generation !== this.generation || revision !== this.revision) return false;

                pendingCount += remaining.count;
                this.importedId = Math.max(latestId, remaining.latest_id);
            } else {
                this.importedId = latestId;
            }

            if (response.count === 0) {
                this.acknowledgeThrough(snapshotId);
                this.count = pendingCount;
            } else {
                this.count = pendingCount;
            }

            return true;
        } catch {
            return false;
        } finally {
            this.checking = false;
        }
    }

    private acknowledgeThrough(id: number): void {
        this.acknowledgedId = Math.max(this.acknowledgedId ?? 0, id);
        this.displayedIds = new Set([...this.displayedIds].filter((displayedId) => displayedId > id));
        this.count = 0;
        this.revision++;
    }
}
