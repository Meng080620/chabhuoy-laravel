<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table): void {
            // Proof-of-delivery photo captured at the delivered transition
            // (6amMart parity: OTP + photo). Nullable — only set on delivery,
            // and only required when config('delivery.proof_photo_required').
            $table->string('proof_photo_path')->nullable()->after('otp');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table): void {
            $table->dropColumn('proof_photo_path');
        });
    }
};
