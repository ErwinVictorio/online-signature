import { PDFDocument, degrees } from 'pdf-lib';
import { pdfRectangle } from './placements.js';

async function fetchBytes(url) {
    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/pdf,image/png,image/jpeg' } });
    if (!response.ok || response.redirected) throw new Error('Unable to load a private file. Your session may have expired.');
    return new Uint8Array(await response.arrayBuffer());
}

export async function generateSignedPdf({ originalUrl, pdf, elements, signatures, textImage }) {
    if (!elements.length) throw new Error('Add a signature or text element first.');
    const output = await PDFDocument.load(await fetchBytes(originalUrl));
    const embedded = new Map();
    for (const element of elements) {
        const page = output.getPage(element.page_number - 1);
        const sourcePage = await pdf.getPage(element.page_number);
        const viewport = sourcePage.getViewport({ scale: 1 });
        const rectangle = pdfRectangle(element, viewport);
        let image;
        if (element.type === 'signature') {
            const signature = signatures.find(s => s.id === element.signature_id);
            if (!signature) throw new Error('A saved signature was deleted. Remove or replace its placement.');
            if (!embedded.has(signature.id)) {
                const bytes = await fetchBytes(signature.image_url);
                embedded.set(signature.id, signature.mime_type === 'image/jpeg' ? await output.embedJpg(bytes) : await output.embedPng(bytes));
            }
            image = embedded.get(signature.id);
        } else {
            image = await output.embedPng(textImage(element));
        }
        page.drawImage(image, { x: rectangle.x, y: rectangle.y, width: rectangle.width, height: rectangle.height, rotate: degrees(rectangle.angle) });
    }
    return new Blob([await output.save()], { type: 'application/pdf' });
}
