import AppLayout from '@/layouts/AppLayout';
import Pagination from '@/components/Pagination';
export default function Index({ logs }) {
    return <AppLayout title="Activity history"><div className="panel"><ul>{logs.data.map(log => <li className="flex flex-wrap justify-between gap-3 border-b py-4 last:border-0" key={log.id}><div><p className="text-sm font-medium capitalize">{log.action.replaceAll('_', ' ')}</p>{log.document_name && <p className="text-sm text-slate-500">{log.document_name}</p>}</div><time className="text-xs text-slate-500">{new Date(log.created_at).toLocaleString()}</time></li>)}</ul>{!logs.data.length && <p className="py-6 text-center text-slate-500">Your document and signature activity will appear here.</p>}<Pagination data={logs} /></div></AppLayout>;
}
