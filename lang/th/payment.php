<?php

return [
    // Payment
    'payment'               => 'การชำระเงิน',
    'payment_method'        => 'วิธีชำระเงิน',
    'select_payment_method' => 'เลือกวิธีชำระเงิน',
    'payment_details'       => 'รายละเอียดการชำระเงิน',
    'amount_to_pay'         => 'ยอดที่ต้องชำระ',

    // Methods
    'promptpay'             => 'พร้อมเพย์',
    'bank_transfer'         => 'โอนเงินผ่านธนาคาร',
    'credit_card'           => 'บัตรเครดิต/เดบิต',
    'true_money'            => 'TrueMoney Wallet',
    'line_pay'              => 'LINE Pay',
    'paypal'                => 'PayPal',
    'omise'                 => 'บัตรเครดิต (Omise)',
    'stripe'                => 'บัตรเครดิต (Stripe)',

    // PromptPay
    'scan_qr_code'          => 'สแกน QR Code',
    'qr_amount'             => 'ยอด :amount',
    'qr_expires_in'         => 'หมดอายุใน :time',
    'after_payment_upload_slip' => 'หลังจากโอนเงินแล้ว กรุณาอัปโหลดสลิปด้านล่าง',

    // Bank Transfer
    'bank_details'          => 'รายละเอียดบัญชี',
    'bank_name'             => 'ชื่อธนาคาร',
    'account_number'        => 'เลขบัญชี',
    'account_name'          => 'ชื่อบัญชี',
    'copy_account_number'   => 'คัดลอกเลขบัญชี',
    'copied'                => 'คัดลอกแล้ว!',

    // Slip upload
    'upload_slip'           => 'อัปโหลดสลิป',
    'upload_slip_hint'      => 'อัปโหลดสลิปหลังจากโอนเงินเพื่อยืนยันการชำระเงิน',
    'select_file'           => 'เลือกไฟล์',
    'drag_drop_slip'        => 'ลากและวางสลิปที่นี่',
    'slip_requirements'     => 'รองรับไฟล์: JPG, PNG ขนาดไม่เกิน 5MB',
    'transfer_amount'       => 'ยอดโอน',
    'transfer_date'         => 'วันที่โอน',
    'ref_code'              => 'เลขอ้างอิง',
    'ref_code_optional'     => 'เลขอ้างอิง (ถ้ามี)',
    'submit_slip'           => 'ส่งสลิป',
    'slip_submitted'        => 'ส่งสลิปเรียบร้อย',
    'slip_verifying'        => 'กำลังตรวจสอบสลิป...',
    'slip_approved'         => 'สลิปได้รับการอนุมัติ',
    'slip_rejected'         => 'สลิปไม่ผ่านการตรวจสอบ',

    // Status
    'payment_pending'       => 'รอการชำระเงิน',
    'payment_success'       => 'ชำระเงินสำเร็จ',
    'payment_failed'        => 'ชำระเงินไม่สำเร็จ',
    'payment_expired'       => 'การชำระเงินหมดอายุ',
    'payment_cancelled'     => 'ยกเลิกการชำระเงิน',
    'payment_refunded'      => 'คืนเงินแล้ว',

    // Messages
    'pay_now'               => 'ชำระเงินตอนนี้',
    'payment_success_message' => 'ขอบคุณสำหรับการซื้อ! ภาพของคุณพร้อมดาวน์โหลดแล้ว',
    'payment_failed_message' => 'การชำระเงินไม่สำเร็จ กรุณาลองอีกครั้ง',
    'check_email'           => 'กรุณาตรวจสอบอีเมลของคุณสำหรับการยืนยันการชำระเงิน',

    // Security notice
    'secure_payment'        => 'การชำระเงินปลอดภัย',
    'ssl_encrypted'         => 'เข้ารหัส SSL',
    'data_protected'        => 'ข้อมูลของคุณปลอดภัย',
];
