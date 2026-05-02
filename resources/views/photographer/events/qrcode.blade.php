@extends('layouts.photographer')

@section('title', 'QR Code — ' . $event->name)

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/qr-card.css') }}">
@endpush

@section('content')
<div class="no-print">
  @include('photographer.partials.page-hero', [
    'icon'     => 'bi-qr-code',
    'eyebrow'  => 'การทำงาน',
    'title'    => 'QR Code อีเวนต์',
    'subtitle' => 'การ์ดสวยพร้อมแบรนด์ — แชร์/พิมพ์/ติดที่งานได้เลย',
    'actions'  => '<a href="'.route('photographer.events.index').'" class="pg-btn-ghost"><i class="bi bi-arrow-left"></i> กลับ</a>',
  ])
</div>

@php
  // Inputs for the shared QR card partial. The branded /qr/branded
  // endpoint already bakes the site logo + "loadroop.com" caption
  // into the QR image itself; the surrounding card adds the visual
  // wrapper (event name, gradient header, footer ribbon).
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
  // Filename slug used when the user clicks "บันทึกการ์ด".
  // We pre-compute on the server because Str::slug handles Thai better
  // than a JS regex would, and we already have $event in PHP scope.
  window.QR_FILE_SLUG = @json(\Illuminate\Support\Str::slug($event->name) ?: 'event-' . $event->id);
</script>
@include('partials.qr-card-script')
@endpush

@endsection
