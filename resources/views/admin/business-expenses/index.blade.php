@extends('layouts.admin')

@section('title', 'ค่าใช้จ่ายของระบบ')

@section('content')
<div class="flex flex-wrap justify-between items-center gap-3 mb-4">
  <h4 class="font-bold mb-0 tracking-tight">
    <i class="bi bi-wallet2 mr-2 text-indigo-500"></i>ค่าใช้จ่ายของระบบ
    <span class="text-sm font-normal text-gray-500 ml-2">Business Expenses</span>
  </h4>
  <div class="flex gap-2 flex-wrap">
    <a href="{{ route('admin.business-expenses.calculator') }}"
       class="px-4 py-2 border border-indigo-200 text-indigo-600 rounded-lg font-medium text-sm hover:bg-indigo-50 dark:border-indigo-500/30 dark:text-indigo-300 dark:hover:bg-indigo-500/10">
      <i class="bi bi-calculator mr-1"></i>คำนวณต้นทุน / Calculator
    </a>
    <a href="{{ route('admin.business-expenses.create') }}"
       class="bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg font-medium px-5 py-2 text-sm inline-flex items-center gap-1 transition hover:from-indigo-600 hover:to-indigo-700">
      <i class="bi bi-plus-lg mr-1"></i>เพิ่มรายการ
    </a>
  </div>
</div>

@if(session('success'))
<div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 rounded-xl p-3 mb-4 text-sm">
  <i class="bi bi-check-circle-fill mr-1"></i>{{ session('success') }}
</div>
@endif

