<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type');          // percentage | fixed | free_shipping
            $table->decimal('value', 10, 2);
            $table->timestamps();
        });
 
        // ── coupon_settings: versioned rule snapshots ────────────────────────
        Schema::create('coupon_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->boolean('is_active')->default(true);
 
            $table->unsignedInteger('global_usage_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->decimal('min_cart_value', 10, 2)->nullable();
            $table->boolean('first_time_user_only')->default(false);
            $table->json('allowed_categories')->nullable();    // array of category IDs
            $table->timestamp('active_from')->nullable();
            $table->timestamp('active_until')->nullable();
            $table->timestamp('expires_at')->nullable();
 
            $table->timestamps();
 
            $table->unique(['coupon_id', 'version']);
            $table->index(['coupon_id', 'is_active']);
        });
 
        // ── coupon_usages: permanent consumption records ──────────────────────
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');             // no FK — users table managed separately
            $table->unsignedBigInteger('order_id')->nullable(); // no FK — orders table may not exist yet
            $table->string('status');                          // consumed | released
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
 
            $table->index('user_id');
            $table->index('order_id');
            $table->unique(['coupon_id', 'user_id', 'order_id']);
            $table->index(['coupon_id', 'status']);
            $table->index(['coupon_id', 'user_id', 'status']);
        });
 
        // ── coupon_events: full lifecycle audit log ───────────────────────────
        Schema::create('coupon_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');             // no FK — users table managed separately
            $table->string('event');                           // validated | reserved | consumed | released | failed
            $table->json('payload');                           // rule_version, cart_total, reason, etc.
            $table->string('coupon_key')->unique();       // prevents duplicate event rows on retry
            $table->timestamp('occurred_at');
            $table->timestamps();
 
            $table->index('user_id');
            $table->index(['coupon_id', 'event']);
            $table->index(['coupon_id', 'occurred_at']);
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('coupon_events');
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupon_settings');
        Schema::dropIfExists('coupons');
    }
};
