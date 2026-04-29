@extends('layouts.admin')

@section('title', 'AI Task History')

@section('content')
<div class="space-y-5">

  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-800 dark:text-gray-100">
        <i class="bi bi-clock-history text-indigo-500 mr-2"></i>AI Task History
      </h1>
      <p class="text-sm text-gray-500 mt-1">ประวัติการใช้งาน AI tools ทั้งหมด</p>
    </div>
    <a href="{{ route('admin.blog.ai.cost') }}" class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm hover:bg-gray-50">
      <i class="bi bi-graph-up"></i> ดู Cost Report
    </a>
  </div>

  {{-- Filters --}}
  <form method="GET" class="bg-white border border-gray-100 rounded-2xl p-4 grid grid-cols-1 md:grid-cols-5 gap-3">
    <select name="type" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
      <option value="">ทุกประเภท</option>
      @foreach(['generate_article', 'summarize', 'rewrite', 'research', 'news_fetch', 'seo_analyze', 'keyword_suggest', 'translate'] as $t)
      <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>{{ $t }}</option>
      @endforeach
    </select>
    <select name="status" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
      <option value="">ทุกสถานะ</option>
      @foreach(['pending', 'processing', 'completed', 'failed'] as $s)
      <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ $s }}</option>
      @endforeach
    </select>
    <select name="provider" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
      <option value="">ทุก provider</option>
      <option value="openai" {{ request('provider') === 'openai' ? 'selected' : '' }}>OpenAI</option>
      <option value="claude" {{ request('provider') === 'claude' ? 'selected' : '' }}>Claude</option>
      <option value="gemini" {{ request('provider') === 'gemini' ? 'selected' : '' }}>Gemini</option>
    </select>
    <input type="date" name="date_from" value="{{ request('date_from') }}" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
    <button type="submit" class="px-4 py-2 bg-indigo-500 text-white rounded-lg text-sm">ค้นหา</button>
  </form>

  {{-- Table --}}
  <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-left p-3 text-xs uppercase text-gray-600">ID</th>
            <th class="text-left p-3 text-xs uppercase text-gray-600">Type</th>
            <th class="text-left p-3 text-xs uppercase text-gray-600">Title/Prompt</th>
            <th class="text-left p-3 text-xs uppercase text-gray-600">Provider</th>
            <th class="text-right p-3 text-xs uppercase text-gray-600">Tokens</th>
            <th class="text-right p-3 text-xs uppercase text-gray-600">Cost</th>
            <th class="text-center p-3 text-xs uppercase text-gray-600">Status</th>
            <th class="text-left p-3 text-xs uppercase text-gray-600">Created</th>
            <th class="text-center p-3 text-xs uppercase text-gray-600"></th>
          </tr>
        </thead>
        <tbody>
          @forelse($tasks ?? [] as $task)
          <tr class="border-t border-gray-50 hover:bg-gray-50">
            <td class="p-3 font-mono text-xs text-gray-500">#{{ $task->id }}</td>
            <td class="p-3"><span class="text-xs px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded font-medium">{{ $task->type }}</span></td>
            <td class="p-3">
              <div class="text-sm font-medium truncate max-w-xs">{{ $task->title ?? Str::limit($task->prompt, 50) }}</div>
            </td>
            <td class="p-3 text-xs">{{ $task->provider }} · <span class="text-gray-500">{{ $task->model }}</span></td>
            <td class="p-3 text-right text-xs">
              <span class="text-blue-600">{{ number_format($task->tokens_input ?? 0) }}</span> /
              <span class="text-emerald-600">{{ number_format($task->tokens_output ?? 0) }}</span>
            </td>
            <td class="p-3 text-right font-semibold">${{ number_format($task->cost_usd ?? 0, 4) }}</td>
            <td class="p-3 text-center">
              @php
                $c = match($task->status) { 'completed' => 'emerald', 'processing' => 'blue', 'pending' => 'amber', 'failed' => 'red', default => 'gray' };
              @endphp
              <span class="text-xs px-2 py-0.5 bg-{{ $c }}-100 text-{{ $c }}-700 rounded font-medium">{{ $task->status }}</span>
            </td>
            <td class="p-3 text-xs text-gray-500">{{ $task->created_at?->diffForHumans() }}</td>
            <td class="p-3 text-center">
              <a href="{{ route('admin.blog.ai.history.show', $task->id) }}" class="text-indigo-600 hover:underline text-xs">ดู</a>
            </td>
          </tr>
          @empty
          <tr><td colspan="9" class="p-12 text-center text-gray-500"><i class="bi bi-inbox text-3xl"></i><p class="mt-2">ยังไม่มี tasks</p></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @if(isset($tasks) && method_exists($tasks, 'hasPages') && $tasks->hasPages())
  <div class="flex justify-center">{{ $tasks->links() }}</div>
  @endif
</div>
@endsection
