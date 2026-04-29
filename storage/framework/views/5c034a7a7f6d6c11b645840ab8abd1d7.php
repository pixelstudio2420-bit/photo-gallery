<?php $__env->startSection('title', 'ใบเสร็จ / ใบแจ้งหนี้'); ?>

<?php $__env->startSection('content'); ?>


<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0" style="letter-spacing:-0.02em;">
    <i class="bi bi-receipt mr-2" style="color:#6366f1;"></i>ใบเสร็จ / ใบแจ้งหนี้
  </h4>
</div>


<div class="row g-3 mb-4">
  
  <div class="col-xl">
    <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,0.06);transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 12px rgba(0,0,0,0.06)'">
      <div class="p-5 py-3 px-4">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center shrink-0" style="width:44px;height:44px;border-radius:12px;background:rgba(99,102,241,0.1);">
            <i class="bi bi-receipt" style="font-size:1.2rem;color:#6366f1;"></i>
          </div>
          <div>
            <div class="font-bold" style="font-size:1.5rem;line-height:1.1;color:#1e293b;"><?php echo e(number_format($stats['total_invoices'])); ?></div>
            <div class="text-gray-500" style="font-size:0.78rem;margin-top:2px;">ใบเสร็จทั้งหมด</div>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <div class="col-xl">
    <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,0.06);transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 12px rgba(0,0,0,0.06)'">
      <div class="p-5 py-3 px-4">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center shrink-0" style="width:44px;height:44px;border-radius:12px;background:rgba(34,197,94,0.1);">
            <i class="bi bi-cash-stack" style="font-size:1.2rem;color:#22c55e;"></i>
          </div>
          <div>
            <div class="font-bold" style="font-size:1.3rem;line-height:1.1;color:#22c55e;"><?php echo e(number_format($stats['total_revenue'], 0)); ?></div>
            <div class="text-gray-500" style="font-size:0.78rem;margin-top:2px;">รายได้รวม (฿)</div>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <div class="col-xl">
    <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,0.06);transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 12px rgba(0,0,0,0.06)'">
      <div class="p-5 py-3 px-4">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center shrink-0" style="width:44px;height:44px;border-radius:12px;background:rgba(59,130,246,0.1);">
            <i class="bi bi-calendar-month" style="font-size:1.2rem;color:#3b82f6;"></i>
          </div>
          <div>
            <div class="font-bold" style="font-size:1.3rem;line-height:1.1;color:#3b82f6;"><?php echo e(number_format($stats['this_month'], 0)); ?></div>
            <div class="text-gray-500" style="font-size:0.78rem;margin-top:2px;">เดือนนี้ (฿)</div>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <div class="col-xl">
    <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,0.06);transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 12px rgba(0,0,0,0.06)'">
      <div class="p-5 py-3 px-4">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center shrink-0" style="width:44px;height:44px;border-radius:12px;background:rgba(16,185,129,0.1);">
            <i class="bi bi-clock" style="font-size:1.2rem;color:#10b981;"></i>
          </div>
          <div>
            <div class="font-bold" style="font-size:1.3rem;line-height:1.1;color:#10b981;"><?php echo e(number_format($stats['today'], 0)); ?></div>
            <div class="text-gray-500" style="font-size:0.78rem;margin-top:2px;">วันนี้ (฿)</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>


