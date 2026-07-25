import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';

interface StickySaveBarProps {
    processing: boolean;
    /** Where cancel goes back to. */
    cancelHref: string;
    saveLabel?: string;
    cancelLabel?: string;
}

/**
 * Sticks to the bottom of the viewport so save is always in thumb reach on a
 * phone, and sits inline on a desktop.
 */
export function StickySaveBar({ processing, cancelHref, saveLabel = 'সংরক্ষণ করুন', cancelLabel = 'বাতিল' }: StickySaveBarProps) {
    return (
        <div className="bg-background/95 sticky bottom-0 -mx-4 mt-2 flex gap-3 border-t p-4 backdrop-blur sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none">
            <Button type="submit" className="h-12 flex-1 text-base sm:flex-none sm:px-8" disabled={processing}>
                {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                {saveLabel}
            </Button>
            <Button type="button" variant="outline" className="h-12 text-base" asChild>
                <Link href={cancelHref}>{cancelLabel}</Link>
            </Button>
        </div>
    );
}
