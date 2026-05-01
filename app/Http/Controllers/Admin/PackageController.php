<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PricingPackage;
use App\Models\Event;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        // Order by event first then sort_order so packages from the same
        // event sit next to each other in the list — eliminates the
        // "5 names repeating across the table" appearance the buyer
        // reported when each event has a default 5-bundle template.
        $query = PricingPackage::query()
            ->with('event:id,name')
            ->orderBy('event_id')
            ->orderBy('sort_order')
            ->orderBy('photo_count');

        if ($request->filled('event_id')) {
            $query->where('event_id', $request->event_id);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $packages = $query->paginate(50);
        $events = Event::orderBy('name')->get(['id', 'name']);

        // Stats banner — gives the admin context that 15 rows is "5
        // bundles × 3 events", not actual duplicate data.
        $stats = [
            'total'    => PricingPackage::count(),
            'events'   => PricingPackage::distinct()->count('event_id'),
            'global'   => PricingPackage::whereNull('event_id')->count(),
            'featured' => PricingPackage::where('is_featured', true)->count(),
        ];

        return view('admin.packages.index', compact('packages', 'events', 'stats'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'photo_count' => 'required|integer|min:1',
            'price'       => 'required|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'event_id'    => 'nullable|exists:events,id',
            'is_active'   => 'nullable|boolean',
        ]);

        PricingPackage::create([
            'name'        => $request->name,
            'photo_count' => $request->photo_count,
            'price'       => $request->price,
            'description' => $request->description,
            'event_id'    => $request->event_id,
            'is_active'   => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'เพิ่มแพ็คเกจสำเร็จ');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'photo_count' => 'required|integer|min:1',
            'price'       => 'required|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'event_id'    => 'nullable|exists:events,id',
            'is_active'   => 'nullable|boolean',
        ]);

        $pkg = PricingPackage::findOrFail($id);
        $pkg->update([
            'name'        => $request->name,
            'photo_count' => $request->photo_count,
            'price'       => $request->price,
            'description' => $request->description,
            'event_id'    => $request->event_id,
            'is_active'   => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'แก้ไขแพ็คเกจสำเร็จ');
    }

    public function destroy($id)
    {
        PricingPackage::findOrFail($id)->delete();
        return back()->with('success', 'ลบแพ็คเกจสำเร็จ');
    }
}
