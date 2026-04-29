<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /* ──────────────────────────── Categories ──────────────────────────── */
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->string('meta_title', 255)->nullable();
            $table->text('meta_description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('color', 20)->default('#6366f1');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('post_count')->default(0);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('blog_categories')->nullOnDelete();
        });

        /* ──────────────────────────── Tags ──────────────────────────── */
        Schema::create('blog_tags', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->unsignedInteger('post_count')->default(0);
            $table->timestamps();
        });

        /* ──────────────────────────── Posts ──────────────────────────── */
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 500);
            $table->string('slug', 500)->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('featured_image', 500)->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'published', 'archived'])->default('draft');
            $table->enum('visibility', ['public', 'private', 'password'])->default('public');
            $table->string('post_password', 255)->nullable();
            $table->string('meta_title', 255)->nullable();
            $table->text('meta_description')->nullable();
            $table->string('og_image', 500)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->string('focus_keyword', 255)->nullable();
            $table->json('secondary_keywords')->nullable();
            $table->string('schema_type', 50)->default('Article');
            $table->unsignedInteger('reading_time')->default(0);
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedTinyInteger('seo_score')->default(0);
            $table->unsignedTinyInteger('readability_score')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_affiliate_post')->default(false);
            $table->boolean('allow_comments')->default(true);
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('share_count')->default(0);
            $table->json('table_of_contents')->nullable();
            $table->json('internal_links')->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->string('ai_provider', 50)->nullable();
            $table->string('ai_model', 100)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('last_modified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('category_id');
            $table->index('published_at');
            $table->index('is_featured');
            $table->index('focus_keyword');

            $table->foreign('category_id')->references('id')->on('blog_categories')->nullOnDelete();
        });

        /* ──────────────────────────── Post-Tag Pivot ──────────────────────────── */
        Schema::create('blog_post_tags', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('tag_id');

            $table->unique(['post_id', 'tag_id']);

            $table->foreign('post_id')->references('id')->on('blog_posts')->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('blog_tags')->cascadeOnDelete();
        });

        /* ──────────────────────────── Affiliate Links ──────────────────────────── */
        Schema::create('blog_affiliate_links', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->string('slug', 100)->unique();
            $table->text('destination_url');
            $table->string('provider', 100)->nullable();
            $table->string('campaign', 100)->nullable();
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('image', 500)->nullable();
            $table->boolean('nofollow')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('total_clicks')->default(0);
            $table->unsignedInteger('total_conversions')->default(0);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        /* ──────────────────────────── Affiliate Clicks ──────────────────────────── */
        Schema::create('blog_affiliate_clicks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('affiliate_link_id');
            $table->unsignedBigInteger('post_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('referrer', 500)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('device_type', 20)->nullable();
            $table->timestamp('clicked_at');

            $table->index('affiliate_link_id');
            $table->index('post_id');
            $table->index('clicked_at');

            $table->foreign('affiliate_link_id')->references('id')->on('blog_affiliate_links')->cascadeOnDelete();
            $table->foreign('post_id')->references('id')->on('blog_posts')->nullOnDelete();
        });

        /* ──────────────────────────── CTA Buttons ──────────────────────────── */
        Schema::create('blog_cta_buttons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->string('label', 255);
            $table->string('sub_label', 255)->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('style', 50)->default('gradient-red');
            $table->string('url', 500)->nullable();
            $table->unsignedBigInteger('affiliate_link_id')->nullable();
            $table->enum('position', ['inline', 'sidebar', 'sticky_bottom', 'popup', 'after_paragraph'])->default('inline');
            $table->integer('show_after_paragraph')->nullable();
            $table->char('variant', 1)->default('A');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('display_conditions')->nullable();
            $table->timestamps();

            $table->foreign('affiliate_link_id')->references('id')->on('blog_affiliate_links')->nullOnDelete();
        });

        /* ──────────────────────────── AI Tasks ──────────────────────────── */
        Schema::create('blog_ai_tasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('type', [
                'generate_article', 'summarize', 'rewrite', 'research',
                'news_fetch', 'seo_analyze', 'keyword_suggest', 'translate',
            ]);
            $table->string('title', 255)->nullable();
            $table->text('prompt')->nullable();
            $table->json('input_data')->nullable();
            $table->longText('output_data')->nullable();
            $table->string('provider', 50)->nullable();
            $table->string('model', 100)->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->unsignedBigInteger('post_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->unsignedInteger('tokens_input')->default(0);
            $table->unsignedInteger('tokens_output')->default(0);
            $table->decimal('cost_usd', 8, 6)->default(0);
            $table->unsignedInteger('processing_time_ms')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('type');
            $table->index('admin_id');
            $table->index('created_at');

            $table->foreign('post_id')->references('id')->on('blog_posts')->nullOnDelete();
        });

        /* ──────────────────────────── News Sources ──────────────────────────── */
        Schema::create('blog_news_sources', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->string('url', 500);
            $table->string('feed_url', 500)->nullable();
            $table->string('feed_type', 20)->default('rss');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('language', 5)->default('th');
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_publish')->default(false);
            $table->unsignedInteger('fetch_interval_hours')->default(6);
            $table->timestamp('last_fetched_at')->nullable();
            $table->unsignedInteger('total_items_fetched')->default(0);
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('blog_categories')->nullOnDelete();
        });

        /* ──────────────────────────── News Items ──────────────────────────── */
        Schema::create('blog_news_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('source_id');
            $table->string('title', 500);
            $table->string('url', 500);
            $table->longText('original_content')->nullable();
            $table->text('ai_summary')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->enum('status', ['fetched', 'summarized', 'published', 'dismissed'])->default('fetched');
            $table->unsignedBigInteger('post_id')->nullable();
            $table->unsignedTinyInteger('relevance_score')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('fetched_at')->useCurrent();
            $table->timestamps();

            $table->index('source_id');
            $table->index('status');
            $table->index('category_id');
            $table->index('fetched_at');

            $table->foreign('source_id')->references('id')->on('blog_news_sources')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('blog_categories')->nullOnDelete();
            $table->foreign('post_id')->references('id')->on('blog_posts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_news_items');
        Schema::dropIfExists('blog_news_sources');
        Schema::dropIfExists('blog_ai_tasks');
        Schema::dropIfExists('blog_cta_buttons');
        Schema::dropIfExists('blog_affiliate_clicks');
        Schema::dropIfExists('blog_affiliate_links');
        Schema::dropIfExists('blog_post_tags');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_tags');
        Schema::dropIfExists('blog_categories');
    }
};
