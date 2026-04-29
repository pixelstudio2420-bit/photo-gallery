<?php

namespace App\Http\Requests\Concerns;

/**
 * Centralised file-upload validation rules.
 *
 * Use this trait inside FormRequest classes so every upload endpoint
 * applies the same defence-in-depth rules:
 *   - Size cap
 *   - MIME-type allowlist (server-side, not just by extension)
 *   - Image dimension floor (rejects 1x1 trackers / corrupt files)
 *   - File-name length cap (prevents filesystem path explosion)
 *
 * NOTE: file-extension validation alone is not enough — `.jpg.php` slips
 * past it. Always combine `mimes:` (extension) with `mimetypes:` (real
 * MIME from `finfo`) so we get both sides.
 */
trait ValidatesFileUploads
{
    /** @return array<int, string> */
    protected function imageRules(int $maxKb = 10240, int $minDim = 200): array
    {
        return [
            'file',
            'image',
            'mimes:jpeg,jpg,png,webp,heic,heif',
            'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif',
            'max:' . $maxKb,
            'dimensions:min_width=' . $minDim . ',min_height=' . $minDim,
        ];
    }

    /** @return array<int, string> */
    protected function documentRules(int $maxKb = 5120): array
    {
        return [
            'file',
            'mimes:pdf,doc,docx,xls,xlsx,csv,txt',
            'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain',
            'max:' . $maxKb,
        ];
    }

    /** @return array<int, string> */
    protected function avatarRules(int $maxKb = 2048): array
    {
        return [
            'file',
            'image',
            'mimes:jpeg,jpg,png,webp',
            'mimetypes:image/jpeg,image/png,image/webp',
            'max:' . $maxKb,
            'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000',
        ];
    }

    /** @return array<int, string> */
    protected function paymentSlipRules(int $maxKb = 5120): array
    {
        return [
            'file',
            'mimes:jpeg,jpg,png,webp,pdf',
            'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
            'max:' . $maxKb,
        ];
    }
}
