<?php

namespace Tests\Unit;

use App\Http\Requests\Concerns\ValidatesFileUploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the file-upload validation trait:
 *   - Image rule rejects oversize / wrong-mime / tiny dim
 *   - Document rule rejects unauthorized types
 *   - Avatar rule enforces max dimensions
 */
class ValidatesFileUploadsTraitTest extends TestCase
{
    private object $host;

    protected function setUp(): void
    {
        parent::setUp();
        // anonymous class consumes the trait so we can call protected helpers
        $this->host = new class {
            use ValidatesFileUploads {
                imageRules       as public publicImageRules;
                documentRules    as public publicDocumentRules;
                avatarRules      as public publicAvatarRules;
                paymentSlipRules as public publicPaymentSlipRules;
            }
        };
    }

    public function test_image_rules_include_mimetype_and_dimensions(): void
    {
        $rules = $this->host->publicImageRules();

        $this->assertContains('image', $rules);
        $this->assertContains('mimes:jpeg,jpg,png,webp,heic,heif', $rules);
        $this->assertContains('mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif', $rules);
        $this->assertContains('max:10240', $rules);
        $this->assertContains('dimensions:min_width=200,min_height=200', $rules);
    }

    public function test_image_rules_respect_custom_max_kb(): void
    {
        $rules = $this->host->publicImageRules(maxKb: 20480, minDim: 100);

        $this->assertContains('max:20480', $rules);
        $this->assertContains('dimensions:min_width=100,min_height=100', $rules);
    }

    public function test_avatar_rules_have_max_dimensions(): void
    {
        $rules = $this->host->publicAvatarRules();

        $this->assertContains('dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000', $rules);
        $this->assertContains('max:2048', $rules);
    }

    public function test_document_rules_reject_php_extension(): void
    {
        $rules = $this->host->publicDocumentRules();
        $allowedMimes = collect($rules)->first(fn ($r) => str_starts_with($r, 'mimes:'));

        $this->assertStringNotContainsString('php', $allowedMimes);
        $this->assertStringNotContainsString('exe', $allowedMimes);
        $this->assertStringNotContainsString('sh', $allowedMimes);
    }

    public function test_payment_slip_rules_allow_pdf_and_image_only(): void
    {
        $rules = $this->host->publicPaymentSlipRules();
        $mimes = collect($rules)->first(fn ($r) => str_starts_with($r, 'mimes:'));

        $this->assertStringContainsString('pdf', $mimes);
        $this->assertStringContainsString('jpeg', $mimes);
        $this->assertStringNotContainsString('exe', $mimes);
        $this->assertStringNotContainsString('zip', $mimes);
    }
}
