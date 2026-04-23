<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| resolution_queue table
|--------------------------------------------------------------------------
| Stores records of ConsumeCouponJob permanent failures that need
| manual review by the ops team.
|
| When ConsumeCouponJob fails all retries, FailedJobHandler writes a row
| here so an admin can verify whether the order was fulfilled and
| manually re-dispatch or cancel accordingly.
|
| Run: php artisan migrate
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resolution_queue', function (Blueprint $table) {
            $table->id();
            $table->string('type');               // e.g. coupon_consume_failure
            $table->json('payload');              // coupon_id, user_id, order_id, error
            $table->boolean('resolved')->default(false);
            $table->text('notes')->nullable();    // ops team can add resolution notes
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'resolved']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resolution_queue');
    }
};