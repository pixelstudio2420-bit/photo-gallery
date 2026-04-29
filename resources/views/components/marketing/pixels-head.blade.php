{{-- Renders all enabled pixel base scripts — include in <head> --}}
{{-- Zero output if marketing_enabled=0 --}}
@php($__pixel = app(\App\Services\Marketing\PixelService::class))
{!! $__pixel->renderHead() !!}
