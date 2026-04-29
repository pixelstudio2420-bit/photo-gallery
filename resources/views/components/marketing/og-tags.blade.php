{{--
  Renders Open Graph + Twitter Card meta tags.

  Usage:
    <x-marketing.og-tags :data="[
      'title'       => $event->name,
      'description' => $event->description,
      'image'       => $event->cover_url,
      'url'         => url()->current(),
      'type'        => 'event',
    ]" />
--}}
@props(['data' => []])

@php($__og = app(\App\Services\Marketing\OgService::class))
{!! $__og->render($data) !!}
