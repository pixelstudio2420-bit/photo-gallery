<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'category_id'      => 'required|exists:event_categories,id',
            'event_date'       => 'required|date',
            'location'         => 'nullable|string|max:255',
            'drive_folder_id'  => 'nullable|string|max:255',
            'drive_folder_url' => 'nullable|url',
            'cover_image'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'status'           => 'required|in:draft,active,inactive,archived',
            'watermark'        => 'nullable|boolean',
        ];
    }
}
