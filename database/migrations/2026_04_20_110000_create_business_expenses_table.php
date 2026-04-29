<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business Expense Tracking — every recurring cost the platform pays.
 *
 * Powers three things:
 *   1. An admin "cost breakdown" dashboard (what we pay, to whom, monthly).
 *   2. A per-service calculator — allocate each expense to one or more
 *      services (events, face_search, downloads, digital_products, …) and
 *      see what % of revenue goes to each cost centre.
 *   3. Break-even / margin analysis — combined with revenue rollups already
 *      computed elsewhere, the admin sees real profitability per service.
 *
 * Design notes
 * ------------
 *   • Currency: values stored in THB on `amount`; if the source invoice is in
 *     another currency the raw value + rate are preserved on `original_*`
 *     columns. `amount` is authoritative — never trust a UI that multiplies
 *     `original_amount * exchange_rate` live.
 *   • Billing cycle: monthly / yearly / one_time / usage_based. Usage-based
 *     rows carry a `unit_cost` (e.g. 0.015 THB per GB-month) and a
 *     `usage_unit` (e.g. GB, request, user). The admin UI prompts for an
 *     estimated usage figure when summing.
 *   • `allocated_to`: JSON array of service slugs. Expense totals are
 *     divided evenly across allocated services unless explicit weights are
 *     provided via `allocation_weights` (JSON object keyed by slug).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_expenses', function (Blueprint $table) {
            $table->id();

            $table->string('category', 40)->index()
                ->comment('infrastructure|saas|storage|bandwidth|cdn|domain|marketing|payment|legal|personnel|ai|other');
            $table->string('name', 150);
            $table->string('provider', 100)->nullable()->comment('Cloudflare, Google, AWS, Stripe, etc.');
            $table->text('description')->nullable();

            // Money
            $table->decimal('amount', 12, 2)->default(0)->comment('THB per billing cycle — authoritative');
            $table->string('currency', 3)->default('THB');
            $table->decimal('original_amount', 12, 2)->nullable()->comment('Invoice amount in source currency');
            $table->string('original_currency', 3)->nullable();
            $table->decimal('exchange_rate', 10, 4)->nullable()->comment('Source → THB, locked at last entry');

            // Cycle + usage
            $table->string('billing_cycle', 20)->default('monthly')
                ->comment('monthly|yearly|one_time|usage_based');
            $table->decimal('unit_cost', 12, 6)->nullable();
            $table->string('usage_unit', 30)->nullable()->comment('GB, request, user, minute, etc.');
            $table->decimal('estimated_monthly_usage', 14, 2)->nullable()
                ->comment('Admin-entered usage estimate for usage_based rows');

            // Allocation
            $table->json('allocated_to')->nullable()
                ->comment('Array of service slugs this expense is attributed to');
            $table->json('allocation_weights')->nullable()
                ->comment('Optional {service_slug: weight} map; defaults to even split');

            // Lifecycle
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_critical')->default(false)
                ->comment('Critical expenses trigger alerts when they fail to renew');

            // Audit
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['category', 'is_active']);
            $table->index(['billing_cycle', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_expenses');
    }
};
