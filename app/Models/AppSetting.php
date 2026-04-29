<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $table = 'app_settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $fillable = ['key','value'];

    /** In-memory store — loaded once per request */
    protected static ?array $allSettings = null;

    /**
     * Load ALL settings into memory at once (single DB/cache hit per request).
     */
    protected static function loadAll(): array
    {
        if (static::$allSettings !== null) {
            return static::$allSettings;
        }

        static::$allSettings = Cache::remember('app_settings_all', 600, function () {
            try {
                return static::pluck('value', 'key')->toArray();
            } catch (\Throwable $e) {
                return [];
            }
        });

        return static::$allSettings;
    }

    public static function get(string $key, $default = null)
    {
        $all = static::loadAll();
        return $all[$key] ?? $default;
    }

    /**
     * Return ALL settings as a flat [key => value] array.
     *
     * Uses the shared in-memory/cache store (single DB hit per request).
     * Prefer this over `AppSetting::all()->pluck('value', 'key')->toArray()`,
     * which issues a fresh, uncached query every call.
     */
    public static function getAll(): array
    {
        return static::loadAll();
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('app_settings_all');
        static::$allSettings = null;
    }

    /**
     * Bulk-write an array of [key => value] settings in a single pass
     * and flush the shared cache exactly once.
     */
    public static function setMany(array $items): void
    {
        foreach ($items as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => (string) ($value ?? '')]);
        }
        Cache::forget('app_settings_all');
        static::$allSettings = null;
    }

    /** Flush the in-memory + cache store (useful after bulk updates). */
    public static function flushCache(): void
    {
        Cache::forget('app_settings_all');
        static::$allSettings = null;
    }
}
