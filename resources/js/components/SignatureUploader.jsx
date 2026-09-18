import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import FormErrors from '@/components/FormErrors';
export default function SignatureUploader() {
    const form = useForm({ name: '', image: null, is_default: false });
    return <form onSubmit={e => { e.preventDefault(); const target = e.currentTarget; form.post('/signatures', { preserveScroll: true, onSuccess: () => { form.reset(); target.reset(); } }); }}>
        <label className="field">Signature name<input required maxLength={100} value={form.data.name} onChange={e => form.setData('name', e.target.value)} placeholder="My signature" /></label><label className="field">Signature image<input required type="file" accept="image/png,image/jpeg" onChange={e => form.setData('image', e.target.files[0])} /></label><p className="mb-4 text-xs text-slate-500">PNG or JPG, up to 2 MB; 10–4000 pixels per side. Transparent PNG works best.</p><label className="mb-4 flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.is_default} onChange={e => form.setData('is_default', e.target.checked)} /> Use as default</label><FormErrors errors={form.errors} /><Button type="submit" disabled={form.processing}>{form.processing ? 'Saving...' : 'Save signature'}</Button>
    </form>;
}
