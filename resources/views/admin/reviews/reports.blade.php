@extends('layouts.admin')

@section('title', 'รีวิวที่ถูกรายงาน')

@section('content')
<div class="space-y-5">

  {{-- Header --}}
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-800 dark:text-gray-100">
        <i class="bi bi-flag-fill text-red-500 mr-2"></i>รีวิวที่ถูกรายงาน
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">จัดการกับรีวิวที่ผู้ใช้รายงานว่าไม่เหมาะสม</p>
    </div>
    <a href="{{ route('admin.reviews.index') }}" class="px-4 py-2 border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5 transition">
      ← กลับไปรีวิวทั้งหมด
    </a>
  </div>

  {{-- Filter --}}
  <form method="GET" class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4 flex gap-3 flex-wrap">
    <select name="status" class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
      <option value="pending" {{ request('status','pending') === 'pending' ? 'selected' : '' }}>รอดำเนินการ</option>
      <option value="reviewed" {{ request('status') === 'reviewed' ? 'selected' : '' }}>ดำเนินการแล้ว</option>
      <option value="dismissed" {{ request('status') === 'dismissed' ? 'selected' : '' }}>ยกเลิก</option>
    </select>
    <select name="reason" class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
      <option value="">ทุกเหตุผล</option>
      @foreach(\App\Models\ReviewReport::REASONS as $key => $label)
      <option value="{{ $key }}" {{ request('reason') === $key ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>
    <button type="submit" class="px-4 py-2 bg-indigo-500 text-white rounded-lg text-sm font-medium hover:bg-indigo-600">ค้นหา</button>
  </form>

  {{-- Reports List --}}
  <div class="space-y-3">
    @forelse($reports as $rep)
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5">
      <div class="flex items-start justify-between gap-4 mb-3">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-xs font-semibold">
              <i class="bi bi-flag-fill mr-1"></i>{{ $rep->reason_label }}
            </span>
            @if($rep->status === 'pending')
              <span class="text-xs px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full">รอดำเนินการ</span>
            @elseif($rep->status === 'reviewed')
              <span class="text-xs px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded-full">ดำเนินการแล้ว</span>
            @else
              <span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-700 rounded-full">ยกเลิก</span>
            @endif
          </div>
          <div class="text-xs text-gray-500">
            รายงานโดย: <strong>{{ $rep->user->first_name ?? 'Unknown' }}</strong> · {{ $rep->created_at?->diffForHumans() }}
          </div>
        </div>

        @if($rep->status === 'pending')
        <div class="flex gap-2">
          <form method="POST" action="{{ route('admin.reviews.reports.resolve', $rep) }}" class="inline">
            @csrf
            <input type="hidden" name="action" value="hide_review">
            <button type="submit" onclick="return confirm('ซ่อนรีวิวและปิดรายงาน?')"
                    class="px-3 py-1.5 bg-amber-50 text-amber-600 rounded-lg text-xs font-medium hover:bg-amber-100">
              <i class="bi bi-eye-slash"></i> ซ่อนรีวิว
            </button>
          </form>
          <form method="POST" action="{{ route('admin.reviews.reports.resolve', $rep) }}" class="inline">
            @csrf
            <input type="hidden" name="action" value="delete_review">
            <button type="submit" onclick="return confirm('ลบรีวิวและปิดรายงาน?')"
                    class="px-3 py-1.5 bg-red-50 text-red-600 rounded-lg text-xs font-medium hover:bg-red-100">
              <i class="bi bi-trash"></i> ลบรีวิว
            </button>
          </form>
          <form method="POST" action="{{ route('admin.reviews.reports.resolve', $rep) }}" class="inline">
            @csrf
            <input type="hidden" name="action" value="dismiss">
            <button type="submit" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-200">
              <i class="bi bi-x"></i> ไม่พบปัญหา
            </button>
          </form>
        </div>
        @endif
      </div>

      {{-- Description --}}
      @if($rep->description)
      <div class="mb-3 p-3 bg-red-50 dark:bg-red-500/10 border-l-4 border-red-500 rounded-r-lg">
        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $rep->description }}</p>
      </div>
      @endif

      {{-- Review --}}
      @if($rep->review)
      <div class="p-4 bg-gray-50 dark:bg-white/[0.02] rounded-xl">
        <div class="flex items-center gap-2 mb-2">
          <span class="font-semibold text-sm">{{ $rep->review->user->first_name ?? 'Unknown' }}</span>
          <span class="text-amber-500">
            @for($i=1;$i<=5;$i++)<i class="bi bi-star{{ $i <= $rep->review->rating ? '-fill' : '' }} text-xs"></i>@endfor
          </span>
          <span class="text-xs text-gray-400">{{ $rep->review->created_at?->format('d/m/Y') }}</span>
        </div>
        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $rep->review->comment }}</p>

        <div class="mt-2 text-xs text-gray-500">
          → ช่างภาพ: {{ $rep->review->photographer->first_name ?? 'N/A' }}
          @if($rep->review->event) · อีเวนต์: {{ $rep->review->event->name }} @endif
        </div>
      </div>
      @else
      <div class="p-3 bg-gray-100 rounded text-sm text-gray-500 italic">(รีวิวถูกลบแล้ว)</div>
      @endif
    </div>
    @empty
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-12 text-center">
      <i class="bi bi-check-circle text-4xl text-emerald-300"></i>
      <p class="text-gray-500 mt-2">ไม่มีรีวิวที่ถูกรายงาน</p>
    </div>
    @endforelse
  </div>

  {{-- Pagination --}}
  @if($reports->hasPages())
  <div class="flex justify-center">{{ $reports->links() }}</div>
  @endif
</div>
@endsection
