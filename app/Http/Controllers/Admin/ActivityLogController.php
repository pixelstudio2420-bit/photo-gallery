<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;

class ActivityLogController extends Controller
{
    public function index() { return view('admin.activity-log', ['logs' => ActivityLog::with('admin')->orderByDesc('created_at')->paginate(50)]); }
}
