<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EventCloneController extends Controller
{
    /**
     * Clone an existing event's metadata (no photos) into a new draft.
     */
    public function clone(Event $event)
    {
        try {
            DB::beginTransaction();

            $baseSlug = Str::slug(($event->slug ?: $event->name) . '-copy', '-') ?: 'event-copy';
            $newSlug = $this->uniqueSlug($baseSlug);

            $copy = $event->replicate([
                'view_count',
            ]);
            $copy->name        = ($event->name ?? 'Event') . ' (Copy)';
            $copy->slug        = $newSlug;
            $copy->status      = 'draft';
            $copy->view_count  = 0;
            if (property_exists($event, 'photo_count') || isset($event->photo_count)) {
                $copy->photo_count = 0;
            }
            $copy->drive_folder_id   = null;
            $copy->drive_folder_link = null;
            $copy->created_at = now();
            $copy->updated_at = now();
            $copy->save();

            // Optionally duplicate pricing packages for this event
            try {
                if (class_exists(\App\Models\PricingPackage::class)) {
                    $pkgs = \App\Models\PricingPackage::where('event_id', $event->id)->get();
                    foreach ($pkgs as $pkg) {
                        $newPkg = $pkg->replicate();
                        $newPkg->event_id = $copy->id;
                        $newPkg->save();
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('EventCloneController: package copy failed: ' . $e->getMessage());
            }

            DB::commit();

            return redirect()
                ->route('admin.events.edit', $copy->id)
                ->with('success', 'คัดลอกอีเวนต์เรียบร้อย — ปรับแก้รายละเอียดและเผยแพร่ได้ที่นี่');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::warning('EventCloneController::clone failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'ไม่สามารถคัดลอกอีเวนต์ได้: ' . $e->getMessage());
        }
    }

    protected function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 2;
        while (Event::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }
}
