<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\DigitalProduct;
use App\Services\Media\Exceptions\InvalidMediaFileException;
use App\Services\Media\R2MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = DigitalProduct::query()
            ->when($request->q, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'ilike', "%{$s}%")->orWhere('description', 'ilike', "%{$s}%");
            }))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->type, fn($q, $s) => $q->where('product_type', $s))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.products.index', compact('products'));
    }
    public function create() { return view('admin.products.create'); }

    public function store(Request $request)
    {
        $validated = $this->validateProduct($request);

        $validated['slug']        = !empty($validated['slug']) ? $validated['slug'] : Str::slug($validated['name']);
        $validated['status']      = $validated['status'] ?? 'draft';
        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);

        // Defer file uploads until AFTER the product row exists so we can
        // scope the storage paths under digital-products/{id}/…
        $coverFile = $request->hasFile('cover_image') ? $request->file('cover_image') : null;
        $localFile = $request->hasFile('local_file') ? $request->file('local_file') : null;
        unset($validated['cover_image'], $validated['local_file']);

        // Extract drive file id from pasted URL if needed
        if (!empty($validated['drive_file_url']) && empty($validated['drive_file_id'])) {
            $validated['drive_file_id'] = $this->extractDriveId($validated['drive_file_url']);
        }

        $product = DigitalProduct::create($validated);

        // Seller-owned uploads: covers + the actual digital product file go
        // under digital.product_covers and digital.products respectively.
        // For admin-created products that aren't owned by a specific seller
        // we use the acting admin's user_id as the owner — this keeps the
        // path schema regular while still letting GDPR delete-by-user clean
        // up uploads if the admin account is later removed.
        $sellerUserId = (int) ($product->seller_id ?? Auth::id());
        $media = app(R2MediaService::class);

        try {
            if ($coverFile) {
                $upload = $media->uploadDigitalProductCover($sellerUserId, (int) $product->id, $coverFile);
                $product->cover_image = $upload->key;
                $product->save();
            }
            if ($localFile) {
                $upload = $media->uploadDigitalProduct($sellerUserId, (int) $product->id, $localFile);
                $product->local_file  = $upload->key;
                $product->file_size   = $this->humanFileSize($localFile->getSize());
                $product->file_format = strtolower($localFile->getClientOriginalExtension() ?: '');
                $product->save();
            }
        } catch (InvalidMediaFileException $e) {
            Log::warning('Admin product upload rejected', [
                'product_id' => $product->id,
                'reason'     => $e->getMessage(),
            ]);
            return redirect()->route('admin.products.edit', $product)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.products.index')->with('success', 'สร้างสำเร็จ');
    }

    public function show(DigitalProduct $product) { return view('admin.products.show', compact('product')); }
    public function edit(DigitalProduct $product) { return view('admin.products.edit', compact('product')); }

    public function update(Request $request, DigitalProduct $product)
    {
        $validated = $this->validateProduct($request, $product->id);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }
        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);

        $sellerUserId = (int) ($product->seller_id ?? Auth::id());
        $media = app(R2MediaService::class);

        if ($request->hasFile('cover_image')) {
            // Wipe the old cover off R2 (and CDN cache) before replacing.
            if ($product->cover_image) {
                try { $media->delete($product->cover_image); } catch (\Throwable) {}
            }
            try {
                $upload = $media->uploadDigitalProductCover($sellerUserId, (int) $product->id, $request->file('cover_image'));
                $validated['cover_image'] = $upload->key;
            } catch (InvalidMediaFileException $e) {
                return back()->withInput()->withErrors(['cover_image' => $e->getMessage()]);
            }
        } else {
            unset($validated['cover_image']);
        }

        // Digital file upload (replace existing).
        if ($request->hasFile('local_file')) {
            if ($product->local_file) {
                try { $media->delete($product->local_file); } catch (\Throwable) {}
            }
            try {
                $upload = $media->uploadDigitalProduct($sellerUserId, (int) $product->id, $request->file('local_file'));
                $validated['local_file'] = $upload->key;
            } catch (InvalidMediaFileException $e) {
                return back()->withInput()->withErrors(['local_file' => $e->getMessage()]);
            }
            $validated['file_size']  = $this->humanFileSize($request->file('local_file')->getSize());
            $validated['file_format'] = strtolower($request->file('local_file')->getClientOriginalExtension() ?: '');
        } else {
            unset($validated['local_file']);
        }

        if (!empty($validated['drive_file_url']) && empty($validated['drive_file_id'])) {
            $validated['drive_file_id'] = $this->extractDriveId($validated['drive_file_url']);
        }

        $product->update($validated);

        return redirect()->route('admin.products.index')->with('success', 'อัพเดทสำเร็จ');
    }

    public function destroy(DigitalProduct $product) { $product->delete(); return redirect()->route('admin.products.index'); }

    /* ─────────────────── Helpers ─────────────────── */

    protected function validateProduct(Request $request, ?int $productId = null): array
    {
        $slugRule = 'nullable|string|max:255|unique:digital_products,slug' . ($productId ? ',' . $productId : '');

        return $request->validate([
            'name'                 => 'required|string|max:255',
            'slug'                 => $slugRule,
            'description'          => 'nullable|string',
            'short_description'    => 'nullable|string|max:500',
            'price'                => 'required|numeric|min:0',
            'sale_price'           => 'nullable|numeric|min:0',
            'product_type'         => 'required|string|max:100',
            'file_source'          => 'nullable|in:local,drive,direct',
            'drive_file_id'        => 'nullable|string|max:255',
            'drive_file_url'       => 'nullable|url|max:500',
            'direct_url'           => 'nullable|url|max:500',
            // Digital-product file upload — whitelisted MIMEs to block executables
            // (.php, .exe, .sh, .bat, etc.). Covers docs, archives, design, media.
            // Max 500MB. If a new format is needed, add it explicitly here.
            'local_file'           => [
                'nullable',
                'file',
                'max:512000',
                'mimes:pdf,txt,rtf,doc,docx,xls,xlsx,ppt,pptx,'
                  .'zip,rar,7z,tar,gz,'
                  .'jpg,jpeg,png,gif,webp,svg,tiff,bmp,'
                  .'psd,ai,eps,indd,sketch,fig,xd,'
                  .'mp3,wav,flac,ogg,m4a,aac,'
                  .'mp4,mov,avi,mkv,webm,wmv,'
                  .'ttf,otf,woff,woff2,'
                  .'json,xml,csv,yaml,yml,md',
            ],
            'file_format'          => 'nullable|string|max:50',
            'version'              => 'nullable|string|max:50',
            'download_limit'       => 'nullable|integer|min:1|max:999',
            'download_expiry_days' => 'nullable|integer|min:1|max:3650',
            'status'               => 'nullable|in:active,inactive,draft',
            'is_featured'          => 'nullable|boolean',
            'sort_order'           => 'nullable|integer',
            'cover_image'          => 'nullable|image|max:5120',
        ]);
    }

    /**
     * Store a digital product's downloadable file on the `local` disk.
     *
     * Layout:
     *   storage/app/digital-products/{productId}/files/{random}.{ext}
     *
     * The value written back to the `local_file` column is the path
     * RELATIVE to `storage/app/digital-products/` — all readers (Admin +
     * Public ProductController, plus DownloadToken resolvers) prepend
     * `digital-products/` to get the real on-disk path, so legacy rows
     * that stored just a filename continue to work unchanged.
     */
    protected function storeDigitalFile($uploadedFile, ?int $productId = null): string
    {
        $ext      = $uploadedFile->getClientOriginalExtension() ?: 'bin';
        $filename = Str::random(24) . '_' . time() . '.' . $ext;

        if ($productId) {
            // e.g. digital-products/42/files/abc123_1712.pdf
            $folder = "digital-products/{$productId}/files";
            $uploadedFile->storeAs($folder, $filename, 'local');
            // Strip the leading "digital-products/" prefix so readers can keep
            // their existing `digital-products/` + $local_file concatenation.
            return "{$productId}/files/{$filename}";
        }

        // Legacy path (shouldn't happen now that product id is always known).
        $uploadedFile->storeAs('digital-products', $filename, 'local');
        return $filename;
    }

    protected function humanFileSize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1024 * 1024 * 1024) return round($bytes / 1024 / 1024, 2) . ' MB';
        return round($bytes / 1024 / 1024 / 1024, 2) . ' GB';
    }

    protected function extractDriveId(string $url): ?string
    {
        // Common Drive URL patterns:
        // https://drive.google.com/file/d/FILE_ID/view
        // https://drive.google.com/open?id=FILE_ID
        // https://drive.google.com/uc?id=FILE_ID&export=download
        if (preg_match('#/d/([a-zA-Z0-9_-]+)#', $url, $m)) return $m[1];
        if (preg_match('#[?&]id=([a-zA-Z0-9_-]+)#', $url, $m)) return $m[1];
        return null;
    }
}
