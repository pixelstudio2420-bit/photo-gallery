@extends('layouts.photographer')

@section('title', 'แชท')

@section('content')
@include('photographer.partials.page-hero', [
  'icon'     => 'bi-chat-dots',
  'eyebrow'  => 'การทำงาน',
  'title'    => 'การสนทนา',
  'subtitle' => 'พูดคุยกับลูกค้าและตอบคำถามเกี่ยวกับอีเวนต์',
  'actions'  => '<a href="'.route('photographer.chat.index').'" class="pg-btn-ghost"><i class="bi bi-arrow-left"></i> กลับ</a>',
])

<div class="pg-card overflow-hidden">
  {{-- Chat Header --}}
  <div class="px-5 py-4 border-b border-gray-200">
    <div class="flex items-center">
      <div class="flex items-center justify-center mr-3 w-10 h-10 rounded-full" style="background:rgba(37,99,235,0.08);">
        <i class="bi bi-person text-indigo-600"></i>
      </div>
      <h6 class="font-semibold">{{ $conversation->user->first_name ?? 'ผู้ใช้' }} {{ $conversation->user->last_name ?? '' }}</h6>
    </div>
  </div>

  {{-- Chat Messages Area --}}
  <div class="p-5 min-h-[400px] max-h-[500px] overflow-y-auto bg-gray-50">
    <div class="text-center text-gray-500 py-12">
      <i class="bi bi-chat-dots text-3xl opacity-30"></i>
      <p class="mt-2">พื้นที่แชท - ข้อความจะแสดงที่นี่</p>
    </div>
  </div>

  {{-- Chat Input --}}
  <div class="px-5 py-4 border-t border-gray-200">
    <form class="flex gap-2">
      <input type="text" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="พิมพ์ข้อความ...">
      <button type="submit" class="bg-gradient-to-br from-indigo-600 to-indigo-700 text-white rounded-lg border-none px-5 py-2.5 transition hover:shadow-lg">
        <i class="bi bi-send"></i>
      </button>
    </form>
  </div>
</div>
@endsection
