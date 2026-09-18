import { Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useMemo, useReducer, useRef, useState } from 'react';
import { toast } from 'sonner';
import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import PdfPage from './PdfPage';
import PageThumbnail from './PageThumbnail';
import SignatureOverlay from './SignatureOverlay';
import SignatureUploader from '@/components/SignatureUploader';
import { loadPdf, pdfError } from '@/lib/pdf';
import { clampPlacement } from '@/lib/placements';
import { historyReducer, initialHistory } from '@/lib/history';
import { textImage } from '@/lib/textImage';

function errorMessage(error) {
    if (error.response?.status === 409) return 'This document changed in another tab. Reload before saving; your current changes have not been saved.';
    if ([401, 419].includes(error.response?.status)) return 'Your session expired. Sign in again before saving.';
    const errors = error.response?.data?.errors;
    return errors ? Object.values(errors).flat().join(' ') : error.response?.data?.message || error.message || 'Unable to save. Check your connection and try again.';
}

export default function EditorWorkspace({ document: doc, signatures, templates = [] }) {
    const { auth } = usePage().props;
    const [pdf, setPdf] = useState(null), [error, setError] = useState('');
    const [page, setPage] = useState(1), [scale, setScale] = useState(1), [viewport, setViewport] = useState(null);
    const [history, dispatch] = useReducer(historyReducer, doc.placements || [], initialHistory);
    const elements = history.present;
    const [selected, setSelected] = useState(null), [revision, setRevision] = useState(doc.revision);
    const [busy, setBusy] = useState(''), [saved, setSaved] = useState(JSON.stringify(doc.placements || []));
    const [preview, setPreview] = useState(null), [signed, setSigned] = useState(!!doc.signed_at);
    const [uploadOpen, setUploadOpen] = useState(false), [templateName, setTemplateName] = useState('');
    const [templateId, setTemplateId] = useState(''), [text, setText] = useState(auth.user.name);
    const [date, setDate] = useState(() => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; });
    const [dateFormat, setDateFormat] = useState('long');
    const previewRef = useRef(null);
    const dirty = saved !== JSON.stringify(elements);
    const active = elements.find(e => e.id === selected);
    const textSources = useMemo(() => new Map(elements.filter(e => e.type !== 'signature').map(e => [e.id, textImage(e)])), [elements]);
    const setElements = value => dispatch({ type: 'set', value });

    useEffect(() => {
        let cancelled = false;
        const task = loadPdf(`/documents/${doc.id}/file`);
        task.promise.then(value => { if (!cancelled) setPdf(value); }).catch(e => { if (!cancelled) setError(pdfError(e)); });
        return () => { cancelled = true; task.destroy(); };
    }, [doc.id]);
    useEffect(() => () => { if (previewRef.current) URL.revokeObjectURL(previewRef.current); }, []);
    useEffect(() => {
        const warn = e => { if (dirty || busy) { e.preventDefault(); e.returnValue = ''; } };
        const unsubscribe = router.on('before', () => { if (busy) return false; if (dirty && !confirm('Leave the editor without saving your changes?')) return false; });
        window.addEventListener('beforeunload', warn);
        return () => { window.removeEventListener('beforeunload', warn); unsubscribe(); };
    }, [dirty, busy]);
    useEffect(() => {
        const keyboard = e => {
            if (busy || preview || /INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.target.isContentEditable) return;
            if ((e.ctrlKey || e.metaKey) && ['z', 'y'].includes(e.key.toLowerCase())) {
                e.preventDefault(); dispatch({ type: e.key.toLowerCase() === 'y' || e.shiftKey ? 'redo' : 'undo' });
            } else if (['Delete', 'Backspace'].includes(e.key) && selected) {
                e.preventDefault(); dispatch({ type: 'set', value: current => current.filter(item => item.id !== selected) }); setSelected(null);
            }
        };
        window.addEventListener('keydown', keyboard);
        return () => window.removeEventListener('keydown', keyboard);
    }, [selected, busy, preview]);

    function changePage(next) { if (next !== page) setViewport(null); setSelected(null); setPage(next); }
    function add(type, signature = null) {
        if (!viewport || busy) return;
        if (elements.length >= 200) { toast.error('A document can contain up to 200 elements.'); return; }
        let value = text.trim();
        if (type === 'date') {
            const parsed = new Date(`${date}T12:00:00`);
            if (Number.isNaN(parsed.getTime())) { toast.error('Choose a valid date.'); return; }
            value = dateFormat === 'iso' ? date : parsed.toLocaleDateString('en-US', dateFormat === 'long' ? { month: 'long', day: 'numeric', year: 'numeric' } : { month: '2-digit', day: '2-digit', year: 'numeric' });
        }
        if (type === 'initials') value = value.split(/\s+/).map(part => [...part][0] || '').join('').slice(0, 20);
        if (type !== 'signature' && !value) { toast.error('Enter a name or text first.'); return; }
        const width = type === 'initials' ? 0.1 : 0.28;
        const element = clampPlacement({ id: crypto.randomUUID(), type, signature_id: signature?.id || null, text: type === 'signature' ? null : value, page_number: page, x_ratio: 0.1, y_ratio: 0.1, width_ratio: width, height_ratio: width * viewport.width / viewport.height * (signature ? signature.height / signature.width : 0.15) });
        setElements([...elements, element]); setSelected(element.id);
    }
    function change(next) { setElements(elements.map(e => e.id === next.id ? next : e)); }
    function remove() { setElements(elements.filter(e => e.id !== selected)); setSelected(null); }
    function duplicate() {
        if (!active || elements.length >= 200) return;
        const next = clampPlacement({ ...active, id: crypto.randomUUID(), x_ratio: active.x_ratio + 0.025, y_ratio: active.y_ratio + 0.025 });
        setElements([...elements, next]); setSelected(next.id);
    }
    async function saveDraft() {
        setBusy('Saving draft...');
        try { const { data } = await axios.put(`/documents/${doc.id}/placements`, { placements: elements, revision }); setRevision(data.revision); setSaved(JSON.stringify(elements)); toast.success('Draft saved.'); }
        catch (e) { toast.error(errorMessage(e)); }
        finally { setBusy(''); }
    }
    async function generate() {
        setBusy('Generating signed PDF...');
        try {
            const { generateSignedPdf } = await import('@/lib/exportPdf');
            const blob = await generateSignedPdf({ originalUrl: `/documents/${doc.id}/file`, pdf, elements, signatures, textImage });
            if (blob.size > 40 * 1024 * 1024) throw new Error('The signed PDF exceeds 40 MB. Use smaller signature images or a smaller source PDF.');
            if (previewRef.current) URL.revokeObjectURL(previewRef.current);
            previewRef.current = URL.createObjectURL(blob);
            setPreview({ blob, url: previewRef.current });
        } catch (e) { toast.error(errorMessage(e)); }
        finally { setBusy(''); }
    }
    function closePreview() { if (busy) return; setPreview(null); if (previewRef.current) URL.revokeObjectURL(previewRef.current); previewRef.current = null; }
    async function saveSigned() {
        setBusy('Saving signed document...');
        try {
            const form = new FormData(); form.append('file', preview.blob, 'signed.pdf'); form.append('placements', JSON.stringify(elements)); form.append('revision', revision);
            const { data } = await axios.post(`/documents/${doc.id}/sign`, form);
            setRevision(data.revision); setSaved(JSON.stringify(elements)); setSigned(true); setPreview(null);
            if (previewRef.current) URL.revokeObjectURL(previewRef.current); previewRef.current = null;
            toast.success('Signed PDF saved. Your original is unchanged.');
            window.location.assign(data.download_url);
        } catch (e) { toast.error(errorMessage(e)); }
        finally { setBusy(''); }
    }
    async function saveTemplate() {
        setBusy('Saving template...');
        try {
            await axios.post('/templates', { name: templateName, page_count: pdf.numPages, placements: elements });
            setTemplateName(''); toast.success('Template saved. It is available in Templates and when you reopen the editor.');
        } catch (e) { toast.error(errorMessage(e)); }
        finally { setBusy(''); }
    }
    function applyTemplate() {
        const template = templates.find(t => String(t.id) === templateId);
        if (!template) return;
        if (template.placements.some(e => e.page_number > pdf.numPages)) { toast.error('This template uses pages that this document does not have.'); return; }
        if (template.placements.some(e => e.type === 'signature' && !signatures.some(s => s.id === e.signature_id))) { toast.error('A signature used by this template was deleted. Update the template with an available signature.'); return; }
        if (elements.length && !confirm('Replace the current placements with this template? You can undo this action.')) return;
        setElements(template.placements.map(e => ({ ...e, id: crypto.randomUUID() }))); changePage(1);
        toast.success('Template applied. Check every page and date before signing.');
    }

    return <AppLayout title={doc.original_name} actions={<div className="flex items-center gap-4"><span className="text-xs text-slate-500">{dirty ? 'Unsaved changes' : 'All changes saved'}</span><Link href={`/documents/${doc.id}`}>Document details</Link></div>}>
        <div className="panel mb-4 flex flex-wrap items-center gap-2">
            <Button variant="outline" disabled={!!busy || page <= 1} onClick={() => changePage(page - 1)}>Previous</Button><label className="text-sm">Page <select aria-label="Page" value={page} disabled={!!busy || !pdf} onChange={e => changePage(Number(e.target.value))}>{Array.from({ length: pdf?.numPages || doc.page_count }, (_, i) => <option key={i} value={i + 1}>{i + 1}</option>)}</select> / {pdf?.numPages || doc.page_count}</label><Button variant="outline" disabled={!!busy || !pdf || page >= pdf.numPages} onClick={() => changePage(page + 1)}>Next</Button>
            <Button aria-label="Zoom out" variant="outline" disabled={!!busy || scale <= 0.25} onClick={() => { setViewport(null); setScale(Math.max(0.25, scale - 0.25)); }}>−</Button><span className="text-sm">{Math.round(scale * 100)}%</span><Button aria-label="Zoom in" variant="outline" disabled={!!busy || scale >= 2} onClick={() => { setViewport(null); setScale(Math.min(2, scale + 0.25)); }}>+</Button>
            <Button variant="ghost" disabled={!!busy || !history.past.length} onClick={() => dispatch({ type: 'undo' })}>Undo</Button><Button variant="ghost" disabled={!!busy || !history.future.length} onClick={() => dispatch({ type: 'redo' })}>Redo</Button>
            <Button variant="outline" disabled={!!busy || !pdf} onClick={saveDraft}>Save draft</Button><Button disabled={!!busy || !pdf || !elements.length} onClick={generate}>Preview signed PDF</Button>{signed && <a className="text-sm text-indigo-700" href={`/documents/${doc.id}/download`}>Download last signed copy</a>}
        </div>
        {busy && <p role="status" className="mb-4 text-sm text-indigo-700">{busy}</p>}{error && <p role="alert" className="panel text-red-700">{error}</p>}{!pdf && !error && <p className="panel">Loading document...</p>}
        {pdf && <div className="grid items-start gap-4 xl:grid-cols-[120px_minmax(0,1fr)_260px]">
            <aside className="flex max-h-[75vh] gap-4 overflow-auto p-2 xl:flex-col">{Array.from({ length: pdf.numPages }, (_, i) => <PageThumbnail key={i} pdf={pdf} pageNumber={i + 1} selected={page === i + 1} onClick={() => !busy && changePage(i + 1)} />)}</aside>
            <div className="max-h-[80vh] min-h-[60vh] overflow-auto rounded-xl bg-slate-200 p-5"><PdfPage key={`${page}-${scale}`} pdf={pdf} pageNumber={page} scale={scale} onViewport={setViewport}>{view => elements.filter(e => e.page_number === page).map(element => <SignatureOverlay key={element.id} element={element} viewport={view} selected={selected === element.id} disabled={!!busy || !!preview} onSelect={() => setSelected(element.id)} src={element.type === 'signature' ? signatures.find(s => s.id === element.signature_id)?.image_url : textSources.get(element.id)} onChange={change} />)}</PdfPage></div>
            <aside className="space-y-4"><section className="panel"><h2 className="mb-3 font-semibold">Add signature</h2><div className="max-h-56 overflow-auto">{signatures.map(signature => <button disabled={!!busy || !viewport} key={signature.id} className="mb-2 w-full rounded-lg border p-2 hover:border-indigo-500 disabled:opacity-50" onClick={() => add('signature', signature)}><img src={signature.image_url} alt={signature.name} className="mb-1 h-12 w-full object-contain" /><span className="text-xs">{signature.name}{signature.is_default ? ' · Default' : ''}</span></button>)}</div><Button variant="ghost" disabled={!!busy} onClick={() => setUploadOpen(!uploadOpen)}>Upload signature</Button>{uploadOpen && <SignatureUploader />}<p className="mt-3 text-xs text-slate-500">Drag to position. Select and drag a corner to resize. Arrow keys move by one pixel; Shift moves by ten.</p></section>
            <section className="panel"><h2 className="mb-3 font-semibold">Text and date</h2><label className="field">Name or text<input maxLength={200} value={text} onChange={e => setText(e.target.value)} /></label><div className="mb-4 flex gap-2"><Button variant="outline" disabled={!!busy || !viewport} onClick={() => add('name')}>Name</Button><Button variant="outline" disabled={!!busy || !viewport} onClick={() => add('initials')}>Initials</Button></div><label className="field">Date<input type="date" value={date} onChange={e => setDate(e.target.value)} /></label><label className="field">Date format<select value={dateFormat} onChange={e => setDateFormat(e.target.value)}><option value="long">September 18, 2026</option><option value="short">09/18/2026</option><option value="iso">2026-09-18</option></select></label><Button variant="outline" disabled={!!busy || !viewport} onClick={() => add('date')}>Add date</Button></section>
            <section className="panel"><h2 className="mb-3 font-semibold">Selected element</h2>{active ? <><p className="mb-3 text-xs capitalize">{active.type} · Page {active.page_number}</p>{active.type !== 'signature' && <label className="field">Text<input value={active.text || ''} maxLength={200} disabled={!!busy} onChange={e => change({ ...active, text: e.target.value })} /></label>}<div className="flex flex-wrap gap-2"><Button variant="outline" disabled={!!busy || elements.length >= 200} onClick={duplicate}>Duplicate</Button><Button variant="destructive" disabled={!!busy} onClick={remove}>Delete</Button></div></> : <p className="text-sm text-slate-500">Select an element on the page.</p>}<Button className="mt-3" variant="ghost" disabled={!!busy || !elements.length} onClick={() => { if (confirm('Remove all placements? You can undo this action.')) { setElements([]); setSelected(null); } }}>Reset document</Button></section>
            <section className="panel"><h2 className="mb-3 font-semibold">Placement templates</h2><label className="field">Saved template<select value={templateId} onChange={e => setTemplateId(e.target.value)}><option value="">Choose a template</option>{templates.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}</select></label><Button variant="outline" disabled={!!busy || !templateId} onClick={applyTemplate}>Apply template</Button><label className="field mt-4">Save current layout as<input maxLength={100} placeholder="Template name" value={templateName} onChange={e => setTemplateName(e.target.value)} /></label><Button variant="outline" disabled={!!busy || !elements.length || !templateName.trim()} onClick={saveTemplate}>Save template</Button></section></aside>
        </div>}
        {preview && <div role="dialog" aria-modal="true" aria-label="Preview signed PDF" className="fixed inset-0 z-50 flex flex-col bg-slate-950/80 p-4 md:p-8"><div className="flex flex-wrap items-center justify-between gap-3 rounded-t-xl bg-white p-4"><div><h2 className="font-semibold">Review signed PDF</h2><p className="text-xs text-slate-500">Check the positions on every page before saving.</p></div><div className="flex gap-2"><Button variant="outline" disabled={!!busy} onClick={closePreview}>Back to editor</Button><Button disabled={!!busy} onClick={saveSigned}>{busy || 'Save signed copy and download'}</Button></div></div><object data={preview.url} type="application/pdf" className="min-h-0 flex-1 bg-white"><a href={preview.url} target="_blank" rel="noreferrer" className="primary-link m-6">Open preview in another tab</a></object></div>}
    </AppLayout>;
}
