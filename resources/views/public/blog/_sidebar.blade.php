{{-- ============ Blog Sidebar ============ --}}
<aside class="space-y-6">

  {{-- Popular Posts --}}
  @if(isset($popularPosts) && $popularPosts->count())
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10 rounded-2xl p-6 shadow-sm">
    <h3 class="font-bold text-base text-slate-800 dark:text-gray-100 mb-5 flex items-center gap-2">
      <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center shadow-md shadow-amber-500/25">
        <i class="bi bi-fire text-white text-sm"></i>
      </span>
      บทความยอดนิยม
    </h3>
    <div class="space-y-4">
      @foreach($popularPosts as $i => $popPost)
      <a href="{{ route('blog.show', $popPost->slug) }}" class="flex items-start gap-3 group">
        <div class="relative w-16 h-16 rounded-xl overflow-hidden shrink-0 shadow-sm">
          @if($popPost->featured_image)
            <img src="{{ asset('storage/' . $popPost->featured_image) }}" alt="{{ $popPost->title }}" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110" loading="lazy">
          @else
            <div class="w-full h-full bg-gradient-to-br from-indigo-100 to-violet-100 dark:from-indigo-900/40 dark:to-violet-900/40 flex items-center justify-center">
              <i class="bi bi-newspaper text-indigo-300 dark:text-indigo-500"></i>
            </div>
          @endif
          <div class="absolute top-0 left-0 m-1 w-5 h-5 rounded-md bg-gradient-to-br from-indigo-500 to-violet-600 text-white text-xs font-bold flex items-center justify-center shadow-md">
            {{ $i + 1 }}
          </div>
        </div>
        <div class="flex-1 min-w-0">
          <h4 class="text-sm font-semibold text-slate-800 dark:text-gray-200 line-clamp-2 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition-colors leading-snug">{{ $popPost->title }}</h4>
          <span class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1 mt-1">
            <i class="bi bi-eye"></i> {{ number_format($popPost->view_count) }}
          </span>
        </div>
      </a>
      @endforeach
    </div>
  </div>
  @endif

  {{-- Categories --}}
  @if(isset($categories) && $categories->count())
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10 rounded-2xl p-6 shadow-sm">
    <h3 class="font-bold text-base text-slate-800 dark:text-gray-100 mb-5 flex items-center gap-2">
      <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-md shadow-indigo-500/25">
        <i class="bi bi-folder text-white text-sm"></i>
      </span>
      หมวดหมู่
    </h3>
    <ul class="space-y-1">
      @foreach($categories as $cat)
      <li>
        <a href="{{ route('blog.category', $cat->slug) }}"
           class="flex items-center justify-between px-3 py-2 rounded-xl text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-white/5 hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors group">
          <span class="flex items-center gap-2">
            @if($cat->icon)<i class="{{ $cat->icon }} text-gray-400 dark:text-gray-500 group-hover:text-indigo-500 dark:group-hover:text-indigo-300 transition-colors"></i>@else<i class="bi bi-folder2 text-gray-400 dark:text-gray-500 group-hover:text-indigo-500 dark:group-hover:text-indigo-300 transition-colors"></i>@endif
            {{ $cat->name }}
          </span>
          <span class="text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-white/5 px-2 py-0.5 rounded-full group-hover:bg-indigo-100 dark:group-hover:bg-indigo-500/20 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition-colors">
            {{ $cat->post_count ?? $cat->posts_count ?? 0 }}
          </span>
        </a>
      </li>
      @endforeach
    </ul>
  </div>
  @endif

  {{-- Tag Cloud --}}
  @if(isset($tags) && $tags->count())
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10 rounded-2xl p-6 shadow-sm">
    <h3 class="font-bold text-base text-slate-800 dark:text-gray-100 mb-5 flex items-center gap-2">
      <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center shadow-md shadow-cyan-500/25">
        <i class="bi bi-tags text-white text-sm"></i>
      </span>
      แท็กยอดนิยม
    </h3>
    <div class="flex flex-wrap gap-2">
      @foreach($tags as $tag)
      <a href="{{ route('blog.tag', $tag->slug) }}"
         class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium bg-gray-50 dark:bg-white/5 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-white/10 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 hover:text-indigo-600 dark:hover:text-indigo-300 hover:border-indigo-200 dark:hover:border-indigo-400/30 hover:scale-105 transition-all">
        <i class="bi bi-hash"></i>{{ $tag->name }}
        @if($tag->post_count > 0)
          <span class="text-gray-400 dark:text-gray-500">({{ $tag->post_count }})</span>
        @endif
      </a>
      @endforeach
    </div>
  </div>
  @endif

  {{-- Affiliate CTA Banner --}}
  @if(isset($sidebarCta) && $sidebarCta)
  <div class="bg-gradient-to-br from-indigo-600 via-violet-600 to-purple-700 rounded-2xl p-6 text-white relative overflow-hidden shadow-xl shadow-indigo-500/20">
    <div class="absolute inset-0 pointer-events-none" style="background:radial-gradient(circle at 80% 20%,rgba(255,255,255,0.1) 0%,transparent 50%)"></div>
    <div class="relative">
      @if($sidebarCta->affiliateLink && $sidebarCta->affiliateLink->image)
        <img src="{{ asset('storage/' . $sidebarCta->affiliateLink->image) }}" alt="{{ $sidebarCta->name }}" class="w-full rounded-xl mb-3 shadow-lg">
      @endif
      <h4 class="font-bold text-base mb-2">{{ $sidebarCta->label ?? 'ข้อเสนอพิเศษ' }}</h4>
      @if($sidebarCta->sub_label)
        <p class="text-white/80 text-sm mb-4">{{ $sidebarCta->sub_label }}</p>
      @endif
      <a href="{{ $sidebarCta->url ?? ($sidebarCta->affiliateLink ? $sidebarCta->affiliateLink->getCloakedUrl() : '#') }}"
         rel="nofollow noopener sponsored"
         target="_blank"
         class="block w-full text-center py-3 px-4 bg-white text-indigo-700 rounded-xl font-bold text-sm hover:bg-gray-50 hover:scale-[1.02] transition-all shadow-lg"
         data-cta-id="{{ $sidebarCta->id }}"
         onclick="trackCtaClick({{ $sidebarCta->id }})">
        {{ $sidebarCta->icon ?? '' }} คลิกดูรายละเอียด <i class="bi bi-arrow-right"></i>
      </a>
      <p class="text-white/50 text-xs mt-2 text-center">* ลิงก์ affiliate</p>
    </div>
  </div>
  @endif

  {{-- RSS / Subscribe --}}
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10 rounded-2xl p-6 shadow-sm">
    <h3 class="font-bold text-base text-slate-800 dark:text-gray-100 mb-3 flex items-center gap-2">
      <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center shadow-md shadow-orange-500/25">
        <i class="bi bi-rss text-white text-sm"></i>
      </span>
      ติดตามบทความ
    </h3>
    <p class="text-gray-600 dark:text-gray-400 text-xs mb-3">รับข่าวสารบทความใหม่ผ่าน RSS Feed</p>
    <a href="{{ route('blog.feed') }}"
       target="_blank"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white bg-gradient-to-br from-orange-500 to-red-500 hover:shadow-lg hover:shadow-orange-500/30 transition-all">
      <i class="bi bi-rss"></i> RSS Feed
    </a>
  </div>

</aside>
