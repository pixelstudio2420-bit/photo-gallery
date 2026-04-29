<?php

namespace App\Observers;

use App\Models\SeoPage;
use App\Models\SeoPageRevision;
use Illuminate\Support\Facades\Auth;

/**
 * Wires three concerns onto every SeoPage save:
 *
 *   1. match_key derivation — keeps the unique index honest. Without
 *      this we'd need every controller to remember to compute it.
 *   2. Revision history — append-only snapshot of the prior state.
 *   3. Cache bust — invalidate the per-route lookup cache so the new
 *      override takes effect on the very next request.
 *
 * `creating` / `updating` hooks (not saved) compute the key BEFORE
 * write, so the unique constraint sees a consistent value.
 *
 * `saved` / `deleted` hooks bust cache AFTER the row is committed.
 */
class SeoPageObserver
{
    public function creating(SeoPage $page): void
    {
        $page->match_key  = SeoPage::buildMatchKey($page->route_params);
        $page->created_by = $page->created_by ?: $this->actorId();
        $page->updated_by = $page->updated_by ?: $this->actorId();
        // The DB default is 1, but the in-memory model is still null when
        // saved() runs to write the revision row. Set it explicitly so
        // the snapshot's version column is never null.
        $page->version    = $page->version ?? 1;
    }

    public function updating(SeoPage $page): void
    {
        // Recompute on update too — route_params can change.
        $page->match_key  = SeoPage::buildMatchKey($page->route_params);
        $page->updated_by = $this->actorId();
        $page->version    = ($page->version ?? 1) + 1;
    }

    public function saved(SeoPage $page): void
    {
        // Snapshot the full row for rollback. We do this AFTER save so
        // the snapshot reflects the just-persisted state — restoring it
        // simply re-applies the same fields.
        SeoPageRevision::create([
            'seo_page_id'   => $page->id,
            'version'       => $page->version,
            'snapshot'      => $page->only([
                'route_name', 'locale', 'route_params', 'path_preview',
                'title', 'description', 'keywords', 'canonical_url', 'meta_robots',
                'og_title', 'og_description', 'og_image', 'og_type',
                'structured_data', 'alt_text_map',
                'is_active', 'is_locked',
            ]),
            'change_reason' => $page->changeReason,
            'changed_by'    => $this->actorId(),
        ]);

        $page->flushCache();
    }

    public function deleted(SeoPage $page): void
    {
        $page->flushCache();
    }

    private function actorId(): ?int
    {
        $admin = Auth::guard('admin')->user();
        return $admin ? (int) $admin->id : null;
    }
}
