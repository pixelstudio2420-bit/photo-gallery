<?php $__env->startSection('title', 'จัดการข่าวสาร'); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .status-fetched { @apply bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400; }
    .status-summarized { @apply bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-400; }
    .status-published { @apply bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400; }
    .status-dismissed { @apply bg-gray-100 text-gray-600 dark:bg-gray-500/20 dark:text-gray-400; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div x-data="newsManager()" x-init="init()">

    
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white">
                <i class="bi bi-newspaper text-blue-500 mr-2"></i>จัดการข่าวสาร
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">รวบรวมข่าวสารจากแหล่งข่าวต่างๆ อัตโนมัติ</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" @click="fetchAllSources()"
                    :disabled="fetching"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium text-sm transition-colors shadow-lg shadow-blue-500/25 disabled:opacity-50">
                <i class="bi" :class="fetching ? 'bi-hourglass-split animate-spin' : 'bi-arrow-clockwise'"></i>
                <span x-text="fetching ? 'กำลังดึงข่าว...' : 'ดึงข่าวทั้งหมด'"></span>
            </button>
            <button type="button" @click="showAddSource = true"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-medium text-sm transition-colors shadow-lg shadow-indigo-500/25">
                <i class="bi bi-plus-lg"></i>เพิ่มแหล่งข่าว
            </button>
        </div>
    </div>

    
    <div class="flex items-center gap-1 mb-6 bg-white dark:bg-slate-800 rounded-xl p-1 border border-gray-100 dark:border-white/[0.06] w-fit">
        <button @click="activeTab = 'sources'"
                :class="activeTab === 'sources' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-400' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                class="px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="bi bi-rss mr-1"></i>แหล่งข่าว
        </button>
        <button @click="activeTab = 'items'"
                :class="activeTab === 'items' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-400' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                class="px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="bi bi-newspaper mr-1"></i>รายการข่าว
        </button>
    </div>

    
    <div x-show="activeTab === 'sources'">
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50/80 dark:bg-slate-700/50">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">แหล่งข่าว</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">URL</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">หมวดหมู่</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">สถานะ</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">ดึงล่าสุด</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">จำนวนข่าว</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                        <?php $__empty_1 = true; $__currentLoopData = $sources ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $source): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-slate-700/30 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center">
                                        <i class="bi bi-rss text-blue-500"></i>
                                    </div>
                                    <span class="font-semibold text-slate-800 dark:text-white"><?php echo e($source->name); ?></span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <a href="<?php echo e($source->url); ?>" target="_blank" class="text-xs text-gray-500 dark:text-gray-400 hover:text-indigo-500 truncate block max-w-[200px]">
                                    <?php echo e(Str::limit($source->url, 40)); ?>

                                    <i class="bi bi-box-arrow-up-right ml-1 text-[10px]"></i>
                                </a>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-xs text-gray-600 dark:text-gray-300"><?php echo e($source->category ?? '-'); ?></span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php if($source->is_active): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400">
                                        ใช้งาน
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-500/20 dark:text-gray-400">
                                        ปิดใช้งาน
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center text-xs text-gray-500 dark:text-gray-400">
                                <?php echo e($source->last_fetched_at ? $source->last_fetched_at->diffForHumans() : 'ยังไม่เคยดึง'); ?>

                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 text-sm font-bold">
                                    <?php echo e($source->items_count ?? 0); ?>

                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button @click="fetchSource(<?php echo e($source->id); ?>)" title="ดึงข่าวเลย"
                                            class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-500/10 text-blue-500 flex items-center justify-center hover:bg-blue-100 dark:hover:bg-blue-500/20 transition-colors">
                                        <i class="bi bi-arrow-clockwise text-xs"></i>
                                    </button>
                                    <button @click="editSource(<?php echo e($source->id); ?>)" title="แก้ไข"
                                            class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-500/10 text-amber-500 flex items-center justify-center hover:bg-amber-100 dark:hover:bg-amber-500/20 transition-colors">
                                        <i class="bi bi-pencil text-xs"></i>
                                    </button>
                                    <button @click="deleteSource(<?php echo e($source->id); ?>, '<?php echo e($source->name); ?>')" title="ลบ"
                                            class="w-7 h-7 rounded-lg bg-red-50 dark:bg-red-500/10 text-red-500 flex items-center justify-center hover:bg-red-100 dark:hover:bg-red-500/20 transition-colors">
                                        <i class="bi bi-trash text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mb-3">
                                        <i class="bi bi-rss text-2xl text-gray-400"></i>
                                    </div>
                                    <p class="text-gray-500 dark:text-gray-400 font-medium">ยังไม่มีแหล่งข่าว</p>
                                    <button @click="showAddSource = true"
                                            class="mt-3 inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-medium transition-colors">
                                        <i class="bi bi-plus-lg"></i>เพิ่มแหล่งข่าวแรก
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    
    <div x-show="activeTab === 'items'" x-cloak>
        
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] p-4 mb-4">
            <form method="GET" action="<?php echo e(route('admin.blog.news.index')); ?>" id="newsFilterForm">
                <input type="hidden" name="tab" value="items">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <select name="source" onchange="document.getElementById('newsFilterForm').submit()"
                            class="text-sm border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 py-2.5 px-3 dark:text-white">
                        <option value="">แหล่งข่าวทั้งหมด</option>
                        <?php $__currentLoopData = $sources ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $source): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($source->id); ?>" <?php echo e(request('source') == $source->id ? 'selected' : ''); ?>><?php echo e($source->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>

                    <select name="item_status" onchange="document.getElementById('newsFilterForm').submit()"
                            class="text-sm border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 py-2.5 px-3 dark:text-white">
                        <option value="">สถานะทั้งหมด</option>
                        <option value="fetched" <?php echo e(request('item_status') == 'fetched' ? 'selected' : ''); ?>>ดึงแล้ว</option>
                        <option value="summarized" <?php echo e(request('item_status') == 'summarized' ? 'selected' : ''); ?>>สรุปแล้ว</option>
                        <option value="published" <?php echo e(request('item_status') == 'published' ? 'selected' : ''); ?>>เผยแพร่แล้ว</option>
                        <option value="dismissed" <?php echo e(request('item_status') == 'dismissed' ? 'selected' : ''); ?>>ปิดแล้ว</option>
                    </select>

                    <select name="category" onchange="document.getElementById('newsFilterForm').submit()"
                            class="text-sm border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 py-2.5 px-3 dark:text-white">
                        <option value="">หมวดหมู่ทั้งหมด</option>
                        <?php $__currentLoopData = $categories ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($cat->id); ?>" <?php echo e(request('category') == $cat->id ? 'selected' : ''); ?>><?php echo e($cat->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>

                    <input type="date" name="date_from" value="<?php echo e(request('date_from')); ?>" onchange="document.getElementById('newsFilterForm').submit()"
                           placeholder="จากวันที่"
                           class="text-sm border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 py-2.5 px-3 dark:text-white">
                    <input type="date" name="date_to" value="<?php echo e(request('date_to')); ?>" onchange="document.getElementById('newsFilterForm').submit()"
                           placeholder="ถึงวันที่"
                           class="text-sm border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 py-2.5 px-3 dark:text-white">
                </div>
            </form>
        </div>

        
        <div class="flex items-center gap-3 mb-4" x-show="selectedItems.length > 0" x-cloak x-transition>
            <span class="text-sm text-gray-500 dark:text-gray-400">
                เลือก <strong class="text-indigo-600 dark:text-indigo-400" x-text="selectedItems.length"></strong> รายการ
            </span>
            <button @click="bulkAction('summarize')"
                    class="px-3 py-1.5 text-xs font-medium bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-400 rounded-lg hover:bg-indigo-200 transition-colors">
                <i class="bi bi-robot mr-1"></i>สรุป AI
            </button>
            <button @click="bulkAction('publish')"
                    class="px-3 py-1.5 text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400 rounded-lg hover:bg-emerald-200 transition-colors">
                <i class="bi bi-send mr-1"></i>เผยแพร่
            </button>
            <button @click="bulkAction('dismiss')"
                    class="px-3 py-1.5 text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-500/20 dark:text-gray-400 rounded-lg hover:bg-gray-200 transition-colors">
                <i class="bi bi-x-circle mr-1"></i>ปิด
            </button>
        </div>

        
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50/80 dark:bg-slate-700/50">
                            <th class="w-10 px-4 py-3">
                                <input type="checkbox" @change="toggleAllItems($event)"
                                       class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">หัวข้อ</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">แหล่งข่าว</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">สถานะ</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">สรุป</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">ความเกี่ยวข้อง</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">วันที่</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                        <?php $__empty_1 = true; $__currentLoopData = $newsItems ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-slate-700/30 transition-colors">
                            <td class="px-4 py-3">
                                <input type="checkbox" value="<?php echo e($item->id); ?>" x-model="selectedItems"
                                       class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            </td>
                            <td class="px-4 py-3">
                                <a href="<?php echo e($item->original_url); ?>" target="_blank"
                                   class="text-sm font-medium text-slate-800 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors line-clamp-2">
                                    <?php echo e($item->title); ?>

                                    <i class="bi bi-box-arrow-up-right text-[10px] ml-1 text-gray-400"></i>
                                </a>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-xs text-gray-600 dark:text-gray-300"><?php echo e($item->source->name ?? '-'); ?></span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php
                                    $statusClasses = [
                                        'fetched' => 'status-fetched',
                                        'summarized' => 'status-summarized',
                                        'published' => 'status-published',
                                        'dismissed' => 'status-dismissed',
                                    ];
                                    $statusLabels = [
                                        'fetched' => 'ดึงแล้ว',
                                        'summarized' => 'สรุปแล้ว',
                                        'published' => 'เผยแพร่',
                                        'dismissed' => 'ปิดแล้ว',
                                    ];
                                ?>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium <?php echo e($statusClasses[$item->status] ?? 'status-fetched'); ?>">
                                    <?php echo e($statusLabels[$item->status] ?? $item->status); ?>

                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <?php if($item->summary): ?>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2"><?php echo e(Str::limit($item->summary, 80)); ?></p>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php if(isset($item->relevance_score)): ?>
                                    <?php
                                        $relevanceColor = $item->relevance_score >= 70 ? 'text-emerald-600 dark:text-emerald-400' : ($item->relevance_score >= 40 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400');
                                    ?>
                                    <span class="text-xs font-bold <?php echo e($relevanceColor); ?>"><?php echo e($item->relevance_score); ?>%</span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center text-xs text-gray-500 dark:text-gray-400">
                                <?php echo e($item->published_date ? $item->published_date->format('d/m/Y') : ($item->created_at?->format('d/m/Y') ?? '-')); ?>

                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <?php if($item->status !== 'summarized' && $item->status !== 'published'): ?>
                                    <button @click="summarizeItem(<?php echo e($item->id); ?>)" title="สรุป AI"
                                            class="w-7 h-7 rounded-lg bg-violet-50 dark:bg-violet-500/10 text-violet-500 flex items-center justify-center hover:bg-violet-100 dark:hover:bg-violet-500/20 transition-colors">
                                        <i class="bi bi-robot text-xs"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if($item->status !== 'published'): ?>
                                    <button @click="publishItem(<?php echo e($item->id); ?>)" title="เผยแพร่เป็นบทความ"
                                            class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 text-emerald-500 flex items-center justify-center hover:bg-emerald-100 dark:hover:bg-emerald-500/20 transition-colors">
                                        <i class="bi bi-send text-xs"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if($item->status !== 'dismissed'): ?>
                                    <button @click="dismissItem(<?php echo e($item->id); ?>)" title="ปิด"
                                            class="w-7 h-7 rounded-lg bg-gray-100 dark:bg-slate-700 text-gray-500 flex items-center justify-center hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">
                                        <i class="bi bi-x-circle text-xs"></i>
                                    </button>
                                    <?php endif; ?>
                                    <a href="<?php echo e($item->original_url); ?>" target="_blank" title="ดูต้นฉบับ"
                                       class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-500/10 text-blue-500 flex items-center justify-center hover:bg-blue-100 dark:hover:bg-blue-500/20 transition-colors">
                                        <i class="bi bi-eye text-xs"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mb-3">
                                        <i class="bi bi-newspaper text-2xl text-gray-400"></i>
                                    </div>
                                    <p class="text-gray-500 dark:text-gray-400 font-medium">ยังไม่มีข่าวสาร</p>
                                    <p class="text-sm text-gray-400 mt-1">เพิ่มแหล่งข่าวและดึงข่าวสาร</p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if(isset($newsItems) && $newsItems instanceof \Illuminate\Pagination\LengthAwarePaginator && $newsItems->hasPages()): ?>
            <div class="px-4 py-3 border-t border-gray-100 dark:border-white/[0.06]">
                <?php echo e($newsItems->appends(request()->query())->links()); ?>

            </div>
            <?php endif; ?>
        </div>
    </div>

    
    <div x-show="showAddSource" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click.self="showAddSource = false">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

            <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06] flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="bi bi-rss text-blue-500"></i>
                    <span x-text="editingSourceId ? 'แก้ไขแหล่งข่าว' : 'เพิ่มแหล่งข่าว'"></span>
                </h3>
                <button @click="showAddSource = false; editingSourceId = null" class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-500 hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">
                    <i class="bi bi-x-lg text-sm"></i>
                </button>
            </div>

            <form :action="editingSourceId ? '<?php echo e(url('admin/blog/news/sources')); ?>/' + editingSourceId : '<?php echo e(route('admin.blog.news.sources.store')); ?>'"
                  method="POST" class="p-6 space-y-4">
                <?php echo csrf_field(); ?>
                <template x-if="editingSourceId">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">ชื่อแหล่งข่าว <span class="text-red-500">*</span></label>
                    <input type="text" name="name" x-model="sourceForm.name" required placeholder="เช่น TechCrunch"
                           class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">URL (RSS Feed) <span class="text-red-500">*</span></label>
                    <input type="url" name="url" x-model="sourceForm.url" required placeholder="https://example.com/feed"
                           class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">หมวดหมู่</label>
                    <input type="text" name="category" x-model="sourceForm.category" placeholder="เช่น เทคโนโลยี, การถ่ายภาพ"
                           class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" x-model="sourceForm.is_active"
                           class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm font-medium text-slate-700 dark:text-gray-200">เปิดใช้งาน</span>
                </label>

                <div class="flex items-center gap-3 pt-2">
                    <button type="button" @click="showAddSource = false; editingSourceId = null"
                            class="flex-1 px-4 py-2.5 text-sm font-medium bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">
                        ยกเลิก
                    </button>
                    <button type="submit"
                            class="flex-1 px-4 py-2.5 text-sm font-medium bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-500/25">
                        <i class="bi bi-check-lg mr-1"></i><span x-text="editingSourceId ? 'อัปเดต' : 'เพิ่ม'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    
    <form id="deleteSourceForm" method="POST" class="hidden"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?></form>
    <form id="bulkNewsForm" method="POST" action="<?php echo e(route('admin.blog.news.bulk-action')); ?>" class="hidden">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" id="bulkNewsAction">
        <input type="hidden" name="ids" id="bulkNewsIds">
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
function newsManager() {
    return {
        activeTab: '<?php echo e(request('tab', 'sources')); ?>',
        showAddSource: false,
        editingSourceId: null,
        fetching: false,
        selectedItems: [],
        sourceForm: { name: '', url: '', category: '', is_active: true },

        init() {},

        fetchAllSources() {
            this.fetching = true;
            fetch("<?php echo e(route('admin.blog.news.fetch-all')); ?>", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            }).then(r => r.json()).then(data => {
                Swal.fire({ icon: 'success', title: 'สำเร็จ', text: data.message || 'ดึงข่าวสำเร็จ', timer: 2000, showConfirmButton: false });
                setTimeout(() => location.reload(), 2000);
            }).catch(() => {
                Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: 'ไม่สามารถดึงข่าวได้' });
            }).finally(() => { this.fetching = false; });
        },

        fetchSource(id) {
            Swal.fire({ title: 'กำลังดึงข่าว...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            fetch(`<?php echo e(url('admin/blog/news/sources')); ?>/${id}/fetch`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            }).then(r => r.json()).then(data => {
                Swal.fire({ icon: 'success', title: 'สำเร็จ', text: data.message || 'ดึงข่าวสำเร็จ', timer: 1500, showConfirmButton: false });
                setTimeout(() => location.reload(), 1500);
            }).catch(() => {
                Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: 'ไม่สามารถดึงข่าวได้' });
            });
        },

        editSource(id) {
            fetch(`<?php echo e(url('admin/blog/news/sources')); ?>/${id}`, {
                headers: { 'Accept': 'application/json' }
            }).then(r => r.json()).then(data => {
                this.sourceForm = { name: data.name, url: data.url, category: data.category || '', is_active: data.is_active };
                this.editingSourceId = id;
                this.showAddSource = true;
            });
        },

        deleteSource(id, name) {
            Swal.fire({
                title: 'ลบแหล่งข่าว?',
                html: `คุณต้องการลบ <strong>${name}</strong>?<br><small class="text-gray-500">ข่าวที่ดึงมาจะถูกลบด้วย</small>`,
                icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', cancelButtonColor: '#6b7280',
                confirmButtonText: 'ลบเลย', cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('deleteSourceForm');
                    form.action = `<?php echo e(url('admin/blog/news/sources')); ?>/${id}`;
                    form.submit();
                }
            });
        },

        summarizeItem(id) {
            Swal.fire({ title: 'กำลังสรุปข่าวด้วย AI...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            fetch(`<?php echo e(url('admin/blog/news/items')); ?>/${id}/summarize`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
            }).then(r => r.json()).then(data => {
                Swal.fire({ icon: 'success', title: 'สำเร็จ', text: 'สรุปเนื้อหาเรียบร้อย', timer: 1500, showConfirmButton: false });
                setTimeout(() => location.reload(), 1500);
            }).catch(() => { Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: 'ไม่สามารถสรุปได้' }); });
        },

        publishItem(id) {
            Swal.fire({
                title: 'เผยแพร่เป็นบทความ?',
                text: 'จะสร้างบทความใหม่จากข่าวนี้',
                icon: 'question', showCancelButton: true, confirmButtonColor: '#6366f1', cancelButtonColor: '#6b7280',
                confirmButtonText: 'เผยแพร่', cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`<?php echo e(url('admin/blog/news/items')); ?>/${id}/publish`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
                    }).then(r => r.json()).then(data => {
                        Swal.fire({ icon: 'success', title: 'สำเร็จ', text: 'สร้างบทความเรียบร้อย', timer: 1500, showConfirmButton: false });
                        setTimeout(() => location.reload(), 1500);
                    }).catch(() => { Swal.fire({ icon: 'error', title: 'ผิดพลาด' }); });
                }
            });
        },

        dismissItem(id) {
            fetch(`<?php echo e(url('admin/blog/news/items')); ?>/${id}/dismiss`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
            }).then(r => r.json()).then(() => { location.reload(); });
        },

        toggleAllItems(event) {
            if (event.target.checked) {
                this.selectedItems = Array.from(document.querySelectorAll('tbody input[type="checkbox"][value]')).map(cb => cb.value);
            } else {
                this.selectedItems = [];
            }
        },

        bulkAction(action) {
            if (this.selectedItems.length === 0) return;
            const labels = { summarize: 'สรุป AI', publish: 'เผยแพร่', dismiss: 'ปิด' };
            Swal.fire({
                title: `${labels[action]} ${this.selectedItems.length} รายการ?`,
                icon: 'question', showCancelButton: true, confirmButtonColor: '#6366f1', cancelButtonColor: '#6b7280',
                confirmButtonText: 'ยืนยัน', cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('bulkNewsAction').value = action;
                    document.getElementById('bulkNewsIds').value = JSON.stringify(this.selectedItems);
                    document.getElementById('bulkNewsForm').submit();
                }
            });
        }
    };
}
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/blog/news/index.blade.php ENDPATH**/ ?>