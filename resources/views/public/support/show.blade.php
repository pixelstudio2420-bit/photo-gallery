@extends('layouts.app')

@section('title', 'Ticket ' . $ticket->ticket_number)

@section('content')
<div class="max-w-3xl mx-auto space-y-4">

  {{-- Back --}}
  <a href="{{ route('support.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
    <i class="bi bi-chevron-left"></i> กลับไปรายการ
  </a>

  {{-- Header --}}
  <div class="bg-white border border-gray-100 rounded-2xl p-5">
    <div class="flex flex-wrap items-center gap-2 mb-2">
      <span class="font-mono text-sm text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">{{ $ticket->ticket_number }}</span>
      <span class="text-xs px-2 py-0.5 bg-{{ $ticket->priority_color }}-100 text-{{ $ticket->priority_color }}-700 rounded font-semibold">{{ $ticket->priority_label }}</span>
      <span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-700 rounded">{{ $ticket->category_label }}</span>

      @switch($ticket->status)
        @case('new')<span class="text-xs px-2 py-0.5 bg-blue-100 text-blue-700 rounded font-medium">ใหม่</span>@break
        @case('open')<span class="text-xs px-2 py-0.5 bg-amber-100 text-amber-700 rounded font-medium">เปิดอยู่</span>@break
        @case('in_progress')<span class="text-xs px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded font-medium">กำลังดำเนินการ</span>@break
        @case('waiting')<span class="text-xs px-2 py-0.5 bg-purple-100 text-purple-700 rounded font-medium">รอตอบกลับ</span>@break
        @case('resolved')<span class="text-xs px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded font-medium">แก้ไขแล้ว</span>@break
        @case('closed')<span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-700 rounded font-medium">ปิด</span>@break
      @endswitch
    </div>

    <h1 class="text-xl font-bold text-slate-800">{{ $ticket->subject }}</h1>
    <p class="text-xs text-gray-500 mt-1">สร้างเมื่อ {{ $ticket->created_at->format('d/m/Y H:i') }}</p>
  </div>

  {{-- Original Message --}}
  <div class="bg-white border border-gray-100 rounded-2xl p-5">
    <div class="flex items-center gap-3 mb-3">
      <div class="w-9 h-9 rounded-full bg-gradient-to-br from-slate-400 to-gray-500 text-white flex items-center justify-center font-semibold">
        {{ mb_strtoupper(mb_substr($ticket->name, 0, 1, 'UTF-8'), 'UTF-8') }}
      </div>
      <div>
        <div class="font-semibold text-sm text-slate-800">คุณ</div>
        <div class="text-xs text-gray-500">{{ $ticket->created_at->format('d/m/Y H:i') }}</div>
      </div>
    </div>
    <p class="text-gray-700 whitespace-pre-wrap">{{ $ticket->message }}</p>
  </div>

  {{-- Replies --}}
  @foreach($ticket->publicReplies as $reply)
    @if($reply->sender_type === 'admin')
      <div class="bg-gradient-to-br from-indigo-50 to-violet-50 border border-indigo-100 rounded-2xl p-5">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-violet-500 text-white flex items-center justify-center font-semibold text-sm">
            <i class="bi bi-headset"></i>
          </div>
          <div>
            <div class="flex items-center gap-2">
              <span class="font-semibold text-sm text-slate-800">{{ $reply->sender_name }}</span>
              <span class="text-[10px] px-2 py-0.5 bg-indigo-500 text-white rounded">ทีมงาน</span>
            </div>
            <div class="text-xs text-gray-500">{{ $reply->created_at->format('d/m/Y H:i') }}</div>
          </div>
        </div>
        <p class="text-gray-700 whitespace-pre-wrap">{{ $reply->message }}</p>
      </div>
    @else
      <div class="bg-white border border-gray-100 rounded-2xl p-5">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-9 h-9 rounded-full bg-gradient-to-br from-slate-400 to-gray-500 text-white flex items-center justify-center font-semibold text-sm">
            {{ mb_strtoupper(mb_substr($reply->sender_name, 0, 1, 'UTF-8'), 'UTF-8') }}
          </div>
          <div>
            <div class="font-semibold text-sm text-slate-800">คุณ</div>
            <div class="text-xs text-gray-500">{{ $reply->created_at->format('d/m/Y H:i') }}</div>
          </div>
        </div>
        <p class="text-gray-700 whitespace-pre-wrap">{{ $reply->message }}</p>
      </div>
    @endif
  @endforeach

  {{-- Reply Form (only if not closed) --}}
  @if($ticket->status !== 'closed')
  <div class="bg-white border border-gray-100 rounded-2xl p-5">
    <h3 class="font-semibold text-slate-800 mb-3">
      <i class="bi bi-reply text-indigo-500 mr-1"></i>ตอบกลับ
    </h3>
    <form method="POST" action="{{ route('support.reply', $ticket->ticket_number) }}">
      @csrf
      <textarea name="message" rows="5" required maxlength="5000" placeholder="พิมพ์ข้อความของคุณ..."
                class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-200"></textarea>
      <div class="flex justify-end mt-3">
        <button type="submit" class="px-6 py-2.5 bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-xl font-medium hover:shadow-lg">
          <i class="bi bi-send mr-1"></i>ส่ง
        </button>
      </div>
    </form>
  </div>
  @else
  <div class="bg-gray-50 border border-gray-200 rounded-2xl p-4 text-center text-gray-500 text-sm">
    <i class="bi bi-lock mr-1"></i>Ticket นี้ถูกปิดแล้ว หากต้องการสอบถามเพิ่ม กรุณาสร้าง ticket ใหม่
  </div>
  @endif

  {{-- Satisfaction Rating (only for resolved/closed without rating) --}}
  @if(in_array($ticket->status, ['resolved', 'closed']) && !$ticket->satisfaction_rating)
  <div class="bg-gradient-to-br from-amber-50 to-yellow-50 border border-amber-200 rounded-2xl p-5" x-data="{ rating: 0 }">
    <h3 class="font-semibold text-amber-800 mb-2">
      <i class="bi bi-star-fill mr-1"></i>ประเมินการบริการ
    </h3>
    <p class="text-sm text-amber-700 mb-3">ให้คะแนนบริการของเราเพื่อการพัฒนาต่อไป</p>

    <form method="POST" action="{{ route('support.rate', $ticket->ticket_number) }}">
      @csrf
      <div class="flex gap-1 mb-3">
        @for($i=1;$i<=5;$i++)
        <button type="button" @click="rating = {{ $i }}" class="text-3xl transition">
          <i class="bi bi-star{{ '-fill' }}" :class="rating >= {{ $i }} ? 'text-amber-400' : 'text-gray-300'"></i>
        </button>
        @endfor
      </div>
      <input type="hidden" name="rating" x-model="rating">
      <textarea name="comment" rows="2" maxlength="500" placeholder="ความคิดเห็นเพิ่มเติม (ไม่บังคับ)"
                class="w-full px-3 py-2 border border-amber-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-amber-200"></textarea>
      <button type="submit" :disabled="rating === 0" :class="rating === 0 ? 'opacity-50 cursor-not-allowed' : ''"
              class="mt-3 px-5 py-2 bg-amber-500 text-white rounded-lg text-sm font-medium hover:bg-amber-600">
        ส่งการประเมิน
      </button>
    </form>
  </div>
  @elseif($ticket->satisfaction_rating)
  <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 text-center">
    <div class="text-amber-500 text-2xl mb-1">
      @for($i=1;$i<=5;$i++)<i class="bi bi-star{{ $i <= $ticket->satisfaction_rating ? '-fill' : '' }}"></i>@endfor
    </div>
    <p class="text-sm text-emerald-700">ขอบคุณสำหรับการประเมิน!</p>
  </div>
  @endif
</div>
@endsection
