<?php $__env->startSection('title', 'จัดการอีเวนต์'); ?>

<?php $__env->startSection('content'); ?>
<div class="flex justify-between items-center mb-6 flex-wrap gap-2">
  <div>
    <h4 class="font-bold mb-1 text-xl tracking-tight">
      <i class="bi bi-calendar-event mr-2 text-indigo-500"></i>งานอีเวนต์
    </h4>
    <p class="text-gray-500 mb-0 text-sm">จัดการอีเวนต์ถ่ายภาพ ตรวจสอบสถานะ และติดตามยอดสั่งซื้อ</p>
  </div>
  <a href="<?php echo e(route('admin.events.create')); ?>" class="inline-flex items-center bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-lg font-medium px-5 py-2 transition hover:from-indigo-600 hover:to-indigo-700">
    <i class="bi bi-plus-lg mr-1"></i> เพิ่มอีเวนต์
  </a>
</div>


<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-indigo-500/10">
        <i class="bi bi-calendar-event text-indigo-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl"><?php echo e($stats['total']); ?></div>
        <small class="text-gray-500">ทั้งหมด</small>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-emerald-500/10">
        <i class="bi bi-check-circle text-emerald-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl"><?php echo e($stats['active']); ?></div>
        <small class="text-gray-500">เปิดใช้งาน</small>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-amber-500/10">
        <i class="bi bi-pencil-square text-amber-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl"><?php echo e($stats['draft']); ?></div>
        <small class="text-gray-500">ฉบับร่าง</small>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-gray-500/10">
        <i class="bi bi-archive text-gray-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl"><?php echo e($stats['archived']); ?></div>
        <small class="text-gray-500">เก็บถาวร</small>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-violet-500/10">
        <i class="bi bi-currency-exchange text-violet-600 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-lg"><?php echo e(number_format($stats['total_revenue'], 0)); ?></div>
        <small class="text-gray-500">รายได้รวม (฿)</small>
      </div>
    </div>
  </div>
</div>


<div class="af-bar" x-data="adminFilter()">
  <form method="GET" action="<?php echo e(route('admin.events.index')); ?>">
    <div class="af-grid">

      
      <div class="af-search">
        <label class="af-label">ค้นหา</label>
        <div class="af-search-wrap">
          <i class="bi bi-search af-search-icon"></i>
          <input type="text" name="q" class="af-input" placeholder="ค้นหาอีเวนต์..." value="<?php echo e(request('q')); ?>">
          <div class="af-spinner" x-show="loading" x-cloak></div>
        </div>
      </div>

      
      <div>
        <label class="af-label">หมวดหมู่</label>
        <select name="category" class="af-input">
          <option value="">ทุกหมวดหมู่</option>
          <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($cat->id); ?>" <?php echo e(request('category') == $cat->id ? 'selected' : ''); ?>><?php echo e($cat->name); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>

      
      <div>
        <label class="af-label">สถานะ</label>
        <select name="status" class="af-input">
          <option value="">ทุกสถานะ</option>
          <option value="active" <?php echo e(request('status') === 'active' ? 'selected' : ''); ?>>Active</option>
          <option value="published" <?php echo e(request('status') === 'published' ? 'selected' : ''); ?>>Published</option>
          <option value="draft" <?php echo e(request('status') === 'draft' ? 'selected' : ''); ?>>Draft</option>
          <option value="archived" <?php echo e(request('status') === 'archived' ? 'selected' : ''); ?>>Archived</option>
        </select>
      </div>

      
      <div>
        <label class="af-label">จังหวัด</label>
        <select name="province" class="af-input">
          <option value="">ทุกจังหวัด</option>
          <?php $__currentLoopData = $provinces; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $prov): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($prov->id); ?>" <?php echo e(request('province') == $prov->id ? 'selected' : ''); ?>><?php echo e($prov->name_th); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>

      
      <div>
        <label class="af-label">เรียงตาม</label>
        <select name="sort" class="af-input">
          <option value="">ล่าสุด</option>
          <option value="newest" <?php echo e(request('sort') === 'newest' ? 'selected' : ''); ?>>ล่าสุด</option>
          <option value="oldest" <?php echo e(request('sort') === 'oldest' ? 'selected' : ''); ?>>เก่าสุด</option>
          <option value="name" <?php echo e(request('sort') === 'name' ? 'selected' : ''); ?>>ชื่อ</option>
          <option value="date" <?php echo e(request('sort') === 'date' ? 'selected' : ''); ?>>วันถ่าย</option>
          <option value="photos" <?php echo e(request('sort') === 'photos' ? 'selected' : ''); ?>>รูปภาพ</option>
          <option value="orders" <?php echo e(request('sort') === 'orders' ? 'selected' : ''); ?>>ออเดอร์</option>
          <option value="price" <?php echo e(request('sort') === 'price' ? 'selected' : ''); ?>>ราคา</option>
        </select>
      </div>

      
      <div class="af-actions">
        <button type="button" class="af-btn-clear" @click="clearFilters()">
          <i class="bi bi-x-lg mr-1"></i>ล้าง
        </button>
      </div>

    </div>
  </form>
</div>


