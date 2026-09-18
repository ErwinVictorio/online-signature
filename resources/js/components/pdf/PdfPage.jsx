import { useEffect, useRef, useState } from 'react';
export default function PdfPage({ pdf, pageNumber, scale = 1, thumbnail = false, onViewport, children }) {
    const canvasRef = useRef(null);
    const viewportCallback = useRef(onViewport);
    viewportCallback.current = onViewport;
    const [viewport, setViewport] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    useEffect(() => {
        let cancelled = false;
        let renderTask;
        setLoading(true); setError(''); setViewport(null);
        async function render() {
            try {
                const page = await pdf.getPage(pageNumber);
                if (cancelled) return;
                const base = page.getViewport({ scale: 1 });
                const view = page.getViewport({ scale: thumbnail ? 100 / base.width : scale });
                const canvas = canvasRef.current;
                const ratio = thumbnail ? 1 : Math.min(window.devicePixelRatio || 1, 2);
                canvas.width = Math.ceil(view.width * ratio); canvas.height = Math.ceil(view.height * ratio);
                canvas.style.width = `${view.width}px`; canvas.style.height = `${view.height}px`;
                setViewport(view); viewportCallback.current?.(view);
                renderTask = page.render({ canvasContext: canvas.getContext('2d'), viewport: view, transform: ratio === 1 ? null : [ratio, 0, 0, ratio, 0, 0] });
                await renderTask.promise;
                if (!cancelled) setLoading(false);
            } catch (e) {
                if (!cancelled && e.name !== 'RenderingCancelledException') { setError('Unable to render this page.'); setLoading(false); }
            }
        }
        render();
        return () => { cancelled = true; renderTask?.cancel(); };
    }, [pdf, pageNumber, scale, thumbnail]);
    return <div className="relative shrink-0 bg-white shadow-md" style={{ width: viewport?.width, height: viewport?.height }}>
        <canvas ref={canvasRef} aria-label={`PDF page ${pageNumber}`} />
        {loading && <div className="absolute inset-0 grid place-items-center bg-white/80 text-xs text-slate-500">Rendering...</div>}
        {error && <p role="alert" className="absolute inset-0 bg-red-50 p-4 text-sm text-red-700">{error}</p>}
        {!loading && !error && viewport && children?.(viewport)}
    </div>;
}
