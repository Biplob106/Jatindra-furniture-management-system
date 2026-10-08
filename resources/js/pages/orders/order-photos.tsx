import { ImageViewer } from '@/components/image-picker';
import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';

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
 * Photos attached to the whole order before images moved onto items. Shown so
 * nothing is lost, deletable, never added to: new images go on an item.
 */
export function OrderPhotos({ orderId, photos, canManage }: Props) {
    const [viewing, setViewing] = useState<number | null>(null);

    const remove = (photo: Photo) => {
        if (!window.confirm('এই ছবি মুছে ফেলতে চান?')) return;

        router.delete(route('orders.photos.destroy', [orderId, photo.id]), { preserveScroll: true });
    };

    return (
        <section className="flex flex-col gap-3 rounded-lg border p-4">
            <div>
                <h2 className="font-medium">অর্ডারের পুরনো ছবি</h2>
                <p className="text-muted-foreground text-sm">নতুন ছবি এখন প্রতিটি পণ্যের সাথে যোগ হয়।</p>
            </div>

            <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
                {photos.map((photo, index) => (
                    <div key={photo.id} className="relative aspect-square overflow-hidden rounded-lg border">
                        <button type="button" onClick={() => setViewing(index)} className="h-full w-full">
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
                                className="bg-background/90 absolute top-1 right-1 rounded p-1.5"
                            >
                                <Trash2 className="text-destructive h-4 w-4" />
                            </button>
                        )}
                    </div>
                ))}
            </div>

            {viewing !== null && <ImageViewer images={photos} index={viewing} onClose={() => setViewing(null)} />}
        </section>
    );
}
