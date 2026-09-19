import { Link } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
export default function Dashboard({ stats = { total: 0, pending: 0, signed: 0, month: 0 }, recent = [] }) {
    return <AppLayout title="Dashboard" actions={<Link className="primary-link" href="/documents/create">Upload document</Link>}>
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{[['Total documents', stats.total], ['Pending documents', stats.pending], ['Signed documents', stats.signed], ['This month', stats.month]].map(([label, value]) => <section className="panel" key={label}><p className="text-sm text-slate-500">{label}</p><p className="mt-3 text-3xl font-semibold">{value}</p></section>)}</div>
        <section className="panel mt-6"><h2 className="mb-4 font-semibold">Recent documents</h2>{recent.length ? recent.map(doc => <Link className="flex justify-between border-t py-4 text-sm hover:text-indigo-600" href={`/documents/${doc.id}`} key={doc.id}><span>{doc.original_name}</span><span className="capitalize">{doc.status}</span></Link>) : <p className="text-sm text-slate-500">Upload your first document to start signing.</p>}</section>
        <p className="mt-6 text-xs text-slate-500">This tool places electronic signatures on PDFs. It does not create certificate-based digital signatures.</p>
    </AppLayout>;
}
