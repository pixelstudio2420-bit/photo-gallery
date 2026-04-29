<?php $__env->startSection('slot'); ?>
<h2>ภาพของคุณพร้อมดาวน์โหลดแล้ว! 📸</h2>

<p>สวัสดี <strong><?php echo e($name); ?></strong>,</p>

<p>ขอบคุณที่สั่งซื้อกับเรา! ภาพทั้งหมดจากอีเวนต์ที่คุณสั่งซื้อพร้อมให้ดาวน์โหลดแล้ว</p>

<div class="info-box highlight">
  <div class="info-row">
    <span class="label">เลขที่คำสั่งซื้อ</span>
    <span class="value">#<?php echo e($orderNumber ?? $orderId); ?></span>
  </div>
  <?php if(!empty($eventName)): ?>
  <div class="info-row">
    <span class="label">อีเวนต์</span>
    <span class="value"><?php echo e($eventName); ?></span>
  </div>
  <?php endif; ?>
  <div class="info-row">
    <span class="label">จำนวนภาพ</span>
    <span class="value"><?php echo e($photoCount ?? 0); ?> ภาพ</span>
  </div>
  <div class="info-row">
    <span class="label">ลิงก์หมดอายุ</span>
    <span class="value" style="color:#ef4444;"><?php echo e($expiresAt ?? now()->addDays(7)->format('d/m/Y H:i')); ?></span>
  </div>
</div>

<div class="btn-wrap">
  <a href="<?php echo e($downloadUrl); ?>" class="btn btn-success">📥 ดาวน์โหลดภาพทั้งหมด</a>
</div>

<div class="alert-box warning">
  <p>⏰ <strong>กรุณาดาวน์โหลดภายใน 7 วัน</strong> ก่อนที่ลิงก์จะหมดอายุ</p>
</div>

<h3>💡 คำแนะนำการดาวน์โหลด</h3>

<div class="info-box">
  <p style="margin:4px 0;">✅ <strong>ดาวน์โหลดด้วย WiFi</strong> — ไฟล์มีขนาดใหญ่ อาจใช้เน็ตเยอะ</p>
  <p style="margin:4px 0;">✅ <strong>บันทึกหลายที่</strong> — แนะนำให้เก็บใน Cloud Drive หรือ External Drive</p>
  <p style="margin:4px 0;">✅ <strong>ดาวน์โหลดได้หลายครั้ง</strong> — ภายในระยะเวลาที่กำหนด</p>
  <p style="margin:4px 0;">✅ <strong>คุณภาพสูง</strong> — ไฟล์ต้นฉบับความละเอียดเต็ม ไม่มีลายน้ำ</p>
</div>

<p style="font-size:13px;color:#6b7280;">
  📞 หากพบปัญหาในการดาวน์โหลด กรุณาติดต่อทีมงานเราได้ทันที<br>
  🔒 ลิงก์นี้เป็นลิงก์ส่วนตัว กรุณาอย่าแชร์ให้ผู้อื่น
</p>

<p>ขอบคุณที่ไว้วางใจใช้บริการ <strong><?php echo e($siteName); ?></strong>! ❤️</p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layout', ['title' => 'ภาพพร้อมดาวน์โหลด', 'preheader' => 'ดาวน์โหลดภาพของคุณได้แล้ว!'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/emails/customer/download-ready.blade.php ENDPATH**/ ?>