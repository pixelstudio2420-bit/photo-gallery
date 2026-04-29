@extends('layouts.app')

@section('title', 'แชท')

@section('content')
@php
  $viewerId = auth()->id();
  $other = $conversation->otherParty($viewerId);
@endphp

<div class="max-w-3xl mx-auto" x-data="chatRoom({{ $conversation->id }})" x-init="init()">

  {{-- Header --}}
  <div class="bg-white border border-gray-100 rounded-2xl mb-3">
    <div class="px-4 py-3 flex items-center gap-3 border-b border-gray-100">
      <a href="{{ route('chat.index') }}" class="text-gray-400 hover:text-gray-700">
        <i class="bi bi-chevron-left"></i>
      </a>
      <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 text-white flex items-center justify-center font-semibold shrink-0">
        {{ mb_strtoupper(mb_substr($other['name'] ?? 'U', 0, 1, 'UTF-8'), 'UTF-8') }}
      </div>
      <div class="flex-1 min-w-0">
        <h3 class="font-semibold text-slate-800 truncate">{{ $other['name'] }}</h3>
        <div class="text-xs text-gray-500">
          <span x-show="!otherTyping">{{ $other['role'] === 'photographer' ? 'ช่างภาพ' : 'ลูกค้า' }}</span>
          <span x-show="otherTyping" class="text-indigo-500 animate-pulse">กำลังพิมพ์...</span>
        </div>
      </div>

      {{-- Menu --}}
      <div x-data="{ open: false }" class="relative">
        <button @click="open = !open" @click.outside="open = false" class="w-9 h-9 rounded-full hover:bg-gray-100 flex items-center justify-center">
          <i class="bi bi-three-dots-vertical"></i>
        </button>
        <div x-show="open" x-cloak class="absolute right-0 top-full mt-1 w-40 bg-white border border-gray-100 rounded-xl shadow-lg overflow-hidden z-10">
          <button @click="archive()" class="w-full px-4 py-2 text-left text-sm hover:bg-gray-50 flex items-center gap-2">
            <i class="bi bi-archive text-gray-500"></i> เก็บเข้าคลัง
          </button>
        </div>
      </div>
    </div>

    {{-- Messages area --}}
    <div id="chatMessages" class="p-4 min-h-[400px] max-h-[60vh] overflow-y-auto bg-gradient-to-b from-slate-50 to-white">

      <div x-show="loading && messages.length === 0" class="text-center py-8">
        <i class="bi bi-arrow-repeat animate-spin text-2xl text-gray-300"></i>
      </div>

      <template x-for="msg in messages" :key="msg.id">
        <div :class="msg.sender_id === {{ $viewerId }} ? 'flex justify-end' : 'flex justify-start'" class="mb-3">
          <div :class="msg.sender_id === {{ $viewerId }} ? 'bg-gradient-to-br from-indigo-500 to-indigo-600 text-white' : 'bg-white border border-gray-100'"
               class="max-w-[80%] rounded-2xl px-4 py-2.5 shadow-sm">

            {{-- Attachment: image --}}
            <template x-if="msg.message_type === 'image' && msg.attachment_url">
              <img :src="'/storage/' + msg.attachment_url" :alt="msg.attachment_name"
                   class="rounded-lg mb-1 max-w-full cursor-pointer"
                   @click="openImage('/storage/' + msg.attachment_url)">
            </template>

            {{-- Attachment: file --}}
            <template x-if="msg.message_type === 'file' && msg.attachment_url">
              <a :href="'/storage/' + msg.attachment_url" download
                 class="flex items-center gap-2 p-2 bg-white/20 rounded-lg mb-1 hover:bg-white/30">
                <i class="bi bi-file-earmark-fill text-xl"></i>
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium truncate" x-text="msg.attachment_name"></div>
                  <div class="text-[10px] opacity-80" x-text="formatSize(msg.attachment_size)"></div>
                </div>
                <i class="bi bi-download"></i>
              </a>
            </template>

            {{-- Text --}}
            <p class="text-sm whitespace-pre-wrap break-words" x-text="msg.message"></p>

            {{-- Meta --}}
            <div class="flex items-center justify-end gap-1 text-[10px] mt-1"
                 :class="msg.sender_id === {{ $viewerId }} ? 'text-indigo-100' : 'text-gray-400'">
              <span x-text="formatTime(msg.created_at)"></span>
              <template x-if="msg.sender_id === {{ $viewerId }}">
                <i :class="msg.is_read ? 'bi-check2-all' : 'bi-check2'" class="bi"></i>
              </template>
            </div>
          </div>
        </div>
      </template>

      {{-- Typing Indicator --}}
      <div x-show="otherTyping" x-cloak class="flex justify-start mb-3">
        <div class="bg-gray-100 rounded-2xl px-4 py-3">
          <div class="flex gap-1">
            <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0s"></span>
            <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.15s"></span>
            <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.3s"></span>
          </div>
        </div>
      </div>

      <div x-show="messages.length === 0 && !loading" class="text-center py-8 text-gray-400">
        <i class="bi bi-chat-dots text-3xl"></i>
        <p class="mt-2 text-sm">ยังไม่มีข้อความ — เริ่มแชทเลย!</p>
      </div>
    </div>

    {{-- Input --}}
    <div class="p-3 border-t border-gray-100 bg-white rounded-b-2xl">
      <form @submit.prevent="sendMessage()" class="flex items-center gap-2">
        <label class="cursor-pointer w-10 h-10 rounded-full hover:bg-gray-100 flex items-center justify-center text-gray-500">
          <i class="bi bi-paperclip text-xl"></i>
          <input type="file" class="hidden" @change="uploadFile($event)" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt">
        </label>

        <input type="text" x-model="newMessage" @input="handleTyping()" @keydown.enter.prevent="sendMessage()"
               placeholder="พิมพ์ข้อความ..." maxlength="5000"
               :disabled="sending"
               class="flex-1 px-4 py-2.5 border border-gray-200 rounded-full text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400">

        <button type="submit" :disabled="sending || (!newMessage.trim() && !selectedFile)"
                :class="sending || (!newMessage.trim() && !selectedFile) ? 'opacity-40' : ''"
                class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-indigo-600 text-white flex items-center justify-center hover:shadow-lg">
          <i class="bi bi-send-fill"></i>
        </button>
      </form>

      <template x-if="selectedFile">
        <div class="mt-2 px-3 py-2 bg-indigo-50 rounded-lg flex items-center gap-2 text-sm">
          <i class="bi bi-file-earmark text-indigo-500"></i>
          <span class="flex-1 truncate" x-text="selectedFile.name"></span>
          <button @click="selectedFile = null" class="text-red-500"><i class="bi bi-x-lg"></i></button>
        </div>
      </template>
    </div>
  </div>

  {{-- Image Modal --}}
  <div x-show="imagePreview" x-cloak @click="imagePreview = null"
       class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4">
    <img :src="imagePreview" class="max-w-full max-h-[90vh] rounded-lg">
  </div>
