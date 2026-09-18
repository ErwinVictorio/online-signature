import { Head } from '@inertiajs/react';

/**
 * The starter page for a project created without a dashboard: Inertia and React
 * are wired up, and nothing else is assumed.
 */
export default function Welcome() {
    return (
        <>
            <Head title="Welcome" />

            <main className="flex min-h-svh items-center justify-center p-6">
                <div className="w-full max-w-md space-y-3 text-center">
                    <h1 className="text-2xl font-semibold text-foreground">{'Pdf Signature Automation'}</h1>
                    <p className="text-sm text-muted-foreground">
                        Laravel, Inertia and React are ready. Start building in{' '}
                        <code className="rounded bg-muted px-1 py-0.5 text-xs">
                            resources/js/pages
                        </code>
                        .
                    </p>
                </div>
            </main>
        </>
    );
}
