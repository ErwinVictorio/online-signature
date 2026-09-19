import { Link, router } from '@inertiajs/react';
import { useEffect } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';

export default function Show({ document: doc, versions = [] }) {
    const word = doc.source_format !== 'pdf';
    const pending = ['queued', 'converting'].includes(doc.conversion_status);
    const interrupted = doc.conversion_status === 'converting' && doc.conversion_started_at && Date.now() - Date.parse(doc.conversion_started_at) > 300000;
    useEffect(() => {
        if (!pending) return;
        const timer = setInterval(() => router.reload({ only: ['document', 'versions'] }), 3000);
        return () => clearInterval(timer);
    }, [pending]);
    const labels = { queued: 'Queued', converting: 'Converting', ready: 'Ready', failed: 'Conversion failed' };
    return <AppLayout title={doc.original_name} actions={doc.editor_ready && <Link className="primary-link" href={`/documents/${doc.id}/edit`}>Open editor</Link>}>
        <section className="panel">
            <dl className="grid gap-5 sm:grid-cols-3"><div><dt className="text-sm text-slate-500">Status</dt><dd className="capitalize">{doc.status}</dd></div><div><dt className="text-sm text-slate-500">Pages</dt><dd>{doc.page_count ?? 'Available after conversion'}</dd></div><div><dt className="text-sm text-slate-500">Signed</dt><dd>{doc.signed_at ? new Date(doc.signed_at).toLocaleString() : 'Not yet signed'}</dd></div></dl>
            {word && <div className="mt-5 rounded-lg bg-slate-50 p-4" role="status"><p className="font-semibold">{labels[doc.conversion_status]}</p>{pending && <p className="mt-2 text-sm">Your Word document is being prepared. This page updates automatically.</p>}{doc.conversion_error && <p className="mt-2 text-sm text-red-700">{doc.conversion_error}</p>}{(doc.conversion_status === 'failed' || interrupted) && <Button className="mt-3" variant="outline" onClick={() => router.post(`/documents/${doc.id}/conversion/retry`)}>Retry conversion</Button>}{doc.editor_ready && <p className="mt-2 text-sm">Open the editor and review the converted PDF's layout, fonts, and every page before signing.</p>}</div>}
            <div className="mt-6 flex flex-wrap gap-4"><a className="primary-link" href={`/documents/${doc.id}/download?version=original`}>Download original</a>{word && doc.editor_ready && <a className="primary-link" href={`/documents/${doc.id}/download?version=converted`}>Download converted PDF</a>}{doc.signed_at && <a className="primary-link" href={`/documents/${doc.id}/download`}>Download signed PDF</a>}</div>
            <p className="mt-5 text-sm text-slate-500">The original document is preserved when you sign.</p>
            {versions.length > 0 && <div className="mt-6 border-t pt-4"><h2 className="mb-3 font-semibold">Signed versions</h2>{versions.map(version => <a className="flex justify-between py-2 text-sm text-indigo-700" key={version.version} href={`/documents/${doc.id}/download?version=${version.version}`}><span>Signed version {version.version}</span><span>{new Date(version.created_at).toLocaleString()}</span></a>)}</div>}
        </section>
    </AppLayout>;
}
