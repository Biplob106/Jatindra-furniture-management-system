<?php

namespace App\Models\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Photos of the piece and design drawings, for orders and their items.
 *
 * Order still carries these for images uploaded before they moved onto items.
 * One trait so the two owners can never disagree on what is accepted or how it
 * is compressed.
 */
trait HasOrderImages
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('designs')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * The compression the rollout checklist asks for: nothing over 1600px, and
     * a thumbnail for the grid.
     *
     * Non-queued on purpose. A shop floor PC has no queue worker running, and
     * a photo that only appears once someone remembers to start one is worse
     * than a save that takes an extra second.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 400, 400)
            ->quality(70)
            ->nonQueued();

        $this->addMediaConversion('web')
            ->fit(Fit::Contain, 1600, 1600)
            ->quality(75)
            ->nonQueued();
    }
}
