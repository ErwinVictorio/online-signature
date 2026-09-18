import { getDocument, GlobalWorkerOptions } from 'pdfjs-dist';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
GlobalWorkerOptions.workerSrc = workerUrl;
export function loadPdf(url) {
    return getDocument({ url, withCredentials: true, isEvalSupported: false, cMapUrl: '/pdfjs/cmaps/', cMapPacked: true, standardFontDataUrl: '/pdfjs/standard_fonts/', wasmUrl: '/pdfjs/wasm/' });
}
export function pdfError(error) {
    if (error?.name === 'PasswordException') return 'This PDF is password protected. Upload an unlocked copy.';
    return 'Unable to open this PDF. It may be damaged or unsupported. Try exporting a new PDF from the source application.';
}
