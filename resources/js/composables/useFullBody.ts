import axios from 'axios';

/**
 * Module-level cache so full bodies are fetched at most once per article per session.
 * `undefined` means not yet fetched; `null` means fetched but no body available.
 */
const bodyCache = new Map<number, string | null>();

/** Deduplicates concurrent requests for the same article ID. */
const pending = new Map<number, Promise<string | null>>();

export function useFullBody() {
    /**
     * Fetch the full body for a given article ID.
     * Results are cached at module level — subsequent calls return immediately from cache.
     */
    async function fetchFullBody(articleId: number): Promise<string | null> {
        if (bodyCache.has(articleId)) {
            return bodyCache.get(articleId) ?? null;
        }

        if (pending.has(articleId)) {
            return pending.get(articleId)!;
        }

        const promise = axios
            .get<{ full_body: string | null }>(`/api/news/${articleId}/body`)
            .then((res) => {
                const body = res.data.full_body ?? null;
                bodyCache.set(articleId, body);
                return body;
            })
            .catch(() => {
                bodyCache.set(articleId, null);
                return null;
            })
            .finally(() => {
                pending.delete(articleId);
            });

        pending.set(articleId, promise);
        return promise;
    }

    /**
     * Return the cached full body without triggering a network request.
     * Returns `undefined` when the body has not been fetched yet.
     */
    function getCachedBody(articleId: number): string | null | undefined {
        return bodyCache.has(articleId) ? (bodyCache.get(articleId) ?? null) : undefined;
    }

    return { fetchFullBody, getCachedBody };
}

