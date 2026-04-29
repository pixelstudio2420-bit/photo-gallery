<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single recurring (or one-off) operating cost paid by the business.
 *
 * See the migration doc-block for the full schema rationale. This model
 * adds derived helpers the admin UI consumes:
 *
 *   monthlyCost()            — normalised monthly THB cost no matter the cycle
 *   perServiceAllocation()   — map of service_slug → THB/month
 *   isRecurring()            — monthly | yearly | usage_based
 *
 * It also owns the canonical `serviceSlugs()` static method — a single list of
 * allocatable service keys used by the controller, calculator and seeder.
 */
class BusinessExpense extends Model
{
    protected $table = 'business_expenses';

    protected $fillable = [
        'category', 'name', 'provider', 'description',
        'amount', 'currency', 'original_amount', 'original_currency', 'exchange_rate',
        'billing_cycle', 'unit_cost', 'usage_unit', 'estimated_monthly_usage',
        'allocated_to', 'allocation_weights',
        'start_date', 'end_date', 'is_active', 'is_critical',
        'notes',
    ];

    protected $casts = [
        'amount'                  => 'decimal:2',
        'original_amount'         => 'decimal:2',
        'exchange_rate'           => 'decimal:4',
        'unit_cost'               => 'decimal:6',
        'estimated_monthly_usage' => 'decimal:2',
        'allocated_to'            => 'array',
        'allocation_weights'      => 'array',
        'start_date'              => 'date',
        'end_date'                => 'date',
        'is_active'               => 'boolean',
        'is_critical'             => 'boolean',
    ];

    // ─── Scopes ───────────────────────────────────────────────────────

    public function scopeActive($q)
    {
        return $q->where('is_active', true)
                 ->where(function ($sub) {
                     $sub->whereNull('end_date')->orWhere('end_date', '>=', now());
                 });
    }

    public function scopeByCategory($q, string $category)
    {
        return $q->where('category', $category);
    }

    public function scopeCritical($q)
    {
        return $q->where('is_critical', true);
    }

    // ─── Canonical lists ──────────────────────────────────────────────

    /** All categories recognised by the UI. Change here = change everywhere. */
    public static function categories(): array
    {
        return [
            'infrastructure' => 'โครงสร้างพื้นฐาน / Infrastructure',
            'saas'           => 'บริการภายนอก / SaaS',
            'storage'        => 'พื้นที่เก็บข้อมูล / Storage',
            'bandwidth'      => 'แบนด์วิดท์ / Bandwidth',
            'cdn'            => 'CDN',
            'domain'         => 'โดเมน / Domain & SSL',
            'ai'             => 'AI Services (Rekognition, Moderation)',
            'payment'        => 'Payment Gateway',
            'marketing'      => 'การตลาด / Marketing',
            'legal'          => 'กฎหมาย / Legal',
            'accounting'     => 'บัญชี / Accounting',
            'personnel'      => 'บุคลากร / Personnel',
            'other'          => 'อื่นๆ / Other',
        ];
    }

    /** Service slugs an expense can be allocated to. */
    public static function serviceSlugs(): array
    {
        return [
            'events'           => 'อีเวนต์ / Event Galleries',
            'photos'           => 'รูปภาพ / Photo Uploads & Storage',
            'face_search'      => 'ค้นหาใบหน้า / Face Search',
            'downloads'        => 'ดาวน์โหลด / Customer Downloads',
            'digital_products' => 'สินค้าดิจิทัล / Digital Products',
            'payments'         => 'ระบบชำระเงิน / Payments',
            'blog'             => 'บล็อก / Blog & SEO',
            'chat'             => 'แชต / Live Chat',
            'notifications'    => 'แจ้งเตือน / Notifications (Email, SMS)',
            'admin'            => 'ระบบหลังบ้าน / Admin Backoffice',
            'shared'           => 'ใช้ร่วมทุกบริการ / Shared Overhead',
        ];
    }

    public static function billingCycles(): array
    {
        return [
            'monthly'      => 'รายเดือน / Monthly',
            'yearly'       => 'รายปี / Yearly',
            'one_time'     => 'จ่ายครั้งเดียว / One-Time',
            'usage_based'  => 'ตามการใช้งาน / Usage-Based',
        ];
    }

    // ─── Derived / calculator helpers ─────────────────────────────────

    /**
     * Normalised monthly THB cost, regardless of billing cycle.
     *
     *   monthly      → amount
     *   yearly       → amount / 12
     *   one_time     → 0  (entered for bookkeeping but not recurring)
     *   usage_based  → unit_cost × estimated_monthly_usage
     */
    public function monthlyCost(): float
    {
        $amount = (float) $this->amount;
        switch ($this->billing_cycle) {
            case 'yearly':
                return round($amount / 12, 2);
            case 'one_time':
                return 0.0;
            case 'usage_based':
                $unit = (float) $this->unit_cost;
                $use  = (float) $this->estimated_monthly_usage;
                return round($unit * $use, 2);
            case 'monthly':
            default:
                return round($amount, 2);
        }
    }

    /** Annualised THB cost. one_time expenses surface here for reference. */
    public function yearlyCost(): float
    {
        return round($this->monthlyCost() * 12, 2);
    }

    public function isRecurring(): bool
    {
        return in_array($this->billing_cycle, ['monthly', 'yearly', 'usage_based'], true);
    }

    /**
     * Returns how this expense's monthly cost is split across the allocated
     * services. By default the split is even; when `allocation_weights` is
     * set the values are normalised to sum to 1.
     *
     * @return array<string,float> e.g. ['face_search' => 450.00, 'events' => 450.00]
     */
    public function perServiceAllocation(): array
    {
        $monthly  = $this->monthlyCost();
        $services = is_array($this->allocated_to) ? $this->allocated_to : [];
        if (empty($services) || $monthly <= 0) {
            return ['shared' => $monthly];
        }

        $weights = is_array($this->allocation_weights) ? $this->allocation_weights : [];
        // Keep only weights for services actually listed in allocated_to
        $weights = array_filter(
            $weights,
            fn($k) => in_array($k, $services, true),
            ARRAY_FILTER_USE_KEY
        );

        if (!empty($weights) && array_sum($weights) > 0) {
            $total = array_sum($weights);
            $out = [];
            foreach ($services as $s) {
                $w = (float) ($weights[$s] ?? 0);
                $out[$s] = round($monthly * ($w / $total), 2);
            }
            return $out;
        }

        // Even split
        $share = $monthly / count($services);
        $out = [];
        foreach ($services as $s) {
            $out[$s] = round($share, 2);
        }
        return $out;
    }

    /** Human-readable THB string for the admin UI. */
    public function formattedMonthly(): string
    {
        return number_format($this->monthlyCost(), 2) . ' THB/เดือน';
    }
}
