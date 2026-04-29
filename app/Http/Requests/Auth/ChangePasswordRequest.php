<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_password' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $user = Auth::user();
                    if (!$user || !Hash::check($value, $user->password_hash ?? $user->password ?? '')) {
                        $fail('รหัสผ่านปัจจุบันไม่ถูกต้อง');
                    }
                },
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(10)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
                'different:current_password',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'password.confirmed'         => 'รหัสผ่านยืนยันไม่ตรงกัน',
            'password.different'         => 'รหัสผ่านใหม่ต้องไม่เหมือนรหัสปัจจุบัน',
            'password.min'               => 'รหัสผ่านต้องมีอย่างน้อย 10 ตัวอักษร',
            'password.letters'           => 'รหัสผ่านต้องมีตัวอักษร',
            'password.mixed_case'        => 'รหัสผ่านต้องมีทั้งตัวพิมพ์ใหญ่และพิมพ์เล็ก',
            'password.numbers'           => 'รหัสผ่านต้องมีตัวเลข',
            'password.symbols'           => 'รหัสผ่านต้องมีอักขระพิเศษ',
            'password.uncompromised'     => 'รหัสผ่านนี้เคยรั่วไหลในเหตุการณ์ data breach แล้ว — กรุณาเปลี่ยนเป็นรหัสอื่น',
        ];
    }
}
