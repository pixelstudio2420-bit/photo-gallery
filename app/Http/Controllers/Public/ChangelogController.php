<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ChangelogEntry;

class ChangelogController extends Controller
{
    public function index()
    {
        $audience = auth()->check() ? 'public' : 'public';

        $entries = ChangelogEntry::published()
            ->forAudience($audience)
            ->orderByDesc('released_on')
            ->orderByDesc('id')
            ->paginate(20);

        $grouped = $entries->groupBy(fn ($e) => $e->released_on->format('Y-m'));

        return view('public.changelog.index', compact('entries', 'grouped'));
    }
}
