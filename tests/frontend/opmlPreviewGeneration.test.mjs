import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { createPreviewGeneration } from '../../resources/js/composables/opmlPreviewGeneration.ts';

function deferred() {
    let resolve;
    const promise = new Promise((done) => {
        resolve = done;
    });

    return { promise, resolve };
}

test('discards a late preview when the selected file changes and uses only the matching result', async () => {
    const guard = createPreviewGeneration();
    const fileA = { name: 'a.opml' };
    const fileB = { name: 'b.xml' };
    let displayed = null;

    guard.select(fileA);
    const generationA = guard.begin(fileA);
    const requestA = deferred();
    const applyA = requestA.promise.then((result) => {
        if (guard.isCurrent(fileA, generationA)) displayed = result;
    });

    guard.select(fileB);
    const generationB = guard.begin(fileB);
    const requestB = deferred();
    const applyB = requestB.promise.then((result) => {
        if (guard.isCurrent(fileB, generationB)) displayed = result;
    });

    requestA.resolve({ source: 'a', feed_count: 1 });
    await applyA;
    assert.equal(displayed, null);

    requestB.resolve({ source: 'b', feed_count: 2 });
    await applyB;
    assert.deepEqual(displayed, { source: 'b', feed_count: 2 });
    assert.equal(guard.isCurrent(fileA, generationA), false);
    assert.equal(guard.isCurrent(fileB, generationB), true);
});

test('the import page requires a preview associated with the selected File before importing', async () => {
    const page = await readFile(new URL('../../resources/js/pages/settings/ImportExport.vue', import.meta.url), 'utf8');

    assert.match(page, /previewGeneration\.isCurrent\(requestedFile, generation\)/);
    assert.match(page, /previewedFile\.value !== file\.value/);
    assert.match(page, /summary && previewedFile === file/);
});
