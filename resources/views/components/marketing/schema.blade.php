{{--
  Renders one or more Schema.org JSON-LD blocks.

  Usage:
    <x-marketing.schema :schemas="[
      app(App\Services\Marketing\SchemaService::class)->organization(),
      app(App\Services\Marketing\SchemaService::class)->event($event),
    ]" />

  Or via convenience helpers:
    @php($s = app(App\Services\Marketing\SchemaService::class))
    <x-marketing.schema :schemas="[$s->website(), $s->breadcrumb($crumbs)]" />
--}}
@props(['schemas' => []])

@php($__sch = app(\App\Services\Marketing\SchemaService::class))
{!! $__sch->render($schemas) !!}
