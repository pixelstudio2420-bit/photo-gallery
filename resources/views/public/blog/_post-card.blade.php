<article class="blog-card-wrap" itemscope itemtype="https://schema.org/BlogPosting">
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10 rounded-2xl overflow-hidden h-full flex flex-col blog-card transition-all duration-300 group shadow-sm hover:shadow-xl hover:-translate-y-1">
    {{-- Featured Image --}}
    <a href="{{ route('blog.show', $post->slug) }}" class="relative block overflow-hidden aspect-[16/10]">
      @if($post->featured_image)
        <img src="{{ asset('storage/' . $post->featured_image) }}"
             alt="{{ $post->title }}"
             class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
             loading="lazy"
             itemprop="image">
      @else
        <div class="w-full h-full bg-gradient-to-br from-indigo-100 to-violet-100 dark:from-indigo-900/40 dark:to-violet-900/40 flex items-center justify-center">
          <i class="bi bi-newspaper text-4xl text-indigo-300 dark:text-indigo-500"></i>
        </div>
      @endif
      {{-- Gradient overlay --}}
      <div class="absolute inset-0 bg-gradient-to-t from-black/45 via-transparent to-transparent opacity-90 group-hover:opacity-100 transition-opacity"></div>

      {{-- Category Badge --}}
      @if($post->category)
        <span class="absolute top-0 left-0 m-3 inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold text-white z-10 shadow-lg backdrop-blur-sm"
              @if($post->category->color)
                style="background-color: {{ $post->category->color }};"
              @else
                style="background: linear-gradient(135deg, #6366f1, #8b5cf6);"
              @endif
              >
          @if($post->category->icon)<i class="{{ $post->category->icon }} mr-1"></i>@endif
          {{ $post->category->name }}
        </span>
      @endif

      {{-- Reading Time Badge --}}
      @if($post->reading_time)
        <span class="absolute top-0 right-0 m-3 inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium text-white bg-black/40 backdrop-blur-md z-10 shadow-md border border-white/10">
          <i class="bi bi-clock"></i> {{ $post->reading_time }} นาที
        </span>
      @endif
    </a>

    {{-- Content --}}
    <div class="p-5 flex-1 flex flex-col">
      <h3 class="font-bold text-base sm:text-lg leading-snug mb-2 line-clamp-2 text-slate-800 dark:text-gray-100" itemprop="headline">
        <a href="{{ route('blog.show', $post->slug) }}" class="group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition-colors">
          {{ $post->title }}
        </a>
      </h3>

      @if($post->excerpt)
        <p class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed line-clamp-3 mb-4 flex-1" itemprop="description">
          {{ $post->excerpt }}
        </p>
      @endif

      {{-- Bottom Meta --}}
      <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mt-auto pt-4 border-t border-gray-100 dark:border-white/10">
        <div class="flex items-center gap-2 min-w-0">
          @if($post->author)
            <div class="w-7 h-7 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 flex items-center justify-center text-white text-xs font-bold shrink-0 shadow-sm">
              {{ mb_substr($post->author->first_name ?? 'A', 0, 1) }}
            </div>
            <span class="truncate font-medium text-gray-700 dark:text-gray-300" itemprop="author" itemscope itemtype="https://schema.org/Person">
              <span itemprop="name">{{ $post->author->full_name ?? $post->author->first_name ?? 'Admin' }}</span>
            </span>
          @endif
        </div>
        <div class="flex items-center gap-3 shrink-0">
          @if($post->published_at)
            <time datetime="{{ $post->published_at->toIso8601String() }}" itemprop="datePublished" class="flex items-center gap-1">
              <i class="bi bi-calendar3"></i>
              {{ $post->published_at->locale('th')->translatedFormat('j M Y') }}
            </time>
          @endif
          @if($post->view_count > 0)
            <span class="flex items-center gap-1">
              <i class="bi bi-eye"></i> {{ number_format($post->view_count) }}
            </span>
          @endif
        </div>
      </div>
    </div>
  </div>
  <meta itemprop="mainEntityOfPage" content="{{ route('blog.show', $post->slug) }}">
</article>