</div>

@push('scripts')
<script>
function chatRoom(convId) {
  return {
    conversationId: convId,
    messages: [],
    loading: false,
    sending: false,
    newMessage: '',
    selectedFile: null,
    otherTyping: false,
    lastTimestamp: null,
    pollInterval: null,
    typingTimeout: null,
    imagePreview: null,

    init() {
      this.loadMessages();
      this.pollInterval = setInterval(() => this.pollUpdates(), 3000);
    },

    async loadMessages() {
      this.loading = true;
      try {
        const res = await fetch(`/api/chat/${this.conversationId}/messages`, { credentials: 'include' });
        const data = await res.json();
        if (data.success) {
          this.messages = data.messages;
          this.otherTyping = data.typing;
          this.lastTimestamp = data.timestamp;
          this.$nextTick(() => this.scrollToBottom());
        }
      } catch (e) {}
      this.loading = false;
    },

    async pollUpdates() {
      if (!this.lastTimestamp) return;
      try {
        const res = await fetch(`/api/chat/${this.conversationId}/messages?since=${encodeURIComponent(this.lastTimestamp)}`, { credentials: 'include' });
        const data = await res.json();
        if (data.success) {
          if (data.messages.length > 0) {
            this.messages.push(...data.messages);
            this.$nextTick(() => this.scrollToBottom());
          }
          this.otherTyping = data.typing;
          this.lastTimestamp = data.timestamp;
        }
      } catch (e) {}
    },

    async sendMessage() {
      if (this.sending) return;
      if (!this.newMessage.trim() && !this.selectedFile) return;

      this.sending = true;
      const formData = new FormData();
      formData.append('message', this.newMessage);
      if (this.selectedFile) formData.append('attachment', this.selectedFile);

      const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

      try {
        const res = await fetch(`/api/chat/${this.conversationId}/send`, {
          method: 'POST',
          credentials: 'include',
          headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
          body: formData,
        });
        const data = await res.json();
        if (data.success) {
          this.messages.push(data.message);
          this.newMessage = '';
          this.selectedFile = null;
          this.$nextTick(() => this.scrollToBottom());
        } else {
          alert(data.error || 'ส่งไม่สำเร็จ');
        }
      } catch (e) {
        alert('เกิดข้อผิดพลาด');
      }
      this.sending = false;
    },

    uploadFile(event) {
      const file = event.target.files[0];
      if (file && file.size <= 10485760) {
        this.selectedFile = file;
      } else if (file) {
        alert('ไฟล์ใหญ่เกิน 10MB');
        event.target.value = '';
      }
    },

    handleTyping() {
      // Send typing=true, then typing=false after 2 seconds of inactivity
      this.sendTypingStatus(true);
      clearTimeout(this.typingTimeout);
      this.typingTimeout = setTimeout(() => this.sendTypingStatus(false), 2000);
    },

    async sendTypingStatus(isTyping) {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
      try {
        await fetch(`/api/chat/${this.conversationId}/typing`, {
          method: 'POST',
          credentials: 'include',
          headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ typing: isTyping }),
        });
      } catch (e) {}
    },

    async archive() {
      if (!confirm('เก็บการสนทนานี้เข้าคลัง?')) return;
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
      try {
        await fetch(`/api/chat/${this.conversationId}/archive`, {
          method: 'POST',
          credentials: 'include',
          headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
        window.location = '{{ route("chat.index") }}';
      } catch (e) {}
    },

    openImage(url) { this.imagePreview = url; },

    scrollToBottom() {
      const el = document.getElementById('chatMessages');
      if (el) el.scrollTop = el.scrollHeight;
    },

    formatTime(dt) {
      if (!dt) return '';
      return new Date(dt).toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
    },

    formatSize(bytes) {
      if (!bytes) return '';
      if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
      if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
      return bytes + ' B';
    },
  };
}
</script>
@endpush
@endsection
