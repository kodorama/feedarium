/** Prevent a stale OPML preview response from being applied to another file selection. */
export function createPreviewGeneration() {
    let generation = 0;
    let selectedFile: File | null = null;

    return {
        select(file: File | null) {
            selectedFile = file;
            generation += 1;
        },
        begin(file: File | null): number {
            if (selectedFile !== file) return -1;
            return ++generation;
        },
        isCurrent(file: File | null, requestGeneration: number): boolean {
            return generation === requestGeneration && selectedFile === file;
        },
    };
}
