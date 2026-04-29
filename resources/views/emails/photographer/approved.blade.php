@extends('emails.layout', ['title' => 'บัญชีช่างภาพได้รับการอนุมัติ!', 'preheader' => 'เริ่มต้นขายภาพและสร้างรายได้'])

@section('slot')
<h2>ยินดีด้วย! บัญชีได้รับการอนุมัติแล้ว ✅</h2>

<p>สวัสดีช่างภาพ <strong>{{ $name }}</strong>,</p>

<p>🎉 ข่าวดี! บัญชีช่างภาพของคุณที่ <strong>{{ $siteName }}</strong> ได้รับการอนุมัติเรียบร้อยแล้ว!</p>

<div class="alert-box success">
  <p>✨ <strong>คุณสามารถเริ่มใช้งานได้แล้วตั้งแต่ตอนนี้!</strong></p>
</div>

<h3>🚀 เริ่มต้นใช้งานใน 3 ขั้นตอน</h3>

<div class="info-box">
  <p style="margin:4px 0;"><strong>1️⃣ สร้างอีเวนต์แรก</strong> — กำหนดข้อมูล วันที่ สถานที่</p>
  <p style="margin:4px 0;"><strong>2️⃣ อัพโหลดภาพ</strong> — Drag & drop หรือ sync จาก Google Drive</p>
  <p style="margin:4px 0;"><strong>3️⃣ กำหนดราคา</strong> — เลือก Package หรือตั้งราคาเอง</p>
</div>

<div class="btn-wrap">
  <a href="{{ $dashboardUrl }}" class="btn btn-success">🏠 เข้าสู่ Dashboard</a>
</div>

<h3>💡 เคล็ดลับความสำเร็จ</h3>

<div class="info-box highlight">
  <p style="margin:4px 0;">📸 <strong>อัพโหลดภาพคุณภาพสูง</strong> — ขั้นต่ำ 12MP สำหรับภาพขนาดใหญ่</p>
  <p style="margin:4px 0;">🏷️ <strong>ตั้งชื่ออีเวนต์ให้ดี</strong> — เข้ากับ SEO เพื่อให้ค้นเจอง่าย</p>
  <p style="margin:4px 0;">⚡ <strong>อัพโหลดเร็ว</strong> — ภายใน 24 ชั่วโมงหลังอีเวนต์ = ขายดี</p>
  <p style="margin:4px 0;">💬 <strong>ตอบแชทไว</strong> — ความพึงพอใจลูกค้า = รีวิวดี</p>
  <p style="margin:4px 0;">⭐ <strong>สะสมรีวิว</strong> — ช่างภาพที่มีรีวิวเยอะ ขายดีกว่า 3 เท่า</p>
</div>

<p>ทีมงานพร้อมช่วยเหลือคุณทุกขั้นตอน หากต้องการคำแนะนำหรือมีคำถาม สามารถติดต่อเราได้ตลอดเวลา</p>

<p>ขอให้สนุกกับการสร้างรายได้! 💰📸</p>
@endsection
