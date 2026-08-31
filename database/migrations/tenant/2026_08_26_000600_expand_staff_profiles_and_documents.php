<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('staff_profiles', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('core_user_id');
            $table->string('last_name', 100)->nullable()->after('first_name');
            $table->date('birth_date')->nullable()->after('last_name');
            $table->string('phone', 50)->nullable()->after('birth_date');
            $table->string('email', 255)->nullable()->after('phone');
            $table->string('emergency_contact_name', 150)->nullable()->after('email');
            $table->string('emergency_contact_relationship', 100)->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_phone', 50)->nullable()->after('emergency_contact_relationship');
            $table->enum('status', ['active', 'inactive'])->default('active')->after('branch_id');
        });

        Schema::connection('tenant')->create('staff_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained('staff_profiles')->cascadeOnDelete();
            $table->enum('type', ['cv', 'certificate', 'police_record']);
            $table->string('title', 200)->nullable();
            $table->string('disk', 50)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['staff_profile_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('staff_documents');
        Schema::connection('tenant')->table('staff_profiles', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'birth_date', 'phone', 'email', 'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone', 'status']);
        });
    }
};
