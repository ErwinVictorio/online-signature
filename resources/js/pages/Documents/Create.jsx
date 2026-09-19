import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import FormErrors from '@/components/FormErrors';
import { UploadCloud } from 'lucide-react';
export default function Create() {
    const form = useForm({ file: null });
    function choose(file) {
        form.clearErrors();
        if (file && (!/\.(pdf|docx|doc)$/i.test(file.name) || file.size > 20 * 1024 * 1024)) { form.setData('file', null); form.setError('file', 'Choose a PDF, DOCX, or DOC no larger than 20 MB.'); return; }
        form.setData('file', file);
    }
    return <AppLayout title="Upload document"><form className="panel max-w-2xl" onSubmit={e => { e.preventDefault(); form.post('/documents'); }}>
        <label className="flex cursor-pointer flex-col items-center gap-4 rounded-xl border-2 border-dashed border-indigo-200 bg-indigo-50/40 px-6 py-16 text-center" onDragOver={e => e.preventDefault()} onDrop={e => { e.preventDefault(); if (!form.processing) choose(e.dataTransfer.files[0]); }}><UploadCloud size={40} className="text-indigo-600" /><span className="font-semibold">Drop a document here or browse files</span><span className="text-sm text-slate-500">PDF, DOCX, DOC · Up to 20 MB and 500 pages</span><input aria-label="Document file" type="file" accept=".pdf,.docx,.doc,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" disabled={form.processing} onChange={e => choose(e.target.files[0])} className="max-w-full text-sm" /></label>
        <p className="mt-4 text-sm text-slate-500">Word files are converted to PDF. Review fonts, pagination, and placement positions before signing. The original stays unchanged; signed copies are PDFs. For Google Docs, download as DOCX first.</p>{form.data.file && <p className="mt-4 text-sm">Selected: {form.data.file.name}</p>}<FormErrors errors={form.errors} />{form.progress && <progress className="my-4 w-full" value={form.progress.percentage} max="100" />}<Button type="submit" className="mt-5" disabled={!form.data.file || form.processing}>{form.processing ? 'Uploading document...' : 'Upload document'}</Button>
    </form></AppLayout>;
}
