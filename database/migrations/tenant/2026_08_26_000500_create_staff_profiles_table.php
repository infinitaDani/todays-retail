<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('staff_profiles', function (Blueprint $table) {
            $table->id();
            // Core users are validated through account_user; tenant databases do not have cross-database FKs.
            $table->unsignedBigInteger('core_user_id')->unique();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('staff_profiles');
    }
};
