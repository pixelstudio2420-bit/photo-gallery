<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StorageStatsService;

class StorageStatsController extends Controller
{
    public function index(StorageStatsService $service)
    {
        $overview     = $service->overview();
        $photographers = $service->byPhotographer(10);

        return view('admin.storage.index', compact('overview', 'photographers'));
    }
}
