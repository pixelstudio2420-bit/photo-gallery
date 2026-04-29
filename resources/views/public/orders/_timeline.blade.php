{{-- Order timeline component --}}
{{-- Required: $order (Order model) --}}

@php
  $service = app(\App\Services\OrderTimelineService::class);
  $events = $service->getTimeline($order);
@endphp

@if(!empty($events))
<div class="bg-white border border-gray-100 rounded-2xl p-5">
  <h3 class="font-semibold text-slate-800 mb-4">
    <i class="bi bi-clock-history text-indigo-500 mr-1"></i>ประวัติสถานะ
  </h3>

  <div class="relative">
    <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200"></div>

    <div class="space-y-4">
      @foreach($events as $event)
      <div class="relative flex items-start gap-4 pl-1">
        <div class="relative z-10 w-8 h-8 rounded-full flex items-center justify-center shrink-0
                    bg-{{ $event['color'] ?? 'gray' }}-100 text-{{ $event['color'] ?? 'gray' }}-600 border-4 border-white">
          <i class="bi bi-{{ $event['icon'] ?? 'circle' }} text-sm"></i>
        </div>
        <div class="flex-1 min-w-0 pb-2">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="font-medium text-slate-800 text-sm">{{ $event['description'] ?? $event['status'] ?? 'Event' }}</span>
            @if(!empty($event['source']))
              <span class="text-[10px] px-1.5 py-0.5 bg-gray-100 text-gray-600 rounded">{{ $event['source'] }}</span>
            @endif
          </div>
          <div class="text-xs text-gray-500 mt-0.5">
            @if(!empty($event['actor_name']))
              {{ $event['actor_name'] }} ·
            @endif
            {{ $event['human_time'] ?? ($event['created_at'] ?? '') }}
          </div>
        </div>
      </div>
      @endforeach
    </div>
  </div>
</div>
@endif
