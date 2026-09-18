import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { FileText, LayoutDashboard, PenLine, Layers, History, LogOut } from 'lucide-react';
import { Button } from '@/components/ui/button';
const navigation = [['Dashboard', '/dashboard', LayoutDashboard], ['Documents', '/documents', FileText], ['Signatures', '/signatures', PenLine], ['Templates', '/templates', Layers], ['Activity', '/activity', History]];
export default function AppLayout({ title, children, actions }) {
    const { auth, flash } = usePage().props;
    const { url } = usePage();
    useEffect(() => { if (flash?.success) toast.success(flash.success); if (flash?.error) toast.error(flash.error); }, [flash]);
    return <div className="min-h-screen bg-slate-50 text-slate-900"><Head title={title} />
        <aside className="border-r bg-white p-5 lg:fixed lg:inset-y-0 lg:w-60">
            <Link href="/dashboard" className="mb-8 flex items-center gap-3 text-lg font-bold"><PenLine className="text-indigo-600" /> Paperless Sign</Link>
            <nav className="flex flex-wrap gap-2 lg:flex-col">{navigation.map(([label, href, Icon]) => <Link key={href} href={href} className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm ${url.startsWith(href) ? 'bg-indigo-50 font-semibold text-indigo-700' : 'text-slate-600 hover:bg-slate-50'}`}><Icon size={18} />{label}</Link>)}</nav>
            <div className="mt-8 border-t pt-4 lg:absolute lg:bottom-6 lg:left-5 lg:right-5"><p className="truncate text-sm font-medium">{auth.user.name}</p><p className="mb-3 truncate text-xs text-slate-500">{auth.user.email}</p><Button variant="ghost" onClick={() => router.post('/logout')}><LogOut size={16} /> Sign out</Button></div>
        </aside><main className="p-4 md:p-8 lg:ml-60"><header className="mb-7 flex flex-wrap items-center justify-between gap-4"><div><p className="mb-1 text-xs font-semibold uppercase tracking-widest text-indigo-600">PDF Signature Tool</p><h1 className="text-2xl font-bold">{title}</h1></div>{actions}</header>{children}</main>
    </div>;
}
