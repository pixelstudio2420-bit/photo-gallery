<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DigitalProduct extends Model
{
    protected $table = 'digital_products';
    protected $fillable = ['name','slug','description','short_description','price','sale_price','cover_image','gallery_images','product_type','file_source','drive_file_id','drive_file_url','direct_url','local_file','file_size','file_format','version','compatibility','features','requirements','demo_url','download_limit','download_expiry_days','total_sales','total_revenue','status','is_featured','sort_order'];
    protected $casts = ['price'=>'decimal:2','sale_price'=>'decimal:2','total_revenue'=>'decimal:2','gallery_images'=>'array','features'=>'array','is_featured'=>'boolean'];
    public function getCoverImageUrlAttribute()
    {
        if (!$this->cover_image) return null;
        if (str_starts_with($this->cover_image, 'http')) return $this->cover_image;
        return asset('storage/' . $this->cover_image);
    }
    public function orders() { return $this->hasMany(DigitalOrder::class,'product_id'); }
    public function scopeActive($q) { return $q->where('status','active'); }
    public function getCurrentPriceAttribute() { return $this->sale_price ?? $this->price; }

    /**
     * Cascade-delete the cover, gallery images, and downloadable file when
     * the product row goes away. Covers both the new per-product layout
     * (`digital-products/{id}/…`) and legacy flat paths (pre-migration rows).
     *
     *   public disk  → cover_image, gallery_images[*] (customer-facing assets)
     *   local disk   → local_file (the actual product binary, outside web root)
     *   cloud drivers (R2/S3) → purged via the per-product tree wipe
     */
    protected static function booted(): void
    {
        static::deleting(function (self $product) {
            $publicDisk = \Illuminate\Support\Facades\Storage::disk('public');
            $localDisk  = \Illuminate\Support\Facades\Storage::disk('local');

            // Cover image — single file on public disk
            if ($product->cover_image) {
                try { $publicDisk->delete($product->cover_image); }
                catch (\Throwable) {}
            }

            // Gallery — array column; each entry is a public-disk path
            $gallery = is_array($product->gallery_images) ? $product->gallery_images : [];
            foreach ($gallery as $img) {
                if (!is_string($img) || $img === '') continue;
                try { $publicDisk->delete($img); }
                catch (\Throwable) {}
            }

            // Local file — on the `local` (non-public) disk under
            // `digital-products/`. Column stores the relative path inside
            // that folder for both legacy and new layouts.
            if ($product->local_file) {
                try { $localDisk->delete('digital-products/' . $product->local_file); }
                catch (\Throwable) {}
            }

            // Also purge the whole per-product tree on cloud drivers (cover
            // mirrors, gallery copies, etc.). Safe no-op when nothing is there.
            try {
                app(\App\Services\StorageManager::class)
                    ->purgeDirectory("digital-products/{$product->id}");
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    "DigitalProduct#{$product->id} directory purge failed: " . $e->getMessage()
                );
            }
        });
    }
}
