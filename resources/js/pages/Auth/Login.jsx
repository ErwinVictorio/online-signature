import { Head, useForm } from '@inertiajs/react';
import { PenLine } from 'lucide-react';
import { Button } from '@/components/ui/button';
export default function Login() {
    const { data, setData, post, processing, errors, reset } = useForm({ email: '', password: '', remember: false });
    return <div className="grid min-h-screen place-items-center bg-slate-50 p-6"><Head title="Sign in" /><form className="w-full max-w-md rounded-2xl border bg-white p-8 shadow-sm" onSubmit={e => { e.preventDefault(); post('/login', { onFinish: () => reset('password') }); }}>
        <PenLine className="mb-5 text-indigo-600" size={32} /><h1 className="text-2xl font-bold">Welcome to Paperless Sign</h1><p className="mb-7 mt-2 text-sm text-slate-500">Sign your PDFs and keep everything in one place.</p>
        <label className="field">Username or email<input type="text" autoComplete="username" required value={data.email} onChange={e => setData('email', e.target.value)} autoFocus /></label>
        <label className="field">Password<input type="password" autoComplete="current-password" required value={data.password} onChange={e => setData('password', e.target.value)} /></label>
        <label className="my-4 flex items-center gap-2 text-sm"><input type="checkbox" checked={data.remember} onChange={e => setData('remember', e.target.checked)} /> Keep me signed in</label>
        {Object.values(errors).map(error => <p role="alert" key={error} className="mb-3 text-sm text-red-600">{error}</p>)}<Button type="submit" disabled={processing} className="w-full">{processing ? 'Signing in...' : 'Sign in'}</Button><p className="mt-5 text-xs text-slate-500">Ask your administrator for an account.</p>
    </form></div>;
}
