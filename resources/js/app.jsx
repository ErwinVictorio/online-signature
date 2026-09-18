import './bootstrap';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { Toaster } from '@/components/ui/sonner';

const appName = import.meta.env.VITE_APP_NAME || 'Pdf Signature Automation';

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.jsx', { eager: false });
        const page = pages[`./pages/${name}.jsx`];

        if (!page) {
            throw new Error(`Inertia page not found: ./pages/${name}.jsx`);
        }

        return page();
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <>
                <App {...props} />
                <Toaster position="top-right" richColors closeButton />
            </>
        );
    },
    progress: {
        color: '#6366F1',
    },
});
