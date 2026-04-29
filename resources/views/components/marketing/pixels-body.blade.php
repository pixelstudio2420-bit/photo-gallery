{{-- Renders noscript fallback tags for GTM + FB — include right after <body> --}}
@php($__pixel = app(\App\Services\Marketing\PixelService::class))
{!! $__pixel->renderBody() !!}
