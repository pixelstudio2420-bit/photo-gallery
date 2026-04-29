@props(['url' => request()->url(), 'title' => config('app.name'), 'description' => '', 'image' => ''])

@php
$shares = \App\Services\Social\SocialShareService::getShareUrls($url, $title, $description, $image);
@endphp

<div class="relative inline-block" x-data="{ open: false }">
  <button @click="open = !open" @click.outside="open = false"
      class="inline-flex items-center gap-1 px-3 py-1.5 text-sm border border-gray-300 text-gray-600 rounded-full hover:bg-gray-50 transition"
      type="button">
    <i class="bi bi-share mr-1"></i>แชร์
  </button>
  <div x-show="open" x-transition
     class="absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-lg border border-gray-100 z-50 py-1"
     style="display: none;">
    <a class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition" href="{{ $shares['facebook'] }}" target="_blank" rel="noopener">
      <i class="bi bi-facebook" style="color:#1877F2;font-size:1.1rem;"></i> Facebook
    </a>
    <a class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition" href="{{ $shares['twitter'] }}" target="_blank" rel="noopener">
      <i class="bi bi-twitter-x" style="color:#000;font-size:1.1rem;"></i> X (Twitter)
    </a>
    <a class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition" href="{{ $shares['line'] }}" target="_blank" rel="noopener">
      <i class="bi bi-chat-dots-fill" style="color:#06C755;font-size:1.1rem;"></i> LINE
    </a>
    @if($shares['pinterest'])
    <a class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition" href="{{ $shares['pinterest'] }}" target="_blank" rel="noopener">
      <i class="bi bi-pinterest" style="color:#E60023;font-size:1.1rem;"></i> Pinterest
    </a>
    @endif
    <hr class="my-1 border-gray-200">
    <a class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition" href="{{ $shares['email'] }}">
      <i class="bi bi-envelope" style="color:#6366f1;font-size:1.1rem;"></i> อีเมล
    </a>
    <button class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition w-full text-left" onclick="navigator.clipboard.writeText('{{ $url }}').then(()=>{this.querySelector('span').textContent='คัดลอกแล้ว!';setTimeout(()=>this.querySelector('span').textContent='คัดลอกลิงก์',1500)})">
      <i class="bi bi-link-45deg" style="color:#6366f1;font-size:1.1rem;"></i> <span>คัดลอกลิงก์</span>
    </button>
  </div>
</div>
