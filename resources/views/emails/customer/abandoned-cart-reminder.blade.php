@extends('emails.layout', ['title' => 'คุณยังมีสินค้าในตะกร้า', 'preheader' => 'กลับมาสานต่อการช้อปปิ้ง'])

@section('slot')
<h2>📸 คุณลืมของในตะกร้า!</h2>

<p>สวัสดี <strong>{{ $name }}</strong>,</p>

<p>เราเห็นว่าคุณได้เลือกภาพใน <strong>{{ $siteName }}</strong> แต่ยังไม่ได้ทำรายการให้เสร็จ ภาพสวยๆ กำลังรอคุณอยู่นะ!</p>

<div class="info-box highlight">
  <div class="info-row">
    <span class="label">จำนวนสินค้าในตะกร้า</span>
    <span class="value">{{ (int) ($itemCount ?? 0) }} รายการ</span>
  </div>
  <div class="info-row total">
    <span class="label">ยอดรวมโดยประมาณ</span>
    <span class="value">฿{{ number_format((float) ($total ?? 0), 2) }}</span>
  </div>
</div>

@if(!empty($items) && is_array($items))
  <h3>รายการในตะกร้า</h3>
  <table class="items-table">
    <thead>
      <tr>
        <th>สินค้า</th>
        <th class="text-right">จำนวน</th>
        <th class="text-right">ราคา</th>
      </tr>
    </thead>
    <tbody>
      @foreach(array_slice($items, 0, 5) as $item)
        <tr>
          <td>{{ $item['name'] ?? 'Photo' }}</td>
          <td class="text-right">{{ (int) ($item['quantity'] ?? 1) }}</td>
          <td class="text-right">฿{{ number_format((float) ($item['price'] ?? 0), 2) }}</td>
        </tr>
      @endforeach
      @if(count($items) > 5)
        <tr>
          <td colspan="3" style="text-align:center; color:#9ca3af; font-size:13px;">
            และอีก {{ count($items) - 5 }} รายการ...
          </td>
        </tr>
      @endif
    </tbody>
  </table>
@endif

<div class="btn-wrap">
  <a href="{{ $recoveryUrl }}" class="btn">กลับไปสานต่อ →</a>
</div>

<div class="alert-box">
  <p>💡 <strong>ทำรายการได้ง่ายๆ:</strong> ตะกร้าของคุณถูกบันทึกไว้แล้ว เพียงคลิกปุ่มด้านบนเพื่อกลับไปชำระเงินต่อ</p>
</div>

<p>ภาพสวยๆ รอคุณอยู่! คลิกเพื่อกลับไปดู และอย่าพลาดช่วงเวลาดีๆ นะ 📷✨</p>
@endsection
