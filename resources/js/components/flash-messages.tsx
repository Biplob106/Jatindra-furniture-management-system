import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { CheckCircle2, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * Renders the success and error messages the controllers flash on redirect.
 * Errors stay until dismissed; a blocked delete explains itself and the person
 * at the counter needs time to read it.
 */
export function FlashMessages() {
    const { flash } = usePage<SharedData>().props;
    const [dismissed, setDismissed] = useState<string | null>(null);

    const success = flash?.success;
    const error = flash?.error;

    useEffect(() => {
        if (!success) return;

        const timer = setTimeout(() => setDismissed(success), 4000);

        return () => clearTimeout(timer);
    }, [success]);

    if (error) {
        return (
            <div role="alert" className="border-destructive/40 bg-destructive/10 text-destructive flex items-start gap-3 rounded-lg border p-4">
                <XCircle className="mt-0.5 h-5 w-5 shrink-0" />
                <p className="text-sm">{error}</p>
            </div>
        );
    }

    if (success && dismissed !== success) {
        return (
            <div role="status" className="flex items-start gap-3 rounded-lg border border-green-600/40 bg-green-600/10 p-4 text-green-700 dark:text-green-400">
                <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0" />
                <p className="text-sm">{success}</p>
            </div>
        );
    }

    return null;
}
