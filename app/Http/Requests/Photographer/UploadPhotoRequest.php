<?php

namespace App\Http\Requests\Photographer;

use App\Http\Requests\Concerns\ValidatesFileUploads;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UploadPhotoRequest extends FormRequest
{
    use ValidatesFileUploads;

    public function authorize(): bool
    {
        $user = Auth::user();
        if (!$user || !$user->photographerProfile) {
            return false;
        }

        $event = $this->route('event');
        if (!$event) {
            return false;
        }

        return (int) $event->photographer_id === (int) $user->photographerProfile->id;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'photo' => array_merge(
                ['required'],
                $this->imageRules(maxKb: 20 * 1024),
            ),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'photo.required'   => 'กรุณาเลือกไฟล์ภาพที่ต้องการอัพโหลด',
            'photo.image'      => 'ไฟล์ที่อัพโหลดต้องเป็นรูปภาพเท่านั้น',
            'photo.mimes'      => 'รองรับเฉพาะ JPEG, JPG, PNG, WebP, HEIC',
            'photo.max'        => 'ขนาดไฟล์ต้องไม่เกิน 20 MB',
            'photo.dimensions' => 'รูปภาพต้องมีขนาดอย่างน้อย 200x200 พิกเซล',
        ];
    }

    public function failedAuthorizationMessage(): string
    {
        return 'You do not own this event';
    }
}
