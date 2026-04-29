{{-- Shared form partial for Landing Page create/edit --}}
@php
    $page = $page ?? null;
    $initial = $page ? [
        'title' => $page->title,
        'slug' => $page->slug,
        'subtitle' => $page->subtitle,
        'hero_image' => $page->hero_image,
        'theme' => $page->theme,
        'cta_label' => $page->cta_label,
        'cta_url' => $page->cta_url,
        'status' => $page->status,
        'sections' => $page->sections ?? [],
        'seo_title' => data_get($page->seo, 'title'),
        'seo_desc'  => data_get($page->seo, 'description'),
        'seo_og'    => data_get($page->seo, 'og_image'),
    ] : [
        'title'=>'','slug'=>'','subtitle'=>'','hero_image'=>'','theme'=>'indigo',
        'cta_label'=>'','cta_url'=>'','status'=>'draft','sections'=>[],
        'seo_title'=>'','seo_desc'=>'','seo_og'=>'',
    ];
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Left: main fields --}}
    <div class="lg:col-span-2 space-y-4">

        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4">เนื้อหาหลัก</h3>

            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Title <span class="text-rose-500">*</span></label>
                    <input type="text" name="title" value="{{ old('title', $initial['title']) }}" required maxlength="160"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Slug (URL)</label>
                        <div class="flex items-center">
                            <span class="px-3 py-2 text-xs font-mono bg-slate-100 dark:bg-slate-800 border border-r-0 border-slate-300 dark:border-slate-700 rounded-l-lg text-slate-500">/lp/</span>
                            <input type="text" name="slug" value="{{ old('slug', $initial['slug']) }}" maxlength="120" pattern="[a-z0-9\-]+"
                                   placeholder="auto-generate from title"
                                   class="flex-1 px-3 py-2 rounded-r-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm font-mono">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Theme</label>
                        <select name="theme" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                            @foreach($themes as $key => $t)
                                <option value="{{ $key }}" @selected(old('theme', $initial['theme']) === $key)>{{ ucfirst($key) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Subtitle</label>
                    <input type="text" name="subtitle" value="{{ old('subtitle', $initial['subtitle']) }}" maxlength="300"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Hero Image URL</label>
                    <input type="text" name="hero_image" value="{{ old('hero_image', $initial['hero_image']) }}" maxlength="500"
                           placeholder="https://example.com/hero.jpg"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">CTA Label</label>
                        <input type="text" name="cta_label" value="{{ old('cta_label', $initial['cta_label']) }}" maxlength="80"
                               placeholder="สั่งซื้อเลย"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">CTA URL</label>
                        <input type="url" name="cta_url" value="{{ old('cta_url', $initial['cta_url']) }}" maxlength="500"
                               placeholder="https://..."
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                    </div>
                </div>
            </div>
        </div>

        {{-- Sections builder --}}
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5" x-data="lpSectionsBuilder()">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Sections (blocks)</h3>
                <div class="flex items-center gap-2">
                    <select x-model="addType" class="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                        @foreach($blockTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="button" @click="addBlock()" class="px-3 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs">
                        <i class="bi bi-plus-lg"></i> เพิ่ม
                    </button>
                </div>
            </div>

            <template x-if="blocks.length === 0">
                <div class="text-center py-8 text-sm text-slate-500">
                    ยังไม่มี block — เลือกด้านบนแล้วกด "เพิ่ม"
                </div>
            </template>

            <div class="space-y-3">
                <template x-for="(block, idx) in blocks" :key="block._id">
                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 p-3">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-xs font-bold uppercase text-indigo-500" x-text="blockLabel(block.type)"></div>
                            <div class="flex items-center gap-1">
                                <button type="button" @click="moveUp(idx)" :disabled="idx===0" class="text-slate-500 hover:text-indigo-500 disabled:opacity-30"><i class="bi bi-arrow-up"></i></button>
                                <button type="button" @click="moveDown(idx)" :disabled="idx===blocks.length-1" class="text-slate-500 hover:text-indigo-500 disabled:opacity-30"><i class="bi bi-arrow-down"></i></button>
                                <button type="button" @click="removeBlock(idx)" class="text-rose-500 hover:text-rose-400 ml-2"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>

                        <input type="hidden" :name="`sections[${idx}][type]`" :value="block.type">

                        {{-- heading --}}
                        <template x-if="block.type === 'heading'">
                            <div class="space-y-2">
                                <input type="text" :name="`sections[${idx}][data][heading]`" x-model="block.data.heading" placeholder="Heading" class="w-full px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                                <input type="text" :name="`sections[${idx}][data][sub]`" x-model="block.data.sub" placeholder="Subheading" class="w-full px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                            </div>
                        </template>

                        {{-- text --}}
                        <template x-if="block.type === 'text'">
                            <textarea :name="`sections[${idx}][data][body]`" x-model="block.data.body" rows="4" placeholder="**Markdown** supported" class="w-full px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm font-mono"></textarea>
                        </template>

                        {{-- image --}}
                        <template x-if="block.type === 'image'">
                            <div class="space-y-2">
                                <input type="text" :name="`sections[${idx}][data][src]`" x-model="block.data.src" placeholder="Image URL" class="w-full px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                                <input type="text" :name="`sections[${idx}][data][alt]`" x-model="block.data.alt" placeholder="Alt text" class="w-full px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                                <input type="text" :name="`sections[${idx}][data][caption]`" x-model="block.data.caption" placeholder="Caption (optional)" class="w-full px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                            </div>
                        </template>

                        {{-- features --}}
                        <template x-if="block.type === 'features'">
                            <div class="space-y-2">
                                <div class="text-xs text-slate-500">แต่ละแถว: icon | title | body (ใช้ | คั่น)</div>
                                <textarea :name="`sections[${idx}][data][raw]`" x-model="block.data.raw" rows="5" placeholder="bi-shield-check | ปลอดภัย | ข้อมูลของคุณถูกเข้ารหัส&#10;bi-lightning | เร็ว | ส่งสินค้าภายใน 1 วัน" class="w-full px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm font-mono"></textarea>
                            </div>
                        </template>

                        {{-- testimonial --}}
                        <template x-if="block.type === 'testimonial'">
                            <div class="space-y-2">
                                <textarea :name="`sections[${idx}][data][quote]`" x-model="block.data.quote" rows="2" placeholder="&quot;คำชม&quot;" class="w-full px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm"></textarea>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" :name="`sections[${idx}][data][author]`" x-model="block.data.author" placeholder="Author" class="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                                    <input type="text" :name="`sections[${idx}][data][role]`" x-model="block.data.role" placeholder="Role / Company" class="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                                </div>
                            </div>
                        </template>

                        {{-- faq --}}
                        <template x-if="block.type === 'faq'">
                            <div class="space-y-2">
                                <div class="text-xs text-slate-500">แต่ละแถว: Q || A (ใช้ || คั่น), 1 บรรทัดต่อคำถาม</div>
                                <textarea :name="`sections[${idx}][data][raw]`" x-model="block.data.raw" rows="6" placeholder="ส่งสินค้ากี่วัน? || ภายใน 1-3 วันทำการ&#10;มีเก็บเงินปลายทางไหม? || มี" class="w-full px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm font-mono"></textarea>
                            </div>
                        </template>

                        {{-- cta --}}
                        <template x-if="block.type === 'cta'">
                            <div class="space-y-2">
                                <input type="text" :name="`sections[${idx}][data][label]`" x-model="block.data.label" placeholder="Button label" class="w-full px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                                <input type="url" :name="`sections[${idx}][data][url]`" x-model="block.data.url" placeholder="https://..." class="w-full px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                                <input type="text" :name="`sections[${idx}][data][note]`" x-model="block.data.note" placeholder="Small note (optional)" class="w-full px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- SEO --}}
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4"><i class="bi bi-search text-emerald-500"></i> SEO</h3>
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">SEO Title</label>
                    <input type="text" name="seo_title" value="{{ old('seo_title', $initial['seo_title']) }}" maxlength="160"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Meta Description</label>
                    <textarea name="seo_desc" rows="2" maxlength="500"
                              class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">{{ old('seo_desc', $initial['seo_desc']) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Open Graph Image URL</label>
                    <input type="text" name="seo_og" value="{{ old('seo_og', $initial['seo_og']) }}" maxlength="500"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                </div>
            </div>
        </div>
    </div>

    {{-- Right: status sidebar --}}
    <div class="space-y-4">
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Publish</h3>
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Status</label>
                    <select name="status" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                        @foreach(['draft','published','archived'] as $s)
                            <option value="{{ $s }}" @selected(old('status', $initial['status']) === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold">
                    <i class="bi bi-save"></i> บันทึก
                </button>
                @if($page && $page->status === 'published')
                    <a href="{{ $page->publicUrl() }}" target="_blank" class="block text-center px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-700 text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                        <i class="bi bi-box-arrow-up-right"></i> เปิด Live
                    </a>
                @endif
            </div>
        </div>

        @if($page)
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">สถิติ</h3>
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">Views</span><span class="font-mono">{{ number_format($page->views) }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Conversions</span><span class="font-mono">{{ number_format($page->conversions) }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Conv. Rate</span><span class="font-mono text-pink-500">{{ $page->conversionRate() }}%</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Created</span><span class="text-xs">{{ $page->created_at?->diffForHumans() }}</span></div>
                </div>
            </div>
        @endif
    </div>
</div>

<script>
function lpSectionsBuilder() {
    const initial = @json(old('sections', $initial['sections']));
    return {
        addType: 'heading',
        blocks: (Array.isArray(initial) ? initial : []).map((b, i) => ({
            _id: Math.random().toString(36).slice(2),
            type: b.type || 'text',
            data: b.data || {},
        })),
        blockLabel(t) {
            return ({!! json_encode($blockTypes) !!}[t]) || t;
        },
        addBlock() {
            this.blocks.push({
                _id: Math.random().toString(36).slice(2),
                type: this.addType,
                data: {},
            });
        },
        removeBlock(i) { this.blocks.splice(i, 1); },
        moveUp(i) {
            if (i === 0) return;
            const tmp = this.blocks[i-1];
            this.blocks[i-1] = this.blocks[i];
            this.blocks[i] = tmp;
        },
        moveDown(i) {
            if (i === this.blocks.length - 1) return;
            const tmp = this.blocks[i+1];
            this.blocks[i+1] = this.blocks[i];
            this.blocks[i] = tmp;
        },
    };
}
</script>
