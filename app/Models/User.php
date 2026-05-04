<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'auth_users';
    protected $fillable = ['username','first_name','last_name','email','password_hash','phone','avatar','auth_provider','provider_id','status','email_verified','email_verified_at','last_login_at','login_count',
        // Geo for personalised popups + email digest (Phase 4)
        'province_id','district_id','subdistrict_id'];
    // remember_token never needs to leave the server — it's a server-side
    // cookie-validation secret, not user-facing data. Same treatment as
    // password_hash: kept on the model for Laravel's auth machinery to
    // read/write via getRememberToken / setRememberToken, but excluded
    // from any JSON serialisation (API responses, email templates that
    // dump the user object, etc.).
    protected $hidden = ['password_hash', 'remember_token'];
    protected $casts = ['email_verified'=>'boolean','email_verified_at'=>'datetime','last_login_at'=>'datetime','created_at'=>'datetime','updated_at'=>'datetime'];

    public function getAuthPassword() { return $this->password_hash; }
    public function socialLogins() { return $this->hasMany(SocialLogin::class,'user_id'); }
    public function photographerProfile() { return $this->hasOne(PhotographerProfile::class,'user_id'); }
    public function orders() { return $this->hasMany(Order::class,'user_id'); }
    public function reviews() { return $this->hasMany(Review::class,'user_id'); }
    public function wishlists() { return $this->hasMany(Wishlist::class,'user_id'); }
    public function notifications() { return $this->hasMany(UserNotification::class,'user_id'); }
    public function chatConversations() { return $this->hasMany(ChatConversation::class,'user_id'); }

    // ─── Consumer cloud-storage relations ────────────────────────────────
    public function storageSubscriptions() { return $this->hasMany(UserStorageSubscription::class, 'user_id'); }
    public function currentStorageSubscription() { return $this->belongsTo(UserStorageSubscription::class, 'current_storage_sub_id'); }
    public function storageInvoices() { return $this->hasMany(UserStorageInvoice::class, 'user_id'); }
    public function userFolders() { return $this->hasMany(UserFolder::class, 'user_id'); }
    public function userFiles() { return $this->hasMany(UserFile::class, 'user_id'); }

    public function getFullNameAttribute() { return trim($this->first_name.' '.$this->last_name); }

    /**
     * Alias for full_name — lets downstream code use `$user->name` uniformly
     * (Laravel convention) without every caller knowing about the split
     * first_name/last_name columns. Falls back to username/email if both
     * name parts are blank so we never render an empty string.
     */
    public function getNameAttribute()
    {
        $full = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
        if ($full !== '') return $full;
        return $this->username ?: ($this->email ?: ('user#'.$this->id));
    }
    public function isPhotographer() { return $this->photographerProfile()->exists(); }

    /**
     * Purge avatar + any user-owned upload directories when the user row is
     * permanently deleted. Skips external URLs (social logins return a
     * provider-hosted photo URL) — we don't want to try to "delete" gravatar.
     * The tree purge covers both the customer layout (`customers/{id}/…`)
     * and the generic users fallback (`users/{id}/…`).
     */
    protected static function booted(): void
    {
        static::deleting(function (self $user) {
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
                try { $disk->delete($user->avatar); } catch (\Throwable) {}
            }
            try {
                $storage = app(\App\Services\StorageManager::class);
                $storage->purgeDirectory("users/{$user->id}");
                $storage->purgeDirectory("customers/{$user->id}");
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    "User#{$user->id} directory purge failed: " . $e->getMessage()
                );
            }
        });
    }
}
