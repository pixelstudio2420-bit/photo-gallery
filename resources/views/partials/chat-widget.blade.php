{{-- AI Chat Widget --}}
<div x-data="chatWidget()" x-cloak class="fixed bottom-6 right-6 z-50" id="ai-chat-widget">
    {{-- Chat Window --}}
    <div x-show="open" x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 scale-95"
         class="mb-4 w-[380px] max-w-[calc(100vw-2rem)] bg-white rounded-2xl shadow-2xl border border-gray-200 flex flex-col overflow-hidden"
         style="height: 500px; max-height: calc(100vh - 120px);">

        {{-- Header --}}
        <div class="bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-4 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-white/20 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-white font-semibold text-sm leading-tight">AI Chat Assistant</h3>
                    <p class="text-indigo-200 text-xs">ยินดีให้บริการครับ</p>
                </div>
            </div>
            <button @click="open = false" class="text-white/80 hover:text-white transition-colors p-1 rounded-lg hover:bg-white/10">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Messages Area --}}
        <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4" x-ref="chatMessages"
             style="scroll-behavior: smooth;">
            {{-- Welcome message --}}
            <template x-if="messages.length === 0">
                <div class="text-center py-6">
                    <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                    </div>
                    <p class="text-gray-600 text-sm font-medium mb-1">สวัสดีครับ!</p>
                    <p class="text-gray-400 text-xs">ถามอะไรได้เลยครับ เช่น ค้นหาอีเวนต์ หรือสอบถามราคา</p>
                    <div class="mt-4 flex flex-wrap gap-2 justify-center">
                        <button @click="sendQuickMessage('ค้นหาอีเวนต์')" class="text-xs bg-indigo-50 text-indigo-600 px-3 py-1.5 rounded-full hover:bg-indigo-100 transition-colors">ค้นหาอีเวนต์</button>
                        <button @click="sendQuickMessage('ราคารูปภาพ')" class="text-xs bg-indigo-50 text-indigo-600 px-3 py-1.5 rounded-full hover:bg-indigo-100 transition-colors">ราคารูปภาพ</button>
                        <button @click="sendQuickMessage('วิธีซื้อรูปภาพ')" class="text-xs bg-indigo-50 text-indigo-600 px-3 py-1.5 rounded-full hover:bg-indigo-100 transition-colors">วิธีซื้อ</button>
                        <button @click="sendQuickMessage('ติดต่อเรา')" class="text-xs bg-indigo-50 text-indigo-600 px-3 py-1.5 rounded-full hover:bg-indigo-100 transition-colors">ติดต่อเรา</button>
                    </div>
                </div>
            </template>

            {{-- Message Bubbles --}}
            <template x-for="(msg, index) in messages" :key="index">
                <div>
                    {{-- User message --}}
                    <template x-if="msg.sender === 'user'">
                        <div class="flex justify-end">
                            <div class="bg-indigo-600 text-white px-4 py-2.5 rounded-2xl rounded-br-md max-w-[80%] text-sm leading-relaxed whitespace-pre-line" x-text="msg.text"></div>
                        </div>
                    </template>

                    {{-- Bot text message --}}
                    <template x-if="msg.sender === 'bot' && msg.type !== 'events'">
                        <div class="flex justify-start">
                            <div class="flex gap-2 max-w-[85%]">
                                <div class="w-7 h-7 bg-gradient-to-br from-indigo-500 to-violet-500 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                                    </svg>
                                </div>
                                <div class="bg-gray-100 text-gray-800 px-4 py-2.5 rounded-2xl rounded-bl-md text-sm leading-relaxed whitespace-pre-line" x-text="msg.text"></div>
                            </div>
                        </div>
                    </template>

                    {{-- Bot events list --}}
                    <template x-if="msg.sender === 'bot' && msg.type === 'events'">
                        <div class="flex justify-start">
                            <div class="flex gap-2 max-w-[90%]">
                                <div class="w-7 h-7 bg-gradient-to-br from-indigo-500 to-violet-500 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                                    </svg>
                                </div>
                                <div class="space-y-2 flex-1">
                                    <div class="bg-gray-100 text-gray-800 px-4 py-2.5 rounded-2xl rounded-bl-md text-sm" x-text="msg.text"></div>
                                    <template x-for="(ev, ei) in (msg.data || [])" :key="ei">
                                        <a :href="'/events/' + ev.slug" class="block bg-white border border-gray-200 rounded-xl p-3 hover:border-indigo-300 hover:shadow-md transition-all group">
                                            <div class="font-medium text-sm text-gray-900 group-hover:text-indigo-600 transition-colors" x-text="ev.name"></div>
                                            <div class="flex items-center gap-3 mt-1.5 text-xs text-gray-500">
                                                <span x-show="ev.date" class="flex items-center gap-1">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                    <span x-text="ev.date"></span>
                                                </span>
                                                <span x-show="ev.location" class="flex items-center gap-1">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                    <span x-text="ev.location"></span>
                                                </span>
                                            </div>
                                            <div class="mt-1.5">
                                                <span x-show="ev.price > 0" class="text-xs font-semibold text-indigo-600" x-text="'฿' + Number(ev.price).toLocaleString() + ' /รูป'"></span>
                                                <span x-show="!ev.price || ev.price == 0" class="text-xs font-semibold text-green-600">ฟรี</span>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Loading indicator --}}
            <template x-if="loading">
                <div class="flex justify-start">
                    <div class="flex gap-2">
                        <div class="w-7 h-7 bg-gradient-to-br from-indigo-500 to-violet-500 rounded-full flex items-center justify-center flex-shrink-0">
                            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                            </svg>
                        </div>
                        <div class="bg-gray-100 px-4 py-3 rounded-2xl rounded-bl-md">
                            <div class="flex items-center gap-1.5">
                                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Input Area --}}
        <div class="border-t border-gray-200 px-4 py-3 bg-gray-50 flex-shrink-0">
            <form @submit.prevent="sendMessage" class="flex items-center gap-2">
                <input
                    x-model="inputMessage"
                    x-ref="chatInput"
                    type="text"
                    maxlength="500"
                    placeholder="พิมพ์ข้อความที่นี่..."
                    class="flex-1 bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent placeholder-gray-400"
                    :disabled="loading"
                >
                <button
                    type="submit"
                    :disabled="loading || !inputMessage.trim()"
                    class="bg-gradient-to-r from-indigo-600 to-violet-600 text-white p-2.5 rounded-xl hover:from-indigo-700 hover:to-violet-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all flex-shrink-0"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>

    {{-- Floating Chat Button --}}
    <button
        @click="toggle()"
        class="w-14 h-14 bg-gradient-to-r from-indigo-600 to-violet-600 rounded-full shadow-lg hover:shadow-xl flex items-center justify-center transition-all duration-300 hover:scale-105 group ml-auto"
        :class="{ 'ring-4 ring-indigo-300/50': !open && !hasInteracted }"
    >
        {{-- Chat icon (shown when closed) --}}
        <svg x-show="!open" class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
        </svg>
        {{-- Close icon (shown when open) --}}
        <svg x-show="open" class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>

    {{-- Pulse animation for uninteracted state --}}
    <template x-if="!hasInteracted && !open">
        <span class="absolute bottom-0 right-0 w-14 h-14 rounded-full bg-indigo-600 animate-ping opacity-20 pointer-events-none"></span>
    </template>
