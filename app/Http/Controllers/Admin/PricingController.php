<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\PricingEventPrice;
use App\Models\Event;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function index(Request $request)
    {
        $prices = PricingEventPrice::with('event')
            ->when($request->q, fn($q, $s) => $q->whereHas('event', fn($eq) => $eq->where('name', 'ilike', "%{$s}%")))
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.pricing.index', compact('prices'));
    }

    public function create()
    {
        $events = Event::orderBy('name')->get();
        return view('admin.pricing.create', compact('events'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id'        => 'required|exists:event_events,id',
            'price_per_photo' => 'required|numeric|min:0',
        ]);

        PricingEventPrice::updateOrCreate(
            ['event_id' => $validated['event_id']],
            [
                'price_per_photo' => $validated['price_per_photo'],
                'set_by_admin'    => true,
                'updated_at'      => now(),
            ]
        );

        return redirect()->route('admin.pricing.index')->with('success', 'สร้างสำเร็จ');
    }

    public function edit($pricing)
    {
        $price = PricingEventPrice::with('event')->where('event_id', $pricing)->firstOrFail();
        $events = Event::orderBy('name')->get();
        return view('admin.pricing.edit', compact('price', 'events'));
    }

    public function update(Request $request, $pricing)
    {
        $validated = $request->validate([
            'event_id'        => 'required|exists:event_events,id',
            'price_per_photo' => 'required|numeric|min:0',
        ]);

        $price = PricingEventPrice::where('event_id', $pricing)->firstOrFail();

        // If event_id changed, delete old and create new
        if ($validated['event_id'] != $pricing) {
            $price->delete();
            PricingEventPrice::create([
                'event_id'        => $validated['event_id'],
                'price_per_photo' => $validated['price_per_photo'],
                'set_by_admin'    => true,
                'updated_at'      => now(),
            ]);
        } else {
            $price->update([
                'price_per_photo' => $validated['price_per_photo'],
                'set_by_admin'    => true,
                'updated_at'      => now(),
            ]);
        }

        return redirect()->route('admin.pricing.index')->with('success', 'อัพเดทสำเร็จ');
    }

    public function destroy($pricing)
    {
        $price = PricingEventPrice::where('event_id', $pricing)->firstOrFail();
        $price->delete();

        return redirect()->route('admin.pricing.index')->with('success', 'ลบสำเร็จ');
    }
}
