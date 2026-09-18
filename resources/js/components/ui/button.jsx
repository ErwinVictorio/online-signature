import { cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';
const variants = cva('inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:pointer-events-none disabled:opacity-50', {
    variants: { variant: { default: 'bg-indigo-600 text-white hover:bg-indigo-700', outline: 'border bg-white text-slate-700 hover:bg-slate-100', destructive: 'bg-red-50 text-red-700 hover:bg-red-100', ghost: 'text-slate-600 hover:bg-slate-100' } },
    defaultVariants: { variant: 'default' },
});
export function Button({ className, variant, type = 'button', ...props }) {
    return <button type={type} className={cn(variants({ variant }), className)} {...props} />;
}