<div id="admin-table-area">
<div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 dark:bg-white/[0.02]">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider pl-5">ชื่ออีเวนต์</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ช่างภาพ</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">หมวดหมู่</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">พื้นที่</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">วันที่ถ่าย</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">ภาพ</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">ออเดอร์</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">สถานะ</th>
          <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider pr-5">จัดการ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
        <?php $__empty_1 = true; $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <?php
          $statusMap = [
            'active'    => ['bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-600', 'label' => 'Active',    'dot' => 'bg-emerald-500'],
            'published' => ['bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-600', 'label' => 'Published', 'dot' => 'bg-emerald-500'],
            'draft'     => ['bg' => 'bg-amber-500/10',   'text' => 'text-amber-600',   'label' => 'Draft',     'dot' => 'bg-amber-500'],
            'archived'  => ['bg' => 'bg-gray-500/10',    'text' => 'text-gray-500',    'label' => 'Archived',  'dot' => 'bg-gray-400'],
            'hidden'    => ['bg' => 'bg-gray-500/10',    'text' => 'text-gray-500',    'label' => 'Hidden',    'dot' => 'bg-gray-400'],
            'inactive'  => ['bg' => 'bg-red-500/10',     'text' => 'text-red-500',     'label' => 'Inactive',  'dot' => 'bg-red-500'],
          ];
          $st = $statusMap[$event->status ?? 'draft'] ?? $statusMap['draft'];
        ?>
        <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
          
          <td class="pl-5 py-3 px-4">
            <div class="flex items-center gap-3">
              <?php if($event->cover_image): ?>
              <img src="<?php echo e($event->cover_image); ?>" alt="" class="w-10 h-10 rounded-lg object-cover flex-shrink-0">
              <?php else: ?>
              <div class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center flex-shrink-0">
                <i class="bi bi-image text-gray-400 dark:text-gray-500"></i>
              </div>
              <?php endif; ?>
              <div class="min-w-0">
                <a href="<?php echo e(route('admin.events.show', $event)); ?>" class="font-semibold text-gray-800 dark:text-gray-100 hover:text-indigo-600 transition truncate block">
                  <?php echo e($event->name); ?>

                </a>
              </div>
            </div>
          </td>
          
          <td class="py-3 px-4">
            <?php if($event->photographer): ?>
            <span class="text-gray-700 dark:text-gray-300"><?php echo e($event->photographer->first_name); ?></span>
            <?php else: ?>
            <span class="text-gray-400">-</span>
            <?php endif; ?>
          </td>
          
          <td class="py-3 px-4">
            <?php if($event->category): ?>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-500/10 text-indigo-600">
              <?php echo e($event->category->name); ?>

            </span>
            <?php else: ?>
            <span class="text-gray-400">-</span>
            <?php endif; ?>
          </td>
          
          <td class="py-3 px-4">
            <?php if($event->province): ?>
            <span class="text-gray-700 dark:text-gray-300"><?php echo e($event->province->name_th); ?></span>
            <?php elseif($event->location): ?>
            <span class="text-gray-700 dark:text-gray-300" title="<?php echo e($event->location); ?>"><?php echo e(Str::limit($event->location, 20)); ?></span>
            <?php else: ?>
            <span class="text-gray-400">-</span>
            <?php endif; ?>
          </td>
          
          <td class="py-3 px-4">
            <small class="text-gray-500"><?php echo e($event->shoot_date?->format('d M Y')); ?></small>
          </td>
          
          <td class="py-3 px-4 text-center">
            <span class="font-semibold"><?php echo e($event->photos_count); ?></span>
          </td>
          
          <td class="py-3 px-4 text-center">
            <span class="font-semibold"><?php echo e($event->orders_count); ?></span>
          </td>
          
          <td class="py-3 px-4">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php echo e($st['bg']); ?> <?php echo e($st['text']); ?>">
              <span class="w-1.5 h-1.5 rounded-full <?php echo e($st['dot']); ?> mr-1.5"></span>
              <?php echo e($st['label']); ?>

            </span>
          </td>
          
          <td class="py-3 pr-5 px-4 text-right">
            <div class="flex gap-1 justify-end">
              
              <a href="<?php echo e(route('admin.events.show', $event)); ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-600/[0.08] text-blue-600 transition hover:bg-blue-600/[0.15]" title="ดูรายละเอียด">
                <i class="bi bi-eye text-sm"></i>
              </a>
              
              <a href="<?php echo e(route('admin.events.edit', $event)); ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-500/[0.08] text-indigo-600 transition hover:bg-indigo-500/[0.15]" title="แก้ไข">
                <i class="bi bi-pencil text-sm"></i>
              </a>
              
              <form method="POST" action="<?php echo e(route('admin.events.toggle-status', $event)); ?>" class="inline">
                <?php echo csrf_field(); ?>
                <?php if(in_array($event->status, ['active', 'published'])): ?>
                <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-500/[0.08] text-emerald-600 transition hover:bg-emerald-500/[0.15]" title="ปิดใช้งาน">
                  <i class="bi bi-toggle2-on text-sm"></i>
                </button>
                <?php else: ?>
                <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-500/[0.08] text-red-500 transition hover:bg-red-500/[0.15]" title="เปิดใช้งาน">
                  <i class="bi bi-toggle2-off text-sm"></i>
                </button>
                <?php endif; ?>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr>
          <td colspan="9" class="text-center py-12">
            <i class="bi bi-calendar-x text-4xl text-gray-300 dark:text-gray-600"></i>
            <p class="text-gray-500 mt-2 mb-0">ไม่พบอีเวนต์</p>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<div id="admin-pagination-area">
<?php if($events->hasPages()): ?>
<div class="flex justify-center mt-4"><?php echo e($events->withQueryString()->links()); ?></div>
<?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/events/index.blade.php ENDPATH**/ ?>