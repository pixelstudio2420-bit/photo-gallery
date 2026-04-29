
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/[0.06]" id="online-users-card">
  <div class="flex justify-between items-center px-5 pt-4 pb-2 border-b border-gray-100 dark:border-white/[0.06]">
    <div class="flex items-center gap-2">
      <span class="inline-flex items-center justify-content-center"
         style="width:32px;height:32px;border-radius:8px;background:rgba(99,102,241,0.1);">
        <i class="bi bi-wifi" style="color:#6366f1;font-size:1rem;"></i>
      </span>
      <h6 class="mb-0 font-semibold">Online Users</h6>
      <span class="inline-flex items-center justify-center ml-1 text-xs font-medium text-white rounded-full" id="online-count-badge"
         style="background:#6366f1;min-width:24px;padding:0.15rem 0.5rem;">
        <?php echo e($onlineCount ?? 0); ?>

      </span>
    </div>
    <span class="text-gray-500" style="font-size:0.72rem;">
      <i class="bi bi-arrow-repeat mr-1"></i>Auto-refresh 30s
    </span>
  </div>
  <div class="p-0">
    <div id="online-users-table-wrapper">
      <?php if(($onlineUsers ?? collect())->isEmpty()): ?>
        <div class="text-center py-6 text-gray-500" id="online-empty-state">
          <i class="bi bi-person-slash" style="font-size:1.8rem;opacity:0.35;"></i>
          <div class="mt-1 text-sm">No users online right now</div>
        </div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="w-full text-sm" style="font-size:0.83rem;">
            <thead class="bg-gray-50 dark:bg-white/[0.04]">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">User</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Device</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Browser / OS</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Last Activity</th>
              </tr>
            </thead>
            <tbody id="online-users-tbody">
              <?php $__currentLoopData = $onlineUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.04] transition">
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <span class="inline-flex items-center justify-center shrink-0"
                       style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;font-size:0.6rem;font-weight:700;">
                      <?php echo e(mb_strtoupper(mb_substr($u->first_name ?? 'U', 0, 1, 'UTF-8'), 'UTF-8')); ?>

                    </span>
                    <div>
                      <div class="font-medium leading-tight">
                        <?php echo e(trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: 'User #'.$u->user_id); ?>

                      </div>
                      <div class="text-gray-500" style="font-size:0.72rem;"><?php echo e($u->email ?? ''); ?></div>
                    </div>
                  </div>
                </td>
                <td class="px-3 py-3">
                  <?php
                    $deviceIcon = match($u->device_type ?? 'desktop') {
                      'mobile' => 'bi-phone',
                      'tablet' => 'bi-tablet',
                      default  => 'bi-laptop',
                    };
                  ?>
                  <span class="inline-flex items-center gap-1">
                    <i class="bi <?php echo e($deviceIcon); ?>" style="color:#6b7280;"></i>
                    <span class="capitalize"><?php echo e($u->device_type ?? 'desktop'); ?></span>
                  </span>
                </td>
                <td class="px-3 py-3">
                  <span class="font-medium"><?php echo e($u->browser ?? 'Unknown'); ?></span>
                  <span class="text-gray-500"> / <?php echo e($u->os ?? 'Unknown'); ?></span>
                </td>
                <td class="px-3 py-3 text-gray-500">
                  <?php echo e($u->last_activity ? \Carbon\Carbon::parse($u->last_activity)->diffForHumans() : '—'); ?>

                </td>
              </tr>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
(function () {
  function refreshOnlineUsers() {
    fetch('<?php echo e(route("admin.api.online-users")); ?>', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
      // Update badge count
      var badge = document.getElementById('online-count-badge');
      if (badge) badge.textContent = data.count;

      var wrapper = document.getElementById('online-users-table-wrapper');
      if (!wrapper) return;

      if (data.count === 0) {
        wrapper.innerHTML = '<div class="text-center py-6 text-gray-500"><i class="bi bi-person-slash" style="font-size:1.8rem;opacity:0.35;"></i><div class="mt-1 text-sm">No users online right now</div></div>';
        return;
      }

      var deviceIcon = { mobile: 'bi-phone', tablet: 'bi-tablet', desktop: 'bi-laptop' };
      var rows = data.users.map(function (u) {
        var initial = (u.name || 'U').charAt(0).toUpperCase();
        var icon = deviceIcon[u.device] || 'bi-laptop';
        var lastActivity = u.last_activity
          ? new Date(u.last_activity).toLocaleTimeString()
          : '—';
        return '<tr class="hover:bg-gray-50 dark:hover:bg-white/[0.04] transition">' +
          '<td class="px-4 py-3"><div class="flex items-center gap-2">' +
          '<span class="inline-flex items-center justify-center shrink-0" style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;font-size:0.6rem;font-weight:700;">' + initial + '</span>' +
          '<div><div class="font-medium leading-tight">' + (u.name || 'User #' + u.id) + '</div>' +
          '<div class="text-gray-500" style="font-size:0.72rem;">' + (u.email || '') + '</div></div></div></td>' +
          '<td class="px-3 py-3"><span class="inline-flex items-center gap-1"><i class="bi ' + icon + '" style="color:#6b7280;"></i><span class="capitalize">' + (u.device || 'desktop') + '</span></span></td>' +
          '<td class="px-3 py-3"><span class="font-medium">' + (u.browser || 'Unknown') + '</span><span class="text-gray-500"> / ' + (u.os || 'Unknown') + '</span></td>' +
          '<td class="px-3 py-3 text-gray-500">' + lastActivity + '</td>' +
          '</tr>';
      }).join('');

      wrapper.innerHTML = '<div class="overflow-x-auto"><table class="w-full text-sm" style="font-size:0.83rem;">' +
        '<thead class="bg-gray-50"><tr>' +
        '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">User</th>' +
        '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Device</th>' +
        '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Browser / OS</th>' +
        '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Last Activity</th>' +
        '</tr></thead><tbody>' + rows + '</tbody></table></div>';
    })
    .catch(function () { /* silent fail */ });
  }

  // Auto-refresh every 30 seconds
  setInterval(refreshOnlineUsers, 30000);
})();
</script>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/partials/online-users.blade.php ENDPATH**/ ?>