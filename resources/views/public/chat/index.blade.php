@extends('layouts.app')

@section('title', 'แชท')

@section('content')
<div class="max-w-3xl mx-auto" x-data="chatInbox()" x-init="init()">

  {{-- Header --}}
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold text-slate-800">
      <i class="bi bi-chat-dots text-indigo-500 mr-2"></i>
      {{ (Auth::user()->photographerProfile?->status === 'approved') ? 'แชท' : 'แชทกับช่างภาพ' }}
      <span x-show="totalUnread > 0"
            x-text="totalUnread > 99 ? '99+' : totalUnread"
            class="ml-2 inline-flex items-center justify-center w-6 h-6 bg-red-500 text-white text-xs rounded-full"></span>
    </h1>

    <div class="flex gap-2">
      <button @click="toggleArchived()"
              :class="showArchived ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-700'"
              class="px-3 py-2 rounded-lg text-sm font-medium">
        <i class="bi bi-archive"></i>
        <span x-text="showArchived ? 'แสดงปกติ' : 'เก็บเข้าคลัง'"></span>
      </button>
    </div>
  </div>

  {{-- Search --}}
  <div class="mb-4 relative">
    <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
    <input type="text" x-model="searchQuery" @input.debounce.300ms="doSearch()"
           placeholder="ค้นหาข้อความ..."
           class="w-full pl-10 pr-3 py-2.5 border border-gray-200 rounded-xl bg-white focus:ring-2 focus:ring-indigo-200">
  </div>

  {{-- Search Results --}}
  <template x-if="searchResults.length > 0">
    <div class="bg-white border border-gray-100 rounded-2xl p-4 mb-4">
      <h3 class="text-sm font-semibold mb-2 text-gray-700">ผลการค้นหา (<span x-text="searchResults.length"></span>)</h3>
      <div class="space-y-2">
        <template x-for="r in searchResults" :key="r.id">
          <a :href="'/chat/' + r.conversation_id" class="block p-2 rounded-lg hover:bg-indigo-50 transition">
            <div class="flex items-center gap-2 text-sm">
              <i class="bi bi-chat-text text-indigo-500"></i>
              <span class="font-medium" x-text="r.other_party?.name"></span>
              <span class="text-xs text-gray-400" x-text="new Date(r.created_at).toLocaleDateString('th-TH')"></span>
            </div>
            <p class="text-sm text-gray-600 mt-1 line-clamp-1" x-text="r.preview"></p>
          </a>
        </template>
      </div>
    </div>
  </template>

  {{-- Conversation List --}}
  <div x-show="!loading" class="space-y-2">
    <template x-for="c in conversations" :key="c.id">
      <a :href="'/chat/' + c.id" class="block bg-white border border-gray-100 rounded-2xl p-4 hover:border-indigo-200 hover:shadow-sm transition">
        <div class="flex items-start gap-3">
          <div class="w-12 h-12 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 text-white flex items-center justify-center font-semibold shrink-0">
            <span x-text="(c.other_party?.name || 'U').charAt(0).toUpperCase()"></span>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-2">
              <h3 class="font-semibold text-slate-800 truncate" x-text="c.other_party?.name"></h3>
              <span class="text-xs text-gray-400 shrink-0" x-text="formatTime(c.latest_message_at)"></span>
            </div>
            <div class="flex items-center gap-2 mt-1">
              <p class="text-sm text-gray-600 flex-1 truncate" x-text="c.latest_message || 'ยังไม่มีข้อความ'"></p>
              <template x-if="c.unread > 0">
                <span class="bg-indigo-500 text-white text-xs font-semibold rounded-full px-2 py-0.5 shrink-0"
                      x-text="c.unread > 99 ? '99+' : c.unread"></span>
              </template>
            </div>
          </div>
        </div>
      </a>
    </template>

    <div x-show="conversations.length === 0" class="bg-white border border-gray-100 rounded-2xl p-12 text-center">
      <i class="bi bi-chat-dots text-4xl text-gray-300"></i>
      <p class="text-gray-500 mt-3" x-text="showArchived ? 'ไม่มีการสนทนาที่เก็บเข้าคลัง' : 'ยังไม่มีการสนทนา'"></p>
    </div>
  </div>

  <div x-show="loading" class="text-center py-8">
    <i class="bi bi-arrow-repeat animate-spin text-3xl text-gray-300"></i>
  </div>
</div>

@push('scripts')
<script>
function chatInbox() {
  return {
    conversations: [],
    searchResults: [],
    searchQuery: '',
    loading: true,
    showArchived: false,
    totalUnread: 0,
    pollInterval: null,

    init() {
      this.loadConversations();
      this.pollInterval = setInterval(() => this.loadConversations(false), 10000);
    },

    async loadConversations(showLoader = true) {
      if (showLoader) this.loading = true;
      try {
        const url = '/api/chat/conversations' + (this.showArchived ? '?archived=1' : '');
        const res = await fetch(url, { credentials: 'include' });
        const data = await res.json();
        if (data.success) {
          this.conversations = data.conversations;
          this.totalUnread = data.total_unread;
        }
      } catch (e) {}
      this.loading = false;
    },

    async doSearch() {
      if (this.searchQuery.length < 2) {
        this.searchResults = [];
        return;
      }
      try {
        const res = await fetch(`/api/chat/search?q=${encodeURIComponent(this.searchQuery)}`, { credentials: 'include' });
        const data = await res.json();
        if (data.success) this.searchResults = data.results;
      } catch (e) {}
    },

    toggleArchived() {
      this.showArchived = !this.showArchived;
      this.loadConversations();
    },

    formatTime(dt) {
      if (!dt) return '';
      const date = new Date(dt);
      const now = new Date();
      const diff = now - date;
      if (diff < 86400000) return date.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
      if (diff < 604800000) return Math.floor(diff / 86400000) + ' วันก่อน';
      return date.toLocaleDateString('th-TH');
    },
  };
}
</script>
@endpush
@endsection
