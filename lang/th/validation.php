<?php

return [
    'accepted'             => ':attribute ต้องได้รับการยอมรับ',
    'between'              => [
        'numeric' => ':attribute ต้องอยู่ระหว่าง :min ถึง :max',
        'string'  => ':attribute ต้องมีความยาว :min ถึง :max ตัวอักษร',
    ],
    'confirmed'            => ':attribute ยืนยันไม่ตรงกัน',
    'email'                => ':attribute ต้องเป็นรูปแบบอีเมลที่ถูกต้อง',
    'max'                  => [
        'numeric' => ':attribute ต้องไม่มากกว่า :max',
        'string'  => ':attribute ต้องมีไม่เกิน :max ตัวอักษร',
    ],
    'min'                  => [
        'numeric' => ':attribute ต้องมีอย่างน้อย :min',
        'string'  => ':attribute ต้องมีอย่างน้อย :min ตัวอักษร',
    ],
    'numeric'              => ':attribute ต้องเป็นตัวเลข',
    'required'             => 'กรุณากรอก :attribute',
    'string'               => ':attribute ต้องเป็นสตริง',
    'unique'               => ':attribute นี้ถูกใช้งานแล้ว',
    'image'                => ':attribute ต้องเป็นรูปภาพ',
    'mimes'                => ':attribute ต้องเป็นไฟล์ชนิด: :values',
    'exists'               => ':attribute ที่เลือกไม่ถูกต้อง',
    'in'                   => ':attribute ที่เลือกไม่ถูกต้อง',

    'attributes' => [
        'email'      => 'อีเมล',
        'password'   => 'รหัสผ่าน',
        'name'       => 'ชื่อ',
        'first_name' => 'ชื่อจริง',
        'last_name'  => 'นามสกุล',
        'phone'      => 'เบอร์โทรศัพท์',
        'username'   => 'ชื่อผู้ใช้',
        'title'      => 'ชื่อ',
        'description' => 'คำอธิบาย',
        'amount'     => 'จำนวนเงิน',
    ],
];
