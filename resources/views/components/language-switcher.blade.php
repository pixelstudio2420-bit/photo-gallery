@php
  $locales = \App\Http\Controllers\Api\LanguageApiController::SUPPORTED;
  $current = app()->getLocale();
  $currentMeta = $locales[$current] ?? ['name' => 'ภาษาไทย', 'flag' => '🇹🇭', 'native' => 'ภาษาไทย'];
@endphp

<div x-data="{ open: false }" class="relative">
  <button @click="open = !open"
          @click.outside="open = false"
          type="button"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm text-gray-700 hover:bg-gray-100 transition-colors"
          aria-label="{{ __('common.select') }} Language">
    <span class="text-base leading-none">{{ $currentMeta['flag'] }}</span>
    <span class="hidden sm:inline font-medium">{{ $currentMeta['native'] }}</span>
    <i class="bi bi-chevron-down text-xs opacity-60 transition-transform" :class="open ? 'rotate-180' : ''"></i>
  </button>

  <div x-show="open"
       x-transition:enter="transition ease-out duration-150"
       x-transition:enter-start="opacity-0 translate-y-1"
       x-transition:enter-end="opacity-100 translate-y-0"
       x-transition:leave="transition ease-in duration-100"
       x-transition:leave-start="opacity-100 translate-y-0"
       x-transition:leave-end="opacity-0 translate-y-1"
       class="absolute right-0 top-full mt-1 w-44 bg-white border border-gray-100 rounded-xl shadow-lg overflow-hidden z-50"
       x-cloak>
    @foreach($locales as $code => $meta)
      <a href="{{ route('lang.switch', $code) }}?redirect={{ urlencode(request()->getRequestUri()) }}"
         class="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-indigo-50 transition-colors {{ $current === $code ? 'bg-indigo-50 text-indigo-600 font-semibold' : 'text-gray-700' }}">
        <span class="text-lg leading-none">{{ $meta['flag'] }}</span>
        <span class="flex-1">{{ $meta['native'] }}</span>
        @if($current === $code)
          <i class="bi bi-check-circle-fill text-indigo-600"></i>
        @endif
      </a>
    @endforeach
  </div>
</div>
