<?php $__env->startSection('title', 'ติดต่อเรา'); ?>

<?php $__env->startSection('content'); ?>

<header class="max-w-4xl mx-auto text-center mb-8">
  <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white mb-2">ติดต่อทีมงาน Loadroop</h1>
  <p class="text-base text-slate-600 dark:text-slate-400">ส่งคำถามหรือปัญหา · เราตอบกลับภายใน 24 ชั่วโมง</p>
</header>

<div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-6">

  
  <div class="md:col-span-1 space-y-4">
    <div class="bg-gradient-to-br from-indigo-500 to-violet-600 text-white rounded-2xl p-6">
      <i class="bi bi-headset text-3xl mb-3"></i>
      <h3 class="font-bold text-lg mb-2">ติดต่อทีมงาน</h3>
      <p class="text-sm text-white/90">เรายินดีช่วยเหลือคุณทุกปัญหา ตอบกลับภายใน 24 ชั่วโมง</p>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-5 space-y-3">
      <div class="flex items-center gap-2 text-sm text-gray-700">
        <i class="bi bi-envelope text-indigo-500 w-5"></i>
        <span><?php echo e(\App\Models\AppSetting::get('support_email', 'support@example.com')); ?></span>
      </div>
      <?php if($phone = \App\Models\AppSetting::get('footer_contact_phone')): ?>
      <div class="flex items-center gap-2 text-sm text-gray-700">
        <i class="bi bi-telephone text-indigo-500 w-5"></i>
        <span><?php echo e($phone); ?></span>
      </div>
      <?php endif; ?>
      <?php if($line = \App\Models\AppSetting::get('footer_contact_line_id')): ?>
      <div class="flex items-center gap-2 text-sm text-gray-700">
        <i class="bi bi-line text-green-500 w-5"></i>
        <span><?php echo e($line); ?></span>
      </div>
      <?php endif; ?>
    </div>

    <?php if(auth()->guard()->check()): ?>
    <a href="<?php echo e(route('support.index')); ?>" class="block bg-white border border-gray-100 rounded-2xl p-4 hover:border-indigo-200 transition">
      <div class="flex items-center gap-2 text-sm font-semibold text-slate-800">
        <i class="bi bi-ticket-perforated text-indigo-500"></i>
        <span>Tickets ของฉัน</span>
        <i class="bi bi-chevron-right ml-auto text-gray-400"></i>
      </div>
      <p class="text-xs text-gray-500 mt-1">ดูและตอบกลับ tickets ที่สร้างไว้</p>
    </a>
    <?php endif; ?>

    
    <div class="bg-white border border-gray-100 rounded-2xl p-5">
      <h4 class="text-xs font-semibold text-gray-500 uppercase mb-3">เวลาตอบกลับ (SLA)</h4>
      <div class="space-y-2 text-xs">
        <?php $__currentLoopData = \App\Models\ContactMessage::PRIORITIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="flex items-center justify-between">
          <span class="px-2 py-0.5 bg-<?php echo e($p['color']); ?>-100 text-<?php echo e($p['color']); ?>-700 rounded font-medium"><?php echo e($p['label']); ?></span>
          <span class="text-gray-600"><?php echo e($p['sla_hours']); ?> ชั่วโมง</span>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
  </div>

  
  <div class="md:col-span-2">
    <div class="bg-white border border-gray-100 rounded-2xl p-6 md:p-8">
      <div class="mb-5">
        <h2 class="text-2xl font-bold text-slate-800">ส่งคำถาม / รายงานปัญหา</h2>
        <p class="text-sm text-gray-500 mt-1">กรอกแบบฟอร์มด้านล่าง ทีมงานจะตอบกลับโดยเร็ว</p>
      </div>

      <?php if(session('success')): ?>
        <div class="bg-emerald-50 border-l-4 border-emerald-400 rounded-r-lg p-4 mb-4 text-sm text-emerald-800">
          <i class="bi bi-check-circle-fill mr-1"></i><?php echo session('success'); ?>

        </div>
      <?php endif; ?>

      <?php if($errors->any()): ?>
        <div class="bg-red-50 border-l-4 border-red-400 rounded-r-lg p-4 mb-4 text-sm text-red-800">
          <ul class="list-disc list-inside">
            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?php echo e(route('contact.store')); ?>" class="space-y-4">
        <?php echo csrf_field(); ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">ชื่อ <span class="text-red-500">*</span></label>
            <input type="text" name="name" required maxlength="200"
                   value="<?php echo e(old('name', auth()->user()?->first_name ?? '')); ?>"
                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">อีเมล <span class="text-red-500">*</span></label>
            <input type="email" name="email" required
                   value="<?php echo e(old('email', auth()->user()?->email ?? '')); ?>"
                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400">
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">หมวดหมู่ <span class="text-red-500">*</span></label>
            <select name="category" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400">
              <?php $__currentLoopData = \App\Models\ContactMessage::CATEGORIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($k); ?>" <?php echo e(old('category') === $k ? 'selected' : ''); ?>><?php echo e($label); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">ความสำคัญ</label>
            <select name="priority" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400">
              <?php $__currentLoopData = \App\Models\ContactMessage::PRIORITIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if($k === 'low' || $k === 'normal' || auth()->check()): ?>
                <option value="<?php echo e($k); ?>" <?php echo e(old('priority', 'normal') === $k ? 'selected' : ''); ?>><?php echo e($p['label']); ?> (ภายใน <?php echo e($p['sla_hours']); ?>h)</option>
                <?php endif; ?>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">หัวข้อ <span class="text-red-500">*</span></label>
          <input type="text" name="subject" required maxlength="300" value="<?php echo e(old('subject')); ?>"
                 placeholder="สรุปปัญหาหรือคำถามของคุณ"
                 class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400">
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">รายละเอียด <span class="text-red-500">*</span></label>
          <textarea name="message" required rows="6" maxlength="5000"
                    placeholder="อธิบายปัญหาหรือคำถามของคุณโดยละเอียด..."
                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400"><?php echo e(old('message')); ?></textarea>
          <p class="text-xs text-gray-400 mt-1">ยิ่งละเอียด ยิ่งตอบกลับได้เร็วและแม่นยำ</p>
        </div>

        <div class="pt-2">
          <button type="submit" class="w-full py-3 bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-xl font-semibold hover:shadow-lg transition">
            <i class="bi bi-send mr-1"></i>ส่งข้อความ
          </button>
        </div>

        <p class="text-xs text-gray-400 text-center">
          การส่งข้อความแสดงว่าคุณยอมรับนโยบายความเป็นส่วนตัวของเรา
        </p>
      </form>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/public/contact.blade.php ENDPATH**/ ?>