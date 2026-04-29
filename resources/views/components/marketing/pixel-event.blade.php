{{--
  Fires a marketing event across all enabled pixels.

  Usage:
    <x-marketing.pixel-event name="Purchase" :data="[
      'value'    => 500,
      'currency' => 'THB',
      'order_id' => $order->id,
      'contents' => [['id' => 'pkg-1', 'quantity' => 1, 'item_price' => 500]],
    ]" />
--}}
@props(['name' => 'PageView', 'data' => []])

@php($__pixel = app(\App\Services\Marketing\PixelService::class))
{!! $__pixel->renderEvent($name, $data) !!}
