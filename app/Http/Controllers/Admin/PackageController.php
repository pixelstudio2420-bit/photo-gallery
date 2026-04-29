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
        $query = PricingPackage::query()->orderBy('photo_count');

        if ($request->filled('event_id')) {
            $query->where('event_id', $request->event_id);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $packages = $query->paginate(25);
        $events = Event::orderBy('name')->get(['id', 'name']);

        return view('admin.packages.index', compact('packages', 'events'));
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
