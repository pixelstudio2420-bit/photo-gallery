@extends('layouts.admin')

@section('title', 'AI Cost Report')

@section('content')
<div class="space-y-5">

  <div>
    <h1 class="text-2xl font-bold text-slate-800 dark:text-gray-100">
      <i class="bi bi-cash-coin text-emerald-500 mr-2"></i>AI Cost Report
    </h1>
    <p class="text-sm text-gray-500 mt-1">สรุปค่าใช้จ่าย AI แยกตาม provider, model, และประเภท</p>
  </div>

  {{-- Date range filter --}}
  <form method="GET" class="bg-white border border-gray-100 rounded-2xl p-4 flex gap-3 flex-wrap">
    <div>
      <label class="block text-xs text-gray-600 mb-1">จาก</label>
      <input type="date" name="date_from" value="{{ $dateFrom }}" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
    </div>
    <div>
      <label class="block text-xs text-gray-600 mb-1">ถึง</label>
      <input type="date" name="date_to" value="{{ $dateTo }}" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
    </div>
    <button type="submit" class="px-4 py-2 bg-indigo-500 text-white rounded-lg text-sm self-end">ค้นหา</button>
  </form>

  {{-- Totals --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 text-white rounded-2xl p-5">
      <div class="text-xs text-emerald-100">ค่าใช้จ่ายรวม</div>
      <div class="text-3xl font-bold mt-1">${{ number_format($totals['total_cost'] ?? 0, 2) }}</div>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl p-5">
      <div class="text-xs text-gray-500">Tasks รวม</div>
      <div class="text-3xl font-bold text-indigo-600">{{ number_format($totals['total_tasks'] ?? 0) }}</div>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl p-5">
      <div class="text-xs text-gray-500">Input Tokens</div>
      <div class="text-2xl font-bold text-blue-600">{{ number_format($totals['total_tokens_input'] ?? 0) }}</div>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl p-5">
      <div class="text-xs text-gray-500">Output Tokens</div>
      <div class="text-2xl font-bold text-purple-600">{{ number_format($totals['total_tokens_output'] ?? 0) }}</div>
    </div>
  </div>

  {{-- By Provider --}}
  <div class="bg-white border border-gray-100 rounded-2xl p-5">
    <h3 class="font-bold text-slate-800 mb-3">ค่าใช้จ่ายตาม Provider</h3>
    <table class="w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="text-left p-2 text-xs uppercase text-gray-600">Provider</th>
          <th class="text-right p-2 text-xs uppercase text-gray-600">Tasks</th>
          <th class="text-right p-2 text-xs uppercase text-gray-600">Input Tokens</th>
          <th class="text-right p-2 text-xs uppercase text-gray-600">Output Tokens</th>
          <th class="text-right p-2 text-xs uppercase text-gray-600">Avg Time</th>
          <th class="text-right p-2 text-xs uppercase text-gray-600">Cost</th>
        </tr>
      </thead>
      <tbody>
        @forelse($costByProvider ?? [] as $row)
        <tr class="border-t border-gray-50">
          <td class="p-2 font-semibold">{{ $row->provider }}</td>
          <td class="p-2 text-right">{{ number_format($row->task_count) }}</td>
          <td class="p-2 text-right text-xs">{{ number_format($row->total_tokens_input) }}</td>
          <td class="p-2 text-right text-xs">{{ number_format($row->total_tokens_output) }}</td>
          <td class="p-2 text-right text-xs text-gray-500">{{ number_format($row->avg_processing_time / 1000, 1) }}s</td>
          <td class="p-2 text-right font-bold text-emerald-600">${{ number_format($row->total_cost, 4) }}</td>
        </tr>
        @empty
        <tr><td colspan="6" class="p-8 text-center text-gray-400">ไม่มีข้อมูลในช่วงเวลาที่เลือก</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- By Model --}}
  <div class="bg-white border border-gray-100 rounded-2xl p-5">
    <h3 class="font-bold text-slate-800 mb-3">ค่าใช้จ่ายตาม Model</h3>
    <table class="w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="text-left p-2 text-xs uppercase text-gray-600">Provider</th>
          <th class="text-left p-2 text-xs uppercase text-gray-600">Model</th>
          <th class="text-right p-2 text-xs uppercase text-gray-600">Tasks</th>
          <th class="text-right p-2 text-xs uppercase text-gray-600">Tokens (I/O)</th>
          <th class="text-right p-2 text-xs uppercase text-gray-600">Cost</th>
        </tr>
      </thead>
      <tbody>
        @forelse($costByModel ?? [] as $row)
        <tr class="border-t border-gray-50">
          <td class="p-2">{{ $row->provider }}</td>
          <td class="p-2 font-mono text-xs">{{ $row->model }}</td>
          <td class="p-2 text-right">{{ number_format($row->task_count) }}</td>
          <td class="p-2 text-right text-xs">{{ number_format($row->total_tokens_input) }}/{{ number_format($row->total_tokens_output) }}</td>
          <td class="p-2 text-right font-bold text-emerald-600">${{ number_format($row->total_cost, 4) }}</td>
        </tr>
        @empty
        <tr><td colspan="5" class="p-8 text-center text-gray-400">ไม่มีข้อมูล</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- By Type --}}
  @if(isset($costByType) && count($costByType) > 0)
  <div class="bg-white border border-gray-100 rounded-2xl p-5">
    <h3 class="font-bold text-slate-800 mb-3">ค่าใช้จ่ายตามประเภท</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
      @foreach($costByType as $row)
      <div class="bg-gray-50 rounded-lg p-3">
        <div class="text-xs text-gray-600">{{ $row->type }}</div>
        <div class="flex items-center justify-between mt-1">
          <span class="text-lg font-bold text-indigo-600">${{ number_format($row->total_cost, 4) }}</span>
          <span class="text-xs text-gray-500">{{ $row->task_count }} tasks</span>
        </div>
      </div>
      @endforeach
    </div>
  </div>
  @endif

  {{-- Daily cost chart --}}
  @if(isset($dailyCost) && count($dailyCost) > 0)
  <div class="bg-white border border-gray-100 rounded-2xl p-5">
    <h3 class="font-bold text-slate-800 mb-3">รายวัน</h3>
    <div class="space-y-1">
      @foreach($dailyCost as $day)
      <div class="flex items-center gap-3 text-sm">
        <span class="w-24 text-gray-500">{{ \Carbon\Carbon::parse($day->date)->format('d/m/Y') }}</span>
        <div class="flex-1 h-6 bg-gray-100 rounded overflow-hidden relative">
          @php
            $max = max(array_map(fn($r) => $r->total_cost, $dailyCost->toArray()));
            $pct = $max > 0 ? ($day->total_cost / $max) * 100 : 0;
          @endphp
          <div class="h-full bg-gradient-to-r from-emerald-400 to-emerald-500" style="width: {{ $pct }}%"></div>
          <div class="absolute inset-0 flex items-center px-2 text-xs">
            <span class="font-semibold">${{ number_format($day->total_cost, 4) }}</span>
            <span class="ml-auto text-gray-600">{{ $day->task_count }} tasks</span>
          </div>
        </div>
      </div>
      @endforeach
    </div>
  </div>
  @endif
</div>
@endsection
