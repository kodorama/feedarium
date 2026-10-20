export interface FeedArticlePage<T> {
    items: T[];
    nextCursor: string | null;
}

export function mergeArticlePages<T extends { id: number; published_at: string | null }>(existing: T[], incoming: T[]): T[] {
    const byId = new Map<number, T>();
    for (const article of existing) byId.set(article.id, article);
    for (const article of incoming) byId.set(article.id, article);

    return [...byId.values()].sort((a, b) => {
        const dateOrder = (b.published_at ?? '').localeCompare(a.published_at ?? '');

        return dateOrder || b.id - a.id;
    });
}

export function mergeSnapshotPage<T extends { id: number; published_at: string | null }>(
    existing: T[],
    incoming: T[],
    nextCursor: string | null,
): FeedArticlePage<T> {
    return { items: mergeArticlePages(existing, incoming), nextCursor };
}
