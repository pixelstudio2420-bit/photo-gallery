@extends('layouts.admin')

@section('title', 'ประวัติกิจกรรม')

@section('content')
<div class="flex justify-between items-center mb-6">
  <h4 class="font-bold mb-0 tracking-tight">
    <i class="bi bi-clock-history mr-2" style="color:#6366f1;"></i>ประวัติกิจกรรม
  </h4>
  <span class="bg-indigo-50 text-indigo-600 text-sm font-medium px-3 py-1.5 rounded-full">{{ $logs->total() }} รายการ</span>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
  <div class="p-0">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="pl-4 px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ID</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">แอดมิน</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">การกระทำ</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">เป้าหมาย</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">IP</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">วันที่</th>
          </tr>
        </thead>
        <tbody>
          @forelse($logs as $log)
          <tr class="hover:bg-gray-50 transition">
            <td class="pl-4 px-4 py-3 font-semibold">{{ $log->id }}</td>
            <td class="px-4 py-3">
              @if($log->admin)
                <span class="font-medium">{{ $log->admin->first_name ?? $log->admin->email }}</span>
              @else
                <span class="text-gray-500">-</span>
              @endif
            </td>
            <td class="px-4 py-3">
              <span class="bg-indigo-50 text-indigo-600 text-xs font-medium px-2.5 py-1 rounded-full">{{ $log->action }}</span>
            </td>
            <td class="px-4 py-3 text-gray-500 text-sm">
              {{ $log->target_type }}{{ $log->target_id ? ' #' . $log->target_id : '' }}
            </td>
            <td class="px-4 py-3 text-gray-500 text-sm font-mono">{{ $log->ip_address }}</td>
            <td class="px-4 py-3 text-gray-500 text-sm">{{ $log->created_at->format('d/m/Y H:i') }}</td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center text-gray-500 py-10">
              <i class="bi bi-clock-history" style="font-size:2rem;opacity:0.3;"></i>
              <p class="mt-2 mb-0">ไม่พบประวัติกิจกรรม</p>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

@if($logs->hasPages())
<div class="flex justify-center mt-6">
  {{ $logs->links() }}
</div>
@endif
@endsection
