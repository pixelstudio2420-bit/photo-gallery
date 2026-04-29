<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\PhotographerAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Photographer manages their weekly schedule + ad-hoc blocks (holidays).
 *
 * Routes:
 *   GET   /photographer/availability        list + form
 *   POST  /photographer/availability        create rule
 *   DELETE /photographer/availability/{id}  remove rule
 */
class AvailabilityController extends Controller
{
    public function index()
    {
        $rules = PhotographerAvailability::forPhotographer(Auth::id())
            ->orderBy('type')
            ->orderBy('day_of_week')
            ->orderBy('specific_date')
            ->orderBy('time_start')
            ->get();

        return view('photographer.availability.index', [
            'recurring'  => $rules->where('type', PhotographerAvailability::TYPE_RECURRING)->values(),
            'overrides'  => $rules->where('type', PhotographerAvailability::TYPE_OVERRIDE)->values(),
        ]);
    }

    public function store(Request $request)
    {
        $valid = $request->validate([
            'type'          => ['required', 'in:recurring,override'],
            'day_of_week'   => ['required_if:type,recurring', 'nullable', 'integer', 'between:0,6'],
            'specific_date' => ['required_if:type,override', 'nullable', 'date'],
            'time_start'    => ['required', 'date_format:H:i'],
            'time_end'      => ['required', 'date_format:H:i', 'after:time_start'],
            'effect'        => ['required', 'in:available,blocked'],
            'label'         => ['nullable', 'string', 'max:100'],
        ]);

        PhotographerAvailability::create([
            'photographer_id' => Auth::id(),
            ...$valid,
        ]);

        return redirect()->route('photographer.availability')
            ->with('success', 'เพิ่มกฎเวลาทำงานแล้ว');
    }

    public function destroy(PhotographerAvailability $availability)
    {
        if ($availability->photographer_id !== Auth::id()) abort(403);
        $availability->delete();
        return redirect()->route('photographer.availability')
            ->with('success', 'ลบกฎออกแล้ว');
    }
}
