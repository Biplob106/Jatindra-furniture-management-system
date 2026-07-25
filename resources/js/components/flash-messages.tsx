import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { CheckCircle2, XCircle } from 'lucide-react';

/**
 * Renders the success and error messages the controllers flash on redirect.
 *
 * Neither fades on a timer. The person saving a day's attendance may be
 * holding a phone in one hand and a pencil in the other, and a message that
 * vanishes after four seconds is a message they never read. Both clear on the
 * next navigation, which is when the flash prop empties anyway.
 */
export function FlashMessages() {
    const { flash } = usePage<SharedData>().props;

    if (flash?.error) {
        return (
            <div role="alert" className="border-destructive/40 bg-destructive/10 text-destructive flex items-start gap-3 rounded-lg border p-4">
                <XCircle className="mt-0.5 h-5 w-5 shrink-0" />
                <p className="text-sm">{flash.error}</p>
            </div>
        );
    }

    if (flash?.success) {
        return (
            <div
                role="status"
                className="flex items-start gap-3 rounded-lg border border-green-600/40 bg-green-600/10 p-4 text-green-700 dark:text-green-400"
            >
                <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0" />
                <p className="text-sm">{flash.success}</p>
            </div>
        );
    }

    return null;
}
