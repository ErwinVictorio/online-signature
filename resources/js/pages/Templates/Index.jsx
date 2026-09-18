import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/Pagination';
import { toast } from 'sonner';
export default function Index({ templates }) {
    function rename(template) {
        const name = prompt('Template name', template.name);
        if (name?.trim()) router.patch(`/templates/${template.id}`, { name: name.trim() }, { onError: errors => toast.error(Object.values(errors).join(' ')) });
    }
    return <AppLayout title="Placement templates"><p className="mb-5 text-sm text-slate-500">Save placements as a template from the document editor, then apply them to another PDF. Templates retain their text and dates; review these before signing.</p><section className="panel">{templates.data.map(template => <article className="flex flex-wrap items-center justify-between gap-4 border-b py-4 last:border-0" key={template.id}><div><h2 className="font-medium">{template.name}</h2><p className="text-sm text-slate-500">{template.placements.length} elements · Created for {template.page_count} pages</p></div><div className="flex gap-2"><Button variant="outline" onClick={() => rename(template)}>Rename</Button><Button variant="destructive" onClick={() => { if (confirm('Delete this template?')) router.delete(`/templates/${template.id}`); }}>Delete</Button></div></article>)}{!templates.data.length && <p className="py-6 text-center text-slate-500">No templates yet. Create one from a document's editor.</p>}<Pagination data={templates} /></section></AppLayout>;
}
