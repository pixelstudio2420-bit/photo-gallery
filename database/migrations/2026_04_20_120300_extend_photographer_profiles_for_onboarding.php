<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends `photographer_profiles` with the extra fields the onboarding wizard
 * needs without changing existing behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photographer_profiles', function (Blueprint $t) {
            // Extended status progression beyond simple approved/pending
            if (!Schema::hasColumn('photographer_profiles', 'onboarding_stage')) {
                $t->string('onboarding_stage', 20)->default('draft')->after('status');
                // draft | submitted | under_review | approved | contract_signed | active | rejected
            }
            if (!Schema::hasColumn('photographer_profiles', 'id_card_path')) {
                $t->string('id_card_path', 500)->nullable()->after('avatar');
            }
            if (!Schema::hasColumn('photographer_profiles', 'portfolio_samples')) {
                $t->json('portfolio_samples')->nullable()->after('portfolio_url');
                // Stored as array of relative image paths.
            }
            if (!Schema::hasColumn('photographer_profiles', 'phone')) {
                $t->string('phone', 30)->nullable()->after('display_name');
            }
            if (!Schema::hasColumn('photographer_profiles', 'specialties')) {
                $t->json('specialties')->nullable()->after('bio');
                // ['wedding', 'event', 'sport', ...]
            }
            if (!Schema::hasColumn('photographer_profiles', 'years_experience')) {
                $t->unsignedTinyInteger('years_experience')->nullable()->after('specialties');
            }
            if (!Schema::hasColumn('photographer_profiles', 'contract_signed_at')) {
                $t->timestamp('contract_signed_at')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('photographer_profiles', 'contract_signer_ip')) {
                $t->string('contract_signer_ip', 45)->nullable()->after('contract_signed_at');
            }
            if (!Schema::hasColumn('photographer_profiles', 'rejection_reason')) {
                $t->string('rejection_reason', 500)->nullable()->after('contract_signer_ip');
            }
        });
    }

    public function down(): void
    {
        Schema::table('photographer_profiles', function (Blueprint $t) {
            foreach ([
                'onboarding_stage', 'id_card_path', 'portfolio_samples',
                'phone', 'specialties', 'years_experience',
                'contract_signed_at', 'contract_signer_ip', 'rejection_reason',
            ] as $col) {
                if (Schema::hasColumn('photographer_profiles', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
