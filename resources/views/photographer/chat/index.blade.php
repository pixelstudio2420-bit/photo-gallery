@extends('layouts.photographer')

@section('title', 'แชท')

@section('content')
@include('photographer.partials.page-hero', [
  'icon'     => 'bi-chat-dots',
  'eyebrow'  => 'การทำงาน',
  'title'    => 'แชท',
  'subtitle' => 'สนทนากับลูกค้าและตอบคำถามเกี่ยวกับอีเวนต์',
])

@forelse($conversations as $conversation)
<a href="{{ route('photographer.chat.show', $conversation) }}" class="no-underline block">
  <div class="pg-card mb-3 transition hover:-translate-y-0.5 hover:shadow-md">
    <div class="p-4">
      <div class="flex justify-between items-start">
        <div class="flex items-center">
          <div class="flex items-center justify-center mr-3 w-12 h-12 rounded-full" style="background:rgba(37,99,235,0.08);">
            <i class="bi bi-person text-xl text-indigo-600"></i>
          </div>
          <div>
            <h6 class="font-semibold mb-1 text-gray-800">{{ $conversation->user->first_name ?? 'ผู้ใช้' }} {{ $conversation->user->last_name ?? '' }}</h6>
            <p class="text-gray-500 text-sm mb-0">{{ Str::limit($conversation->latestMessage->message ?? 'ยังไม่มีข้อความ', 60) }}</p>
          </div>
        </div>
        <span class="text-gray-500 text-xs">
          {{ $conversation->latestMessage ? $conversation->latestMessage->created_at->diffForHumans() : '' }}
        </span>
      </div>
    </div>
  </div>
</a>
@empty
<div class="pg-card">
  <div class="p-12 text-center">
    <i class="bi bi-chat-dots text-4xl text-indigo-600 opacity-30"></i>
    <p class="text-gray-500 mt-3">ยังไม่มีการสนทนา</p>
  </div>
</div>
@endforelse
@endsection
