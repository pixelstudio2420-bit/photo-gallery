@extends('layouts.admin')

@section('title', 'News Items')

@section('content')
<div class="space-y-5">

  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-800 dark:text-gray-100">
        <i class="bi bi-newspaper text-indigo-500 mr-2"></i>News Items
      </h1>
      <p class="text-sm text-gray-500 mt-1">ข่าวที่ดึงมาจาก RSS sources</p>
    </div>
    <a href="{{ route('admin.blog.news.index') }}" class="px-4 py-2 border border-gray-200 rounded-xl text-sm hover:bg-gray-50">
      <i class="bi bi-chevron-left"></i> กลับไปที่ News Sources
    </a>
  </div>

  {{-- Filters --}}
  <form method="GET" class="bg-white border border-gray-100 rounded-2xl p-4 grid grid-cols-1 md:grid-cols-5 gap-3">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="ค้นหา..." class="col-span-2 px-3 py-2 border border-gray-200 rounded-lg text-sm">
    <select name="status" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
      <option value="">ทุกสถานะ</option>
      <option value="fetched" {{ request('status') === 'fetched' ? 'selected' : '' }}>ดึงมาแล้ว</option>
      <option value="summarized" {{ request('status') === 'summarized' ? 'selected' : '' }}>สรุปด้วย AI แล้ว</option>
      <option value="published" {{ request('status') === 'published' ? 'selected' : '' }}>เผยแพร่แล้ว</option>
      <option value="dismissed" {{ request('status') === 'dismissed' ? 'selected' : '' }}>ยกเลิก</option>
    </select>
    <select name="source_id" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
      <option value="">ทุก source</option>
      @foreach($sources ?? [] as $src)
      <option value="{{ $src->id }}" {{ request('source_id') == $src->id ? 'selected' : '' }}>{{ $src->name }}</option>
      @endforeach
    </select>
    <button type="submit" class="px-4 py-2 bg-indigo-500 text-white rounded-lg text-sm">ค้นหา</button>
  </form>

  {{-- Bulk Actions Form --}}
  <form id="bulkNewsForm" method="POST" action="{{ route('admin.blog.news.items.bulk-action') }}">
    @csrf
    <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
      <div class="p-3 border-b border-gray-100 flex items-center gap-2 text-sm">
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" id="selectAllNews" class="rounded">
          <span class="text-gray-600">เลือกทั้งหมด</span>
        </label>
        <div class="flex gap-2 ml-auto">
          <button type="submit" name="action" value="summarize" class="px-3 py-1.5 bg-indigo-50 text-indigo-600 rounded-lg text-xs">
            <i class="bi bi-magic"></i> สรุปด้วย AI
          </button>
          <button type="submit" name="action" value="publish" class="px-3 py-1.5 bg-emerald-50 text-emerald-600 rounded-lg text-xs">
            <i class="bi bi-send"></i> เผยแพร่
          </button>
          <button type="submit" name="action" value="dismiss" class="px-3 py-1.5 bg-gray-100 text-gray-600 rounded-lg text-xs">
            <i class="bi bi-x"></i> ยกเลิก
          </button>
        </div>
      </div>

      <div class="divide-y divide-gray-100">
        @forelse($items ?? [] as $item)
        <div class="flex items-start gap-3 p-4 hover:bg-gray-50">
          <input type="checkbox" name="ids[]" value="{{ $item->id }}" class="mt-2 rounded news-checkbox">

          @if($item->image_url)
          <img src="{{ $item->image_url }}" alt="" class="w-20 h-20 rounded object-cover shrink-0" onerror="this.style.display='none'">
          @endif

          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              @php
                $c = match($item->status) { 'published' => 'emerald', 'summarized' => 'blue', 'dismissed' => 'gray', default => 'amber' };
              @endphp
              <span class="text-xs px-2 py-0.5 bg-{{ $c }}-100 text-{{ $c }}-700 rounded font-medium">{{ $item->status }}</span>
              <span class="text-xs text-gray-500">{{ $item->source->name ?? 'Unknown' }}</span>
              <span class="text-xs text-gray-400">· {{ $item->fetched_at?->diffForHumans() }}</span>
            </div>

            <h3 class="font-semibold text-slate-800 mb-1">
              <a href="{{ $item->url }}" target="_blank" class="hover:text-indigo-600">{{ $item->title }}</a>
            </h3>

            @if($item->ai_summary)
            <div class="mt-2 p-2 bg-blue-50 rounded text-xs text-blue-900">
              <i class="bi bi-robot text-blue-500"></i> <strong>AI Summary:</strong> {{ Str::limit($item->ai_summary, 200) }}
            </div>
            @elseif($item->original_content)
            <p class="text-sm text-gray-600 line-clamp-2">{{ Str::limit(strip_tags($item->original_content), 200) }}</p>
            @endif

            <div class="flex items-center gap-2 mt-2">
              <a href="{{ route('admin.blog.news.items.show', $item->id) }}" class="text-xs text-indigo-600 hover:underline">
                <i class="bi bi-eye"></i> ดูรายละเอียด
              </a>
              @if($item->status === 'fetched')
                <form method="POST" action="{{ route('admin.blog.news.items.summarize', $item->id) }}" class="inline">
                  @csrf
                  <button type="submit" class="text-xs text-blue-600 hover:underline"><i class="bi bi-magic"></i> สรุปด้วย AI</button>
                </form>
              @endif
              @if(in_array($item->status, ['fetched', 'summarized']))
                <form method="POST" action="{{ route('admin.blog.news.items.publish', $item->id) }}" class="inline">
                  @csrf
                  <button type="submit" class="text-xs text-emerald-600 hover:underline"><i class="bi bi-send"></i> เผยแพร่</button>
                </form>
                <form method="POST" action="{{ route('admin.blog.news.items.dismiss', $item->id) }}" class="inline">
                  @csrf
                  <button type="submit" class="text-xs text-gray-600 hover:underline"><i class="bi bi-x"></i> ยกเลิก</button>
                </form>
              @endif
            </div>
          </div>

          @if($item->relevance_score)
          <div class="text-right shrink-0">
            <div class="text-xs text-gray-500">Relevance</div>
            <div class="text-lg font-bold {{ $item->relevance_score >= 70 ? 'text-emerald-600' : ($item->relevance_score >= 40 ? 'text-amber-600' : 'text-gray-400') }}">
              {{ $item->relevance_score }}
            </div>
          </div>
          @endif
        </div>
        @empty
        <div class="p-12 text-center">
          <i class="bi bi-newspaper text-4xl text-gray-300"></i>
          <p class="text-gray-500 mt-2">ยังไม่มี news items</p>
          <p class="text-xs text-gray-400 mt-1">เพิ่ม News Source แล้วกด Fetch Now</p>
        </div>
        @endforelse
      </div>
    </div>
  </form>

  @if(isset($items) && method_exists($items, 'hasPages') && $items->hasPages())
  <div class="flex justify-center">{{ $items->links() }}</div>
  @endif
</div>

@push('scripts')
<script>
document.getElementById('selectAllNews')?.addEventListener('change', function() {
  document.querySelectorAll('.news-checkbox').forEach(cb => cb.checked = this.checked);
});
</script>
@endpush
@endsection