<div class="af-bar" x-data="adminFilter()">
  <form method="GET" action="<?php echo e(route('admin.invoices.index')); ?>">
    <div class="af-grid">

      
      <div class="af-search">
        <label class="af-label">ค้นหา</label>
        <div class="af-search-wrap">
          <i class="bi bi-search af-search-icon"></i>
          <input type="text" name="q" class="af-input" placeholder="เลขใบเสร็จ, ชื่อลูกค้า, อีเมล..." value="<?php echo e(request('q')); ?>">
          <div class="af-spinner" x-show="loading" x-cloak></div>
        </div>
      </div>

      
      <div>
        <label class="af-label">ช่วงเวลา</label>
        <select name="period" class="af-input">
          <option value="">ทั้งหมด</option>
          <option value="today" <?php echo e(request('period') === 'today' ? 'selected' : ''); ?>>วันนี้</option>
          <option value="week"  <?php echo e(request('period') === 'week'  ? 'selected' : ''); ?>>7 วันล่าสุด</option>
          <option value="month" <?php echo e(request('period') === 'month' ? 'selected' : ''); ?>>30 วันล่าสุด</option>
          <option value="year"  <?php echo e(request('period') === 'year'  ? 'selected' : ''); ?>>ปีนี้</option>
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
<div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,0.06);overflow:hidden;">
  <div class="p-5 p-0">
    <div class="overflow-x-auto">
      <table class="table table-hover mb-0 align-middle">
        <thead style="background:rgba(99,102,241,0.04);border-b:1px solid rgba(99,102,241,0.08);">
          <tr>
            <th class="ps-4" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;padding-top:0.9rem;padding-bottom:0.9rem;">เลขใบเสร็จ</th>
            <th style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;">ลูกค้า</th>
            <th style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;">อีเวนต์</th>
            <th style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;">ยอดรวม</th>
            <th style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;">วันที่</th>
            <th style="width:56px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <?php
            $firstName = $order->user->first_name ?? 'U';
            $lastName  = $order->user->last_name ?? '';
          ?>
          <tr style="transition:background .12s;">
            
            <td class="ps-4">
              <a href="<?php echo e(route('admin.invoices.show', $order->id)); ?>" class="no-underline">
                <code style="font-size:0.82rem;color:#6366f1;font-weight:600;background:rgba(99,102,241,0.06);padding:0.2em 0.5em;border-radius:5px;">
                  INV-<?php echo e(str_pad($order->id, 6, '0', STR_PAD_LEFT)); ?>

                </code>
              </a>
            </td>
            
            <td>
              <div class="flex items-center gap-2">
                <?php if (isset($component)) { $__componentOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.avatar','data' => ['src' => $order->user->avatar ?? null,'name' => trim($firstName . ' ' . $lastName),'userId' => $order->user_id,'size' => 'md']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['src' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($order->user->avatar ?? null),'name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(trim($firstName . ' ' . $lastName)),'user-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($order->user_id),'size' => 'md']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b)): ?>
<?php $attributes = $__attributesOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b; ?>
<?php unset($__attributesOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b)): ?>
<?php $component = $__componentOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b; ?>
<?php unset($__componentOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b); ?>
<?php endif; ?>
                <div>
                  <div style="font-size:0.88rem;font-weight:600;color:#1e293b;line-height:1.3;">
                    <?php echo e(trim($firstName . ' ' . $lastName) ?: 'ไม่ระบุ'); ?>

                  </div>
                  <div style="font-size:0.75rem;color:#94a3b8;line-height:1.2;">
                    <?php echo e($order->user->email ?? ''); ?>

                  </div>
                </div>
              </div>
            </td>
            
            <td>
              <span style="font-size:0.85rem;color:#475569;">
                <?php echo e($order->event->name ?? '-'); ?>

              </span>
            </td>
            
            <td>
              <span class="font-bold" style="font-size:0.95rem;color:#1e293b;">
                ฿<?php echo e(number_format($order->total, 0)); ?>

              </span>
            </td>
            
            <td>
              <div style="font-size:0.82rem;color:#475569;"><?php echo e($order->created_at->format('d/m/Y')); ?></div>
              <div style="font-size:0.73rem;color:#94a3b8;"><?php echo e($order->created_at->format('H:i')); ?></div>
            </td>
            
            <td class="pe-3">
              <a href="<?php echo e(route('admin.invoices.show', $order->id)); ?>"
                 class="inline-flex items-center justify-center"
                 style="width:34px;height:34px;border-radius:8px;background:rgba(99,102,241,0.07);border:none;color:#6366f1;transition:background .15s;"
                 title="ดูใบเสร็จ"
                 onmouseover="this.style.background='rgba(99,102,241,0.15)'"
                 onmouseout="this.style.background='rgba(99,102,241,0.07)'">
                <i class="bi bi-eye" style="font-size:0.9rem;"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr>
            <td colspan="6" class="text-center py-5">
              <div style="color:#cbd5e1;">
                <i class="bi bi-receipt" style="font-size:3rem;display:block;margin-bottom:0.75rem;"></i>
              </div>
              <p class="text-gray-500 mb-1" style="font-size:0.95rem;font-weight:500;">ไม่พบใบเสร็จ</p>
              <?php if(request()->hasAny(['q','period'])): ?>
              <a href="<?php echo e(route('admin.invoices.index')); ?>" class="text-sm px-3 py-1.5 rounded-lg mt-2 inline-block" style="border-radius:8px;background:rgba(99,102,241,0.08);color:#6366f1;border:none;">
                <i class="bi bi-x-circle mr-1"></i>ล้างตัวกรอง
              </a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  
  <div id="admin-pagination-area">
  <?php if($orders->hasPages()): ?>
  <div class="px-5 py-3 border-t border-gray-100 bg-white border-0 py-3 px-4" style="border-t:1px solid rgba(0,0,0,0.05);">
    <div class="flex flex-wrap justify-between items-center gap-2">
      <div class="text-gray-500" style="font-size:0.82rem;">
        แสดง <strong><?php echo e($orders->firstItem()); ?></strong>–<strong><?php echo e($orders->lastItem()); ?></strong>
        จาก <strong><?php echo e(number_format($orders->total())); ?></strong> รายการ
      </div>
      <nav>
        <ul class="pagination pagination-sm mb-0 gap-1">
          
          <?php if($orders->onFirstPage()): ?>
          <li class="page-item disabled">
            <span class="page-link" style="border-radius:8px;border-color:#e2e8f0;color:#94a3b8;">
              <i class="bi bi-chevron-left" style="font-size:0.75rem;"></i>
            </span>
          </li>
          <?php else: ?>
          <li class="page-item">
            <a class="page-link" href="<?php echo e($orders->withQueryString()->previousPageUrl()); ?>" style="border-radius:8px;border-color:#e2e8f0;color:#475569;">
              <i class="bi bi-chevron-left" style="font-size:0.75rem;"></i>
            </a>
          </li>
          <?php endif; ?>

          
          <?php $__currentLoopData = $orders->withQueryString()->getUrlRange(max(1,$orders->currentPage()-2), min($orders->lastPage(),$orders->currentPage()+2)); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $page => $url): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <li class="page-item <?php echo e($page == $orders->currentPage() ? 'active' : ''); ?>">
            <a class="page-link" href="<?php echo e($url); ?>"
              style="border-radius:8px;border-color:<?php echo e($page == $orders->currentPage() ? '#6366f1' : '#e2e8f0'); ?>;background:<?php echo e($page == $orders->currentPage() ? '#6366f1' : '#fff'); ?>;color:<?php echo e($page == $orders->currentPage() ? '#fff' : '#475569'); ?>;">
              <?php echo e($page); ?>

            </a>
          </li>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

          
          <?php if($orders->hasMorePages()): ?>
          <li class="page-item">
            <a class="page-link" href="<?php echo e($orders->withQueryString()->nextPageUrl()); ?>" style="border-radius:8px;border-color:#e2e8f0;color:#475569;">
              <i class="bi bi-chevron-right" style="font-size:0.75rem;"></i>
            </a>
          </li>
          <?php else: ?>
          <li class="page-item disabled">
            <span class="page-link" style="border-radius:8px;border-color:#e2e8f0;color:#94a3b8;">
              <i class="bi bi-chevron-right" style="font-size:0.75rem;"></i>
            </span>
          </li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
  </div>
  <?php endif; ?>
  </div>
</div>
</div>

<style>
.table-hover tbody tr:hover { background: rgba(99,102,241,0.025) !important; }
</style>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/invoices/index.blade.php ENDPATH**/ ?>