import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { router, useForm } from '@inertiajs/react';
import { Camera, LoaderCircle, Trash2, X } from 'lucide-react';
import { ChangeEvent, useState } from 'react';

export interface Photo {
    id: number;
    collection: 'photos' | 'designs';
    name: string;
    url: string;
    thumb: string;
}

interface Props {
    orderId: number;
    photos: Photo[];
    canManage: boolean;
}

/**
 * Photos of the piece and the design drawings.
 *
 * The camera capture attribute means tapping this on a phone opens the camera
 * rather than a file browser, which is how these actually get taken: someone
 * photographs the drawing the customer brought in, at the counter.
 *
 * Uploads go straight off the input with no preview step. The shop is holding
 * a phone in one hand.
 */
export function OrderPhotos({ orderId, photos, canManage }: Props) {
    const [viewing, setViewing] = useState<Photo | null>(null);

    const form = useForm<{ photos: File[]; collection: string }>({
        photos: [],
        collection: 'photos',
    });

    const upload = (event: ChangeEvent<HTMLInputElement>, collection: 'photos' | 'designs') => {
        const files = Array.from(event.target.files ?? []);

        if (files.length === 0) return;

        form.transform(() => ({ photos: files, collection }));
        form.post(route('orders.photos.store', orderId), {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => {
                event.target.value = '';
            },
        });
    };

    const remove = (photo: Photo) => {
        if (!window.confirm('এই ছবি মুছে ফেলতে চান?')) return;

        router.delete(route('orders.photos.destroy', [orderId, photo.id]), { preserveScroll: true });
    };

    const errors = form.errors as Record<string, string>;
    const uploadError = errors.photos ?? errors['photos.0'];

    return (
        <section className="flex flex-col gap-3 rounded-lg border p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="font-medium">ছবি</h2>

                {canManage && (
                    <div className="flex gap-2">
                        <Button type="button" variant="outline" className="h-11" disabled={form.processing} asChild>
                            <label>
                                {form.processing ? (
                                    <LoaderCircle className="h-4 w-4 animate-spin" />
                                ) : (
                                    <Camera className="h-4 w-4" />
                                )}
                                আসবাবের ছবি
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    capture="environment"
                                    multiple
                                    className="hidden"
                                    onChange={(e) => upload(e, 'photos')}
                                />
                            </label>
                        </Button>

                        <Button type="button" variant="outline" className="h-11" disabled={form.processing} asChild>
                            <label>
                                নকশা
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    multiple
                                    className="hidden"
                                    onChange={(e) => upload(e, 'designs')}
                                />
                            </label>
                        </Button>
                    </div>
                )}
            </div>

            {uploadError && <p className="text-destructive text-sm">{uploadError}</p>}

            {photos.length === 0 ? (
                <p className="text-muted-foreground text-sm">এখনো কোনো ছবি দেওয়া হয়নি।</p>
            ) : (
                <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
                    {photos.map((photo) => (
                        <div key={photo.id} className="group relative aspect-square overflow-hidden rounded-lg border">
                            <button type="button" onClick={() => setViewing(photo)} className="h-full w-full">
                                <img src={photo.thumb} alt={photo.name} loading="lazy" className="h-full w-full object-cover" />
                            </button>

                            {photo.collection === 'designs' && (
                                <span className="bg-background/90 absolute top-1 left-1 rounded px-1.5 py-0.5 text-xs">নকশা</span>
                            )}

                            {canManage && (
                                <button
                                    type="button"
                                    onClick={() => remove(photo)}
                                    title="মুছে ফেলুন"
                                    className="bg-background/90 absolute top-1 right-1 rounded p-1 opacity-0 transition-opacity group-hover:opacity-100 focus:opacity-100"
                                >
                                    <Trash2 className="text-destructive h-4 w-4" />
                                </button>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {viewing && (
                <div
                    role="dialog"
                    aria-modal="true"
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
                    onClick={() => setViewing(null)}
                >
                    <button
                        type="button"
                        onClick={() => setViewing(null)}
                        className="absolute top-4 right-4 rounded-full bg-white/10 p-2 text-white"
                        aria-label="বন্ধ করুন"
                    >
                        <X className="h-5 w-5" />
                    </button>
                    <img src={viewing.url} alt={viewing.name} className="max-h-full max-w-full rounded-lg object-contain" />
                </div>
            )}

            <Label className="text-muted-foreground text-xs font-normal">
                ছবি সার্ভারে ছোট করে রাখা হয়, তাই মোবাইলে দ্রুত খোলে।
            </Label>
        </section>
    );
}
