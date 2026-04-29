<?php $__env->startSection('title', 'Support Tickets'); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-5">

  
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-800 dark:text-gray-100">
        <i class="bi bi-ticket-detailed text-indigo-500 mr-2"></i>Support Tickets
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">จัดการคำถามและปัญหาจากลูกค้า</p>
    </div>
    <?php if($stats['overdue'] > 0): ?>
    <a href="?overdue=1" class="px-4 py-2 bg-red-500 text-white rounded-xl text-sm font-medium hover:bg-red-600 animate-pulse">
      <i class="bi bi-clock-history"></i> เกินกำหนด <?php echo e($stats['overdue']); ?> รายการ
    </a>
    <?php endif; ?>
  </div>

  
  <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
    <?php
      $statCards = [
        ['label' => 'ทั้งหมด',    'value' => $stats['total'],      'color' => 'slate',   'icon' => 'inbox'],
        ['label' => 'ใหม่',       'value' => $stats['new'],        'color' => 'blue',    'icon' => 'envelope'],
        ['label' => 'เปิดอยู่',    'value' => $stats['open'],       'color' => 'amber',   'icon' => 'envelope-open'],
        ['label' => 'แก้ไขแล้ว',   'value' => $stats['resolved'],   'color' => 'emerald', 'icon' => 'check-circle'],
        ['label' => 'ยังไม่มอบหมาย','value' => $stats['unassigned'], 'color' => 'gray',    'icon' => 'person-dash'],
        ['label' => 'ของฉัน',     'value' => $stats['mine'],       'color' => 'indigo',  'icon' => 'person-check'],
        ['label' => 'เกินกำหนด',   'value' => $stats['overdue'],    'color' => 'red',     'icon' => 'clock-history'],
        ['label' => 'เร่งด่วน',    'value' => $stats['urgent'],     'color' => 'red',     'icon' => 'exclamation-triangle'],
      ];
    ?>
    <?php $__currentLoopData = $statCards; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-3">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-<?php echo e($s['color']); ?>-100 text-<?php echo e($s['color']); ?>-600 flex items-center justify-center shrink-0">
          <i class="bi bi-<?php echo e($s['icon']); ?> text-sm"></i>
        </div>
        <div class="min-w-0">
          <div class="text-[10px] text-gray-500 truncate"><?php echo e($s['label']); ?></div>
          <div class="text-lg font-bold text-<?php echo e($s['color']); ?>-600"><?php echo e(number_format($s['value'])); ?></div>
        </div>
      </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>

  
  <form method="GET" class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4 grid grid-cols-1 md:grid-cols-7 gap-3">
    <input type="text" name="q" value="<?php echo e(request('q')); ?>" placeholder="Ticket #, หัวข้อ, ชื่อ..."
           class="col-span-2 px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
    <select name="status" class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
      <option value="">ทุกสถานะ</option>
      <option value="open" <?php echo e(request('status') === 'open' ? 'selected' : ''); ?>>เปิดอยู่ทั้งหมด</option>
      <option value="resolved" <?php echo e(request('status') === 'resolved' ? 'selected' : ''); ?>>แก้ไขแล้ว</option>
      <?php $__currentLoopData = \App\Models\ContactMessage::STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <option value="<?php echo e($k); ?>" <?php echo e(request('status') === $k ? 'selected' : ''); ?>><?php echo e($label); ?></option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
    <select name="priority" class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
      <option value="">ทุกความสำคัญ</option>
      <?php $__currentLoopData = \App\Models\ContactMessage::PRIORITIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <option value="<?php echo e($k); ?>" <?php echo e(request('priority') === $k ? 'selected' : ''); ?>><?php echo e($p['label']); ?></option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
    <select name="category" class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
      <option value="">ทุกหมวด</option>
      <?php $__currentLoopData = \App\Models\ContactMessage::CATEGORIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <option value="<?php echo e($k); ?>" <?php echo e(request('category') === $k ? 'selected' : ''); ?>><?php echo e($label); ?></option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
    <select name="assigned" class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
      <option value="">ทุกการมอบหมาย</option>
      <option value="me" <?php echo e(request('assigned') === 'me' ? 'selected' : ''); ?>>ของฉัน</option>
      <option value="unassigned" <?php echo e(request('assigned') === 'unassigned' ? 'selected' : ''); ?>>ยังไม่มอบหมาย</option>
      <?php $__currentLoopData = $admins; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <option value="<?php echo e($a->id); ?>" <?php echo e((string)request('assigned') === (string)$a->id ? 'selected' : ''); ?>><?php echo e($a->first_name); ?> <?php echo e($a->last_name); ?></option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
    <button type="submit" class="px-4 py-2 bg-indigo-500 text-white rounded-lg text-sm font-medium hover:bg-indigo-600">ค้นหา</button>
  </form>

  
  <form method="POST" action="<?php echo e(route('admin.messages.bulk-action')); ?>" id="bulkForm">
    <?php echo csrf_field(); ?>
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl overflow-hidden">

      
      <div class="p-3 border-b border-gray-100 dark:border-white/5 flex flex-wrap items-center gap-2 text-sm">
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" id="selectAll" class="rounded border-gray-300">
          <span class="text-gray-600">เลือกทั้งหมด</span>
        </label>
        <div class="flex gap-2 flex-wrap">
          <button type="button" onclick="bulkAction('assign_me')" class="px-3 py-1.5 bg-indigo-50 text-indigo-600 rounded-lg text-xs font-medium hover:bg-indigo-100">
            <i class="bi bi-person-check"></i> มอบให้ฉัน
          </button>
          <button type="button" onclick="bulkAction('resolve')" class="px-3 py-1.5 bg-emerald-50 text-emerald-600 rounded-lg text-xs font-medium hover:bg-emerald-100">
            <i class="bi bi-check2"></i> แก้ไขแล้ว
          </button>
          <button type="button" onclick="bulkAction('close')" class="px-3 py-1.5 bg-gray-100 text-gray-600 rounded-lg text-xs font-medium hover:bg-gray-200">
            <i class="bi bi-lock"></i> ปิด
          </button>
          <button type="button" onclick="bulkAction('delete')" class="px-3 py-1.5 bg-red-50 text-red-600 rounded-lg text-xs font-medium hover:bg-red-100">
            <i class="bi bi-trash"></i> ลบ
          </button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-white/[0.02]">
            <tr>
              <th class="w-8 p-3"></th>
              <th class="text-left p-3 font-semibold text-xs text-gray-600 uppercase">Ticket / Subject</th>
              <th class="text-left p-3 font-semibold text-xs text-gray-600 uppercase">From</th>
              <th class="text-left p-3 font-semibold text-xs text-gray-600 uppercase">Category</th>
              <th class="text-left p-3 font-semibold text-xs text-gray-600 uppercase">Priority</th>
              <th class="text-left p-3 font-semibold text-xs text-gray-600 uppercase">Status</th>
              <th class="text-left p-3 font-semibold text-xs text-gray-600 uppercase">Assigned</th>
              <th class="text-left p-3 font-semibold text-xs text-gray-600 uppercase">SLA</th>
              <th class="text-left p-3 font-semibold text-xs text-gray-600 uppercase">Activity</th>
            </tr>
          </thead>
          <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $tickets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr class="border-t border-gray-50 dark:border-white/5 hover:bg-gray-50 dark:hover:bg-white/[0.02] <?php echo e($t->isOverdue() ? 'bg-red-50/50 dark:bg-red-500/[0.05]' : ''); ?>">
              <td class="p-3">
                <input type="checkbox" name="ids[]" value="<?php echo e($t->id); ?>" class="ticket-checkbox rounded border-gray-300">
              </td>
              <td class="p-3">
                <a href="<?php echo e(route('admin.messages.show', $t)); ?>" class="block">
                  <div class="font-mono text-xs text-indigo-600 mb-0.5"><?php echo e($t->ticket_number); ?></div>
                  <div class="font-semibold text-slate-800 dark:text-gray-100 truncate max-w-xs hover:text-indigo-600"><?php echo e($t->subject); ?></div>
                  <?php if($t->reply_count > 0): ?>
                  <div class="text-xs text-gray-500 mt-0.5"><i class="bi bi-chat-left-dots"></i> <?php echo e($t->reply_count); ?> replies</div>
                  <?php endif; ?>
                </a>
              </td>
              <td class="p-3">
                <div class="font-medium text-sm"><?php echo e($t->name); ?></div>
                <div class="text-xs text-gray-500 truncate max-w-[150px]"><?php echo e($t->email); ?></div>
              </td>
              <td class="p-3">
                <span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-700 rounded"><?php echo e($t->category_label); ?></span>
              </td>
              <td class="p-3">
                <span class="text-xs px-2 py-0.5 bg-<?php echo e($t->priority_color); ?>-100 text-<?php echo e($t->priority_color); ?>-700 rounded font-semibold"><?php echo e($t->priority_label); ?></span>
              </td>
              <td class="p-3">
                <?php switch($t->status):
                  case ('new'): ?><span class="text-xs px-2 py-0.5 bg-blue-100 text-blue-700 rounded font-medium">ใหม่</span><?php break; ?>
                  <?php case ('open'): ?><span class="text-xs px-2 py-0.5 bg-amber-100 text-amber-700 rounded font-medium">เปิดอยู่</span><?php break; ?>
                  <?php case ('in_progress'): ?><span class="text-xs px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded font-medium">กำลังทำ</span><?php break; ?>
                  <?php case ('waiting'): ?><span class="text-xs px-2 py-0.5 bg-purple-100 text-purple-700 rounded font-medium">รอลูกค้า</span><?php break; ?>
                  <?php case ('resolved'): ?><span class="text-xs px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded font-medium">แก้ไขแล้ว</span><?php break; ?>
                  <?php case ('closed'): ?><span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-700 rounded font-medium">ปิด</span><?php break; ?>
                <?php endswitch; ?>
              </td>
              <td class="p-3 text-xs">
                <?php if($t->assignedAdmin): ?>
                  <div class="flex items-center gap-1.5">
                    <div class="w-6 h-6 rounded-full bg-indigo-500 text-white flex items-center justify-center text-[10px] font-bold">
                      <?php echo e(mb_strtoupper(mb_substr($t->assignedAdmin->first_name ?? 'A', 0, 1, 'UTF-8'), 'UTF-8')); ?>

                    </div>
                    <span><?php echo e($t->assignedAdmin->first_name); ?></span>
                  </div>
                <?php else: ?>
                  <span class="text-gray-400">-</span>
                <?php endif; ?>
              </td>
              <td class="p-3 text-xs">
                <?php if($t->sla_deadline): ?>
                  <span class="<?php echo e($t->isOverdue() ? 'text-red-600 font-semibold' : 'text-gray-600'); ?>"><?php echo e($t->slaTimeRemaining()); ?></span>
                <?php else: ?>
                  <span class="text-gray-400">-</span>
                <?php endif; ?>
              </td>
              <td class="p-3 text-xs text-gray-500"><?php echo e(($t->last_activity_at ?? $t->created_at)->diffForHumans()); ?></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
              <td colspan="9" class="p-12 text-center text-gray-500">
                <i class="bi bi-inbox text-3xl text-gray-300"></i>
                <p class="mt-2">ไม่พบ tickets</p>
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </form>

  <?php if($tickets->hasPages()): ?>
  <div class="flex justify-center"><?php echo e($tickets->links()); ?></div>
  <?php endif; ?>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
document.getElementById('selectAll').addEventListener('change', function() {
  document.querySelectorAll('.ticket-checkbox').forEach(cb => cb.checked = this.checked);
});
function bulkAction(action) {
  const checked = document.querySelectorAll('.ticket-checkbox:checked');
  if (checked.length === 0) return alert('กรุณาเลือกรายการ');
  const texts = { assign_me: 'มอบให้ฉัน', resolve: 'แก้ไขแล้ว', close: 'ปิด', delete: 'ลบ' };
  if (!confirm(`${texts[action]} ${checked.length} รายการ?`)) return;
  const form = document.getElementById('bulkForm');
  let input = form.querySelector('input[name="action"]');
  if (!input) { input = document.createElement('input'); input.type = 'hidden'; input.name = 'action'; form.appendChild(input); }
  input.value = action;
  form.submit();
}
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/messages/index.blade.php ENDPATH**/ ?>