{{-- ─── KPI Cards ───────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-4">
    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">รายจ่ายรวม/เดือน</div>
    <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-300">
      {{ number_format($totalMonthly, 2) }}
      <span class="text-sm font-normal text-gray-500">THB</span>
    </div>
  </div>
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-4">
    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">รายจ่ายรวม/ปี (ประมาณ)</div>
    <div class="text-2xl font-bold text-slate-700 dark:text-gray-100">
      {{ number_format($totalYearly, 0) }}
      <span class="text-sm font-normal text-gray-500">THB</span>
    </div>
  </div>
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-4">
    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">รายการทั้งหมด</div>
    <div class="text-2xl font-bold text-slate-700 dark:text-gray-100">{{ number_format($expenses->total()) }}</div>
  </div>
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-4">
    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">ค่าใช้จ่ายวิกฤต (Critical)</div>
    <div class="text-2xl font-bold {{ $critical->count() ? 'text-rose-600 dark:text-rose-400' : 'text-slate-400' }}">
      {{ number_format($critical->count()) }}
    </div>
  </div>
</div>

{{-- ─── Breakdown Cards ──────────────────────────────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4">
  {{-- By category --}}
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-4">
    <div class="flex items-center justify-between mb-3">
      <h5 class="font-semibold text-slate-700 dark:text-gray-100"><i class="bi bi-pie-chart mr-1 text-indigo-500"></i>แยกตามหมวดหมู่</h5>
      <div class="text-xs text-gray-500">รายการที่ active</div>
    </div>
    @if(empty($byCategory))
      <div class="text-sm text-gray-500 py-6 text-center">ยังไม่มีข้อมูล</div>
    @else
      <div class="space-y-2">
        @foreach($byCategory as $cat => $amt)
          @php $pct = $totalMonthly > 0 ? round(($amt / $totalMonthly) * 100, 1) : 0; @endphp
          <div>
            <div class="flex justify-between text-xs mb-1">
              <span class="font-medium text-slate-700 dark:text-gray-200">{{ $categories[$cat] ?? $cat }}</span>
              <span class="text-gray-500 dark:text-gray-400">{{ number_format($amt, 2) }} THB ({{ $pct }}%)</span>
            </div>
            <div class="h-2 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden">
              <div class="h-full bg-gradient-to-r from-indigo-500 to-indigo-600 rounded-full" style="width: {{ max(2, $pct) }}%"></div>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>

  {{-- By service --}}
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-4">
    <div class="flex items-center justify-between mb-3">
      <h5 class="font-semibold text-slate-700 dark:text-gray-100"><i class="bi bi-diagram-3 mr-1 text-emerald-500"></i>แยกตามบริการ / Service</h5>
      <div class="text-xs text-gray-500">แบ่งเฉลี่ยตามที่ระบุ</div>
    </div>
    @if(empty($byService))
      <div class="text-sm text-gray-500 py-6 text-center">ยังไม่มีข้อมูล</div>
    @else
      <div class="space-y-2">
        @foreach($byService as $svc => $amt)
          @php $pct = $totalMonthly > 0 ? round(($amt / $totalMonthly) * 100, 1) : 0; @endphp
          <div>
            <div class="flex justify-between text-xs mb-1">
              <span class="font-medium text-slate-700 dark:text-gray-200">{{ $services[$svc] ?? $svc }}</span>
              <span class="text-gray-500 dark:text-gray-400">{{ number_format($amt, 2) }} THB ({{ $pct }}%)</span>
            </div>
            <div class="h-2 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden">
              <div class="h-full bg-gradient-to-r from-emerald-500 to-emerald-600 rounded-full" style="width: {{ max(2, $pct) }}%"></div>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>
</div>

{{-- ─── Filters ───────────────────────────────────────────────── --}}
<form method="GET" class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-3 mb-4 flex flex-wrap items-center gap-3">
  <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="ค้นหาชื่อ / ผู้ให้บริการ"
         class="border border-gray-200 dark:border-white/5 dark:bg-slate-700 dark:text-gray-100 rounded-lg px-3 py-1.5 text-sm flex-1 min-w-[200px]">
  <select name="category" class="border border-gray-200 dark:border-white/5 dark:bg-slate-700 dark:text-gray-100 rounded-lg px-3 py-1.5 text-sm">
    <option value="">ทุกหมวดหมู่</option>
    @foreach($categories as $key => $label)
      <option value="{{ $key }}" {{ ($filters['category'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
    @endforeach
  </select>
  <select name="cycle" class="border border-gray-200 dark:border-white/5 dark:bg-slate-700 dark:text-gray-100 rounded-lg px-3 py-1.5 text-sm">
    <option value="">ทุกรอบบิล</option>
    @foreach($cycles as $key => $label)
      <option value="{{ $key }}" {{ ($filters['cycle'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
    @endforeach
  </select>
  <select name="status" class="border border-gray-200 dark:border-white/5 dark:bg-slate-700 dark:text-gray-100 rounded-lg px-3 py-1.5 text-sm">
    <option value="">ทุกสถานะ</option>
    <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Active</option>
    <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
  </select>
  <button type="submit" class="bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg px-4 py-1.5 text-sm font-medium">
    <i class="bi bi-funnel mr-1"></i>กรอง
  </button>
  <a href="{{ route('admin.business-expenses.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">ล้าง</a>
</form>

{{-- ─── Table ────────────────────────────────────────────────── --}}
<div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 dark:bg-slate-900/40 text-xs uppercase text-gray-500 dark:text-gray-400">
        <tr>
          <th class="px-3 py-2 text-left">รายการ</th>
          <th class="px-3 py-2 text-left">หมวดหมู่</th>
          <th class="px-3 py-2 text-left">รอบบิล</th>
          <th class="px-3 py-2 text-right">มูลค่า</th>
          <th class="px-3 py-2 text-right">บาท/เดือน</th>
          <th class="px-3 py-2 text-left">บริการที่แบกรับ</th>
          <th class="px-3 py-2 text-center">สถานะ</th>
          <th class="px-3 py-2 text-right">จัดการ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-white/5">
        @forelse($expenses as $e)
        <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/30 {{ $e->is_active ? '' : 'opacity-60' }}">
          <td class="px-3 py-2">
            <div class="font-semibold text-slate-800 dark:text-gray-100 flex items-center gap-1">
              {{ $e->name }}
              @if($e->is_critical)
                <span class="text-rose-500" title="Critical"><i class="bi bi-exclamation-triangle-fill text-xs"></i></span>
              @endif
            </div>
            @if($e->provider)
              <div class="text-xs text-gray-500 dark:text-gray-400">{{ $e->provider }}</div>
            @endif
          </td>
          <td class="px-3 py-2">
            <span class="inline-block text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">
              {{ $categories[$e->category] ?? $e->category }}
            </span>
          </td>
          <td class="px-3 py-2">
            <span class="text-xs text-slate-600 dark:text-gray-300">{{ $cycles[$e->billing_cycle] ?? $e->billing_cycle }}</span>
          </td>
          <td class="px-3 py-2 text-right">
            <div class="font-mono text-slate-700 dark:text-gray-200">
              {{ number_format($e->amount, 2) }} {{ $e->currency }}
            </div>
            @if($e->original_currency && $e->original_currency !== 'THB' && $e->original_amount)
              <div class="text-xs text-gray-400">≈ {{ number_format($e->original_amount, 2) }} {{ $e->original_currency }}</div>
            @endif
          </td>
          <td class="px-3 py-2 text-right">
            <div class="font-semibold text-indigo-600 dark:text-indigo-300 font-mono">
              {{ number_format($e->monthlyCost(), 2) }}
            </div>
          </td>
          <td class="px-3 py-2">
            @if(!empty($e->allocated_to))
              <div class="flex flex-wrap gap-1">
                @foreach($e->allocated_to as $svc)
                  <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">
                    {{ $services[$svc] ?? $svc }}
                  </span>
                @endforeach
              </div>
            @else
              <span class="text-xs text-gray-400 italic">shared</span>
            @endif
          </td>
          <td class="px-3 py-2 text-center">
            @if($e->is_active)
              <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">Active</span>
            @else
              <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-400">Inactive</span>
            @endif
          </td>
          <td class="px-3 py-2 text-right whitespace-nowrap">
            <a href="{{ route('admin.business-expenses.edit', $e) }}"
               class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-300 mr-2" title="แก้ไข">
              <i class="bi bi-pencil"></i>
            </a>
            <form action="{{ route('admin.business-expenses.destroy', $e) }}" method="POST" class="inline"
                  onsubmit="return confirm('ลบรายการนี้?');">
              @csrf @method('DELETE')
              <button type="submit" class="text-rose-500 hover:text-rose-700" title="ลบ">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="8" class="px-3 py-12 text-center">
            <i class="bi bi-receipt text-4xl text-gray-300 dark:text-gray-600"></i>
            <div class="mt-2 text-gray-500 dark:text-gray-400">ยังไม่มีรายการค่าใช้จ่าย</div>
            <a href="{{ route('admin.business-expenses.create') }}" class="mt-3 inline-block text-indigo-600 dark:text-indigo-300 hover:underline text-sm">
              <i class="bi bi-plus-lg mr-1"></i>เพิ่มรายการแรก
            </a>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($expenses->hasPages())
    <div class="p-3 border-t border-gray-100 dark:border-white/5">
      {{ $expenses->links() }}
    </div>
  @endif
</div>
@endsection
