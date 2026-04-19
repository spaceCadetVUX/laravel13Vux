<?php

namespace App\Observers;

use App\Jobs\Seo\SyncJsonldSchema;
use App\Models\Review;

class ReviewObserver
{
    /**
     * Re-sync Product JSON-LD whenever a review is saved.
     * This updates the AggregateRating schema on the product page.
     * Only dispatch if the review is approved — unapproved reviews
     * should not affect the product's public schema.
     */
    public function saved(Review $review): void
    {
        if ($review->is_approved) {
            dispatch(new SyncJsonldSchema($review->product))->onQueue('seo');
        }
    }

    /**
     * Re-sync when a review is deleted so AggregateRating is recalculated.
     */
    public function deleted(Review $review): void
    {
        dispatch(new SyncJsonldSchema($review->product))->onQueue('seo');
    }
}
