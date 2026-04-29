@extends('layouts.app')

@section('title', 'Support Tickets')

@section('content')
<div class="max-w-4xl mx-auto">

  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">
        <i class="bi bi-life-preserver text-indigo-500 mr-2"></i>Support Tickets ของฉัน
      </h1>
      <p class="text-sm text-gray-500 mt-1">ติดตามคำถามและปัญหาที่ส่งให้เรา</p>
    </div>
    <a href="{{ route('contact') }}" class="px-4 py-2 bg-indigo-500 text-white rounded-xl text-sm font-medium hover:bg-indigo-600">
      <i class="bi bi-plus-lg"></i> สร้าง Ticket ใหม่
    </a>
  </div>

  {{-- Stats --}}
  <div class="grid grid-cols-3 gap-3 mb-6">
    <a href="?" class="bg-white border border-gray-100 rounded-2xl p-4 hover:border-indigo-200 transition {{ !request('status') ? 'ring-2 ring-indigo-300' : '' }}">
      <div class="text-xs text-gray-500">ทั้งหมด</div>
      <div class="text-2xl font-bold text-slate-800">{{ $stats['total'] }}</div>
    </a>
    <a href="?status=open" class="bg-white border border-gray-100 rounded-2xl p-4 hover:border-amber-200 transition {{ request('status') === 'open' ? 'ring-2 ring-amber-300' : '' }}">
      <div class="text-xs text-gray-500">เปิดอยู่</div>
      <div class="text-2xl font-bold text-amber-600">{{ $stats['open'] }}</div>
    </a>
    <a href="?status=resolved" class="bg-white border border-gray-100 rounded-2xl p-4 hover:border-emerald-200 transition {{ request('status') === 'resolved' ? 'ring-2 ring-emerald-300' : '' }}">
      <div class="text-xs text-gray-500">แก้ไขแล้ว</div>
      <div class="text-2xl font-bold text-emerald-600">{{ $stats['resolved'] }}</div>
    </a>
  </div>

  {{-- Tickets --}}
  <div class="space-y-3">
    @forelse($tickets as $t)
    <a href="{{ route('support.show', $t->ticket_number) }}" class="block bg-white border border-gray-100 rounded-2xl p-5 hover:border-indigo-200 hover:shadow-md transition">
      <div class="flex items-start justify-between gap-3 mb-2">
        <div class="flex-1 min-w-0">
          <div class="flex flex-wrap items-center gap-2 mb-1">
            <span class="font-mono text-xs text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">{{ $t->ticket_number }}</span>
            <span class="text-xs px-2 py-0.5 bg-{{ $t->priority_color }}-100 text-{{ $t->priority_color }}-700 rounded font-medium">{{ $t->priority_label }}</span>
            <span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-700 rounded">{{ $t->category_label }}</span>

            @switch($t->status)
              @case('new')<span class="text-xs px-2 py-0.5 bg-blue-100 text-blue-700 rounded font-medium">ใหม่</span>@break
              @case('open')<span class="text-xs px-2 py-0.5 bg-amber-100 text-amber-700 rounded font-medium">เปิดอยู่</span>@break
              @case('in_progress')<span class="text-xs px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded font-medium">กำลังดำเนินการ</span>@break
              @case('waiting')<span class="text-xs px-2 py-0.5 bg-purple-100 text-purple-700 rounded font-medium">รอตอบกลับ</span>@break
              @case('resolved')<span class="text-xs px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded font-medium">แก้ไขแล้ว</span>@break
              @case('closed')<span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-700 rounded font-medium">ปิด</span>@break
            @endswitch
          </div>
          <h3 class="font-semibold text-slate-800 truncate">{{ $t->subject }}</h3>
          <p class="text-sm text-gray-600 mt-1 line-clamp-2">{{ Str::limit($t->message, 150) }}</p>
        </div>
        <i class="bi bi-chevron-right text-gray-400 shrink-0"></i>
      </div>

      <div class="flex items-center justify-between text-xs text-gray-500 mt-3 pt-3 border-t border-gray-50">
        <div class="flex items-center gap-3">
          @if($t->reply_count > 0)
            <span><i class="bi bi-chat-left-dots"></i> {{ $t->reply_count }} ข้อความ</span>
          @endif
          @if($t->satisfaction_rating)
            <span class="text-amber-500">
              @for($i=1;$i<=5;$i++)<i class="bi bi-star{{ $i <= $t->satisfaction_rating ? '-fill' : '' }}"></i>@endfor
            </span>
          @endif
        </div>
        <span>{{ ($t->last_activity_at ?? $t->created_at)->diffForHumans() }}</span>
      </div>
    </a>
    @empty
    <div class="bg-white border border-gray-100 rounded-2xl p-12 text-center">
      <i class="bi bi-inbox text-4xl text-gray-300"></i>
      <p class="text-gray-500 mt-2 mb-3">ยังไม่มี tickets</p>
      <a href="{{ route('contact') }}" class="inline-block px-4 py-2 bg-indigo-500 text-white rounded-xl text-sm">สร้าง Ticket แรก</a>
    </div>
    @endforelse
  </div>

  @if($tickets->hasPages())
  <div class="flex justify-center mt-6">{{ $tickets->links() }}</div>
  @endif
</div>
@endsection
