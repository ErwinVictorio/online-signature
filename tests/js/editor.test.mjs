import test from 'node:test';
import assert from 'node:assert/strict';
import { PDFDocument, degrees } from 'pdf-lib';
import { clampPlacement, pdfRectangle } from '../../resources/js/lib/placements.js';
import { historyReducer, initialHistory } from '../../resources/js/lib/history.js';
import { generateSignedPdf } from '../../resources/js/lib/exportPdf.js';

test('coordinates invert PDF.js transforms for all rotations, crop offsets and zoom', async () => {
    const { getDocument } = await import('pdfjs-dist/legacy/build/pdf.mjs');
    for (const rotation of [0, 90, 180, 270]) {
        const source = await PDFDocument.create();
        const page = source.addPage([600, 800]);
        page.setCropBox(20, 30, 500, 700); page.setRotation(degrees(rotation));
        const task = getDocument({ data: await source.save(), useSystemFonts: true });
        const pdf = await task.promise;
        const rendered = await pdf.getPage(1);
        let previous;
        for (const scale of [0.5, 1, 2]) {
            const viewport = rendered.getViewport({ scale });
            const element = { x_ratio: 0.2, y_ratio: 0.3, width_ratio: 0.25, height_ratio: 0.1 };
            const rect = pdfRectangle(element, viewport);
            const [screenX, screenY] = viewport.convertToViewportPoint(rect.x, rect.y);
            assert.ok(Math.abs(screenX - viewport.width * 0.2) < 0.001);
            assert.ok(Math.abs(screenY - viewport.height * 0.4) < 0.001);
            const angle = rect.angle * Math.PI / 180;
            const [rightX, rightY] = viewport.convertToViewportPoint(rect.x + Math.cos(angle) * rect.width, rect.y + Math.sin(angle) * rect.width);
            assert.ok(Math.abs(rightX - viewport.width * 0.45) < 0.001);
            assert.ok(Math.abs(rightY - viewport.height * 0.4) < 0.001);
            if (previous) assert.deepEqual(rect, previous);
            previous = rect;
        }
        await task.destroy();
    }
});

test('placements stay in page bounds', () => {
    const value = clampPlacement({ x_ratio: 0.95, y_ratio: -0.1, width_ratio: 0.3, height_ratio: 0.1 });
    assert.equal(value.x_ratio, 0.7); assert.equal(value.y_ratio, 0);
});

test('undo and redo preserve edits and discard abandoned futures', () => {
    let state = initialHistory([]);
    state = historyReducer(state, { type: 'set', value: [{ id: 1 }] });
    state = historyReducer(state, { type: 'set', value: [{ id: 1, x: 0.4 }] });
    state = historyReducer(state, { type: 'undo' }); assert.deepEqual(state.present, [{ id: 1 }]);
    state = historyReducer(state, { type: 'redo' }); assert.equal(state.present[0].x, 0.4);
    state = historyReducer(state, { type: 'undo' });
    state = historyReducer(state, { type: 'set', value: [] }); assert.equal(state.future.length, 0);
});

test('export embeds a signature into a real PDF while preserving source bytes', async () => {
    const { getDocument } = await import('pdfjs-dist/legacy/build/pdf.mjs');
    const source = await PDFDocument.create(); source.addPage([600, 800]); source.addPage([800, 600]);
    const original = await source.save(); const before = original.slice();
    const png = Uint8Array.from(Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=', 'base64'));
    const task = getDocument({ data: original.slice() });
    const pdf = await task.promise;
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async url => new Response(url === '/original' ? original : png, { status: 200 });
    try {
        const blob = await generateSignedPdf({ originalUrl: '/original', pdf, elements: [{ type: 'signature', signature_id: 1, page_number: 2, x_ratio: 0.25, y_ratio: 0.7, width_ratio: 0.2, height_ratio: 0.1 }], signatures: [{ id: 1, mime_type: 'image/png', image_url: '/signature' }] });
        const result = await PDFDocument.load(await blob.arrayBuffer());
        assert.equal(result.getPageCount(), 2);
        assert.ok(result.getPage(1).node.Resources().toString().includes('/Image'));
        assert.deepEqual(original, before);
    } finally { globalThis.fetch = originalFetch; await task.destroy(); }
});
