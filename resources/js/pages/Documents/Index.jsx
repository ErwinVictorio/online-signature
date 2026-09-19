import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/Pagination';
export default function Index({ documents, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    return <AppLayout title="Documents" actions={<Link className="primary-link" href="/documents/create">Upload document</Link>}>
        <form className="mb-5 flex flex-wrap gap-3" onSubmit={e => { e.preventDefault(); router.get('/documents', { search, status }); }}><input aria-label="Search documents" className="rounded-lg border bg-white px-3 py-2" placeholder="Search documents..." value={search} onChange={e => setSearch(e.target.value)} /><select aria-label="Document status" className="rounded-lg border bg-white px-3 py-2" value={status} onChange={e => setStatus(e.target.value)}><option value="">All statuses</option>{['uploaded', 'editing', 'signed'].map(s => <option key={s}>{s}</option>)}</select><Button variant="outline" type="submit">Filter</Button></form>
        <div className="panel overflow-x-auto"><table className="w-full text-left text-sm"><thead className="text-slate-500"><tr><th className="pb-3">Document</th><th>Status</th><th>Uploaded</th><th>Actions</th></tr></thead><tbody>{documents.data.map(doc => <tr key={doc.id} className="border-t"><td className="py-4"><Link className="font-medium text-indigo-700" href={`/documents/${doc.id}`}>{doc.original_name}</Link><p className="mt-1 text-xs text-slate-500">{doc.page_count ?? 'Pending'} pages · {(doc.file_size / 1024 / 1024).toFixed(2)} MB</p></td><td className="capitalize">{doc.source_format !== 'pdf' && !doc.editor_ready ? doc.conversion_status.replace('failed', 'Conversion failed') : doc.status}</td><td>{new Date(doc.created_at).toLocaleDateString()}</td><td><div className="flex items-center gap-3">{doc.editor_ready && <Link href={`/documents/${doc.id}/edit`}>Open editor</Link>}{doc.signed_at && <a href={`/documents/${doc.id}/download`}>Download</a>}<Button variant="destructive" onClick={() => { if (confirm('Delete this document and its stored copies?')) router.delete(`/documents/${doc.id}`); }}>Delete</Button></div></td></tr>)}</tbody></table>{!documents.data.length && <p className="py-10 text-center text-slate-500">No documents found.</p>}<Pagination data={documents} /></div>
    </AppLayout>;
}
