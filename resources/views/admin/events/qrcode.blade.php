@extends('layouts.admin')

@section('title', 'QR Code - ' . $event->name)

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/qr-card.css') }}">
@endpush

@section('content')
<div class="flex justify-between items-center mb-4 no-print">
  <h4 class="font-bold mb-0 tracking-tight">
    <i class="bi bi-qr-code mr-2 text-indigo-500"></i>QR Code
  </h4>
  <a href="{{ route('admin.events.index') }}" class="bg-indigo-50 text-indigo-600 rounded-lg font-medium px-5 py-2 inline-flex items-center gap-1 transition hover:bg-indigo-100">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>

@php
  // Same QR pipeline as the photographer view — branded /qr/branded
  // endpoint with shared card layout. Admin sees the exact card the
  // photographer/customer would see, so they can verify branding.
  $eventUrl   = route('events.show', $event->slug ?: $event->id);
  $qrUrl      = route('qr.branded', ['data' => $eventUrl, 'size' => 320]);
  $qrFallback = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
    'size'   => '320x320',
    'data'   => $eventUrl,
    'ecc'    => 'M',
    'margin' => '10',
    'format' => 'png',
  ]);
@endphp

@include('partials.qr-card', [
  'event'      => $event,
  'eventUrl'   => $eventUrl,
  'qrUrl'      => $qrUrl,
  'qrFallback' => $qrFallback,
])

@push('scripts')
<script>
  window.QR_FILE_SLUG = @json(\Illuminate\Support\Str::slug($event->name) ?: 'event-' . $event->id);
</script>
@include('partials.qr-card-script')
@endpush
@endsection