</div>

<script>
function chatWidget() {
    return {
        open: false,
        loading: false,
        hasInteracted: false,
        inputMessage: '',
        messages: [],

        toggle() {
            this.open = !this.open;
            this.hasInteracted = true;
            if (this.open) {
                this.$nextTick(() => {
                    this.$refs.chatInput?.focus();
                });
            }
        },

        sendQuickMessage(text) {
            this.inputMessage = text;
            this.sendMessage();
        },

        async sendMessage() {
            const text = this.inputMessage.trim();
            if (!text || this.loading) return;

            // Add user message
            this.messages.push({ sender: 'user', text: text, type: 'text' });
            this.inputMessage = '';
            this.loading = true;
            this.scrollToBottom();

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const response = await fetch('{{ route("api.chatbot") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ message: text }),
                });

                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                const data = await response.json();
                this.messages.push({
                    sender: 'bot',
                    text: data.reply,
                    type: data.type || 'text',
                    data: data.data || null,
                });
            } catch (error) {
                this.messages.push({
                    sender: 'bot',
                    text: 'ขออภัยครับ เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง',
                    type: 'text',
                });
            } finally {
                this.loading = false;
                this.scrollToBottom();
            }
        },

        scrollToBottom() {
            this.$nextTick(() => {
                const container = this.$refs.chatMessages;
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        },
    };
}
</script>

<style>
[x-cloak] { display: none !important; }
</style>
