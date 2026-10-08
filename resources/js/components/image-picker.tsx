import { toBengaliDigits } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { compressImage } from '@/lib/compress-image';
import { cn } from '@/lib/utils';
import { ChevronLeft, ChevronRight, ImagePlus, LoaderCircle, X } from 'lucide-react';
import { ChangeEvent, useEffect, useMemo, useState } from 'react';

export interface OrderImage {
    id: number;
    name: string;
    url: string;
    thumb: string;
}

/** Object URLs for local previews, revoked when the files change or unmount. */
export function useObjectUrls(files: File[]): string[] {
    const urls = useMemo(() => files.map((file) => URL.createObjectURL(file)), [files]);

    useEffect(() => () => urls.forEach((url) => URL.revokeObjectURL(url)), [urls]);

    return urls;
}

interface ViewerProps {
    images: { url: string; name: string }[];
    index: number;
    onClose: () => void;
}

export function ImageViewer({ images, index: start, onClose }: ViewerProps) {
    const [index, setIndex] = useState(start);
    const image = images[index];
    const step = (delta: number) => setIndex((i) => (i + delta + images.length) % images.length);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
            if (e.key === 'ArrowRight') step(1);
            if (e.key === 'ArrowLeft') step(-1);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    });

    if (!image) return null;

    return (
        <div role="dialog" aria-modal="true" className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" onClick={onClose}>
            <button type="button" onClick={onClose} className="absolute top-4 right-4 rounded-full bg-white/10 p-3 text-white" aria-label="বন্ধ করুন">
                <X className="h-5 w-5" />
            </button>
            {images.length > 1 && (
                <>
                    <button
                        type="button"
                        onClick={(e) => {
                            e.stopPropagation();
                            step(-1);
                        }}
                        className="absolute left-2 rounded-full bg-white/10 p-3 text-white"
                        aria-label="আগের ছবি"
                    >
                        <ChevronLeft className="h-6 w-6" />
                    </button>
                    <button
                        type="button"
                        onClick={(e) => {
                            e.stopPropagation();
                            step(1);
                        }}
                        className="absolute right-2 rounded-full bg-white/10 p-3 text-white"
                        aria-label="পরের ছবি"
                    >
                        <ChevronRight className="h-6 w-6" />
                    </button>
                </>
            )}
            <img src={image.url} alt={image.name} className="max-h-full max-w-full rounded-lg object-contain" onClick={(e) => e.stopPropagation()} />
        </div>
    );
}

interface Props {
    label: string;
    existing: OrderImage[];
    removedIds?: number[];
    newFiles?: File[];
    /** Absent means read-only. */
    onAdd?: (files: File[]) => void;
    onRemoveNew?: (index: number) => void;
    onRemoveExisting?: (id: number) => void;
    onRestore?: () => void;
    error?: string;
    max?: number;
}

/**
 * One image zone of an order item: product photos or design drawings.
 *
 * Picked files stay in the form until the order is saved, shown with a "নতুন"
 * badge. Removing a saved image only marks it; it goes when the order saves.
 */
export function ImagePicker({
    label,
    existing,
    removedIds = [],
    newFiles = [],
    onAdd,
    onRemoveNew,
    onRemoveExisting,
    onRestore,
    error,
    max = 10,
}: Props) {
    const [busy, setBusy] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);
    const [viewing, setViewing] = useState<number | null>(null);
    const previews = useObjectUrls(newFiles);

    const kept = existing.filter((image) => !removedIds.includes(image.id));
    const tiles = [
        ...kept.map((image) => ({
            key: `e${image.id}`,
            thumb: image.thumb,
            url: image.url,
            name: image.name,
            isNew: false,
            remove: () => onRemoveExisting?.(image.id),
        })),
        ...newFiles.map((file, i) => ({
            key: `n${i}-${file.name}`,
            thumb: previews[i],
            url: previews[i],
            name: file.name,
            isNew: true,
            remove: () => onRemoveNew?.(i),
        })),
    ];
    const editable = Boolean(onAdd);

    const pick = async (event: ChangeEvent<HTMLInputElement>) => {
        const picked = Array.from(event.target.files ?? []);
        event.target.value = '';
        if (picked.length === 0) return;

        const images = picked.filter((file) => file.type.startsWith('image/'));
        const room = max - tiles.length;
        setLocalError(
            images.length < picked.length
                ? 'শুধু ছবি দেওয়া যাবে।'
                : images.length > room
                  ? `সর্বোচ্চ ${toBengaliDigits(max)}টি ছবি দেওয়া যাবে।`
                  : null,
        );
        if (room <= 0) return;

        setBusy(true);
        try {
            onAdd?.(await Promise.all(images.slice(0, room).map(compressImage)));
        } finally {
            setBusy(false);
        }
    };

    if (!editable && tiles.length === 0) return null;

    return (
        <div className="flex flex-col gap-2">
            <p className="text-sm font-medium">
                {label} ({toBengaliDigits(tiles.length)})
            </p>

            <div className="grid grid-cols-4 gap-2 sm:grid-cols-6">
                {tiles.map((tile, i) => (
                    <div key={tile.key} className="relative aspect-square overflow-hidden rounded-lg border">
                        <button type="button" onClick={() => setViewing(i)} className="h-full w-full">
                            <img src={tile.thumb} alt={tile.name} loading="lazy" className="h-full w-full object-cover" />
                        </button>
                        {tile.isNew && (
                            <span className="bg-primary text-primary-foreground absolute bottom-1 left-1 rounded px-1 text-[10px]">নতুন</span>
                        )}
                        {editable && (
                            <button
                                type="button"
                                onClick={tile.remove}
                                aria-label="ছবি সরান"
                                className="bg-background/90 absolute top-1 right-1 rounded-full p-1.5 shadow"
                            >
                                <X className="text-destructive h-4 w-4" />
                            </button>
                        )}
                    </div>
                ))}

                {editable && tiles.length < max && (
                    <Button
                        type="button"
                        variant="outline"
                        className={cn('aspect-square h-auto flex-col gap-1 border-dashed p-1 text-xs')}
                        disabled={busy}
                        asChild
                    >
                        <label>
                            {busy ? <LoaderCircle className="h-5 w-5 animate-spin" /> : <ImagePlus className="h-5 w-5" />}
                            যোগ করুন
                            <input type="file" accept="image/*" multiple className="hidden" onChange={pick} disabled={busy} />
                        </label>
                    </Button>
                )}
            </div>

            {editable && removedIds.some((id) => existing.some((image) => image.id === id)) && (
                <p className="text-muted-foreground text-sm">
                    {toBengaliDigits(existing.filter((image) => removedIds.includes(image.id)).length)}টি ছবি সংরক্ষণের সময় মুছে যাবে।{' '}
                    <button type="button" onClick={onRestore} className="text-foreground underline">
                        পূর্বাবস্থায় ফেরান
                    </button>
                </p>
            )}

            {(localError ?? error) && <p className="text-destructive text-sm">{localError ?? error}</p>}

            {viewing !== null && <ImageViewer images={tiles} index={viewing} onClose={() => setViewing(null)} />}
        </div>
    );
}
