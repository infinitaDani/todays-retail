<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::connection('tenant')->table('assignments')
            ->select('core_user_id', 'date', DB::raw('COUNT(*) as total'))
            ->groupBy('core_user_id', 'date')
            ->having('total', '>', 1)
            ->orderBy('date')
            ->get();
        if ($duplicates->isNotEmpty()) {
            $conflicts = $duplicates->map(fn (object $duplicate) => "core_user_id={$duplicate->core_user_id}, date={$duplicate->date}, assignments={$duplicate->total}")->implode('; ');
            throw new RuntimeException("No se puede crear el índice único de assignments. Corrige manualmente estos duplicados antes de migrar este tenant: {$conflicts}");
        }

        Schema::connection('tenant')->table('assignments', function (Blueprint $table) {
            $table->unique(['core_user_id', 'date'], 'assignments_user_date_unique');
        });
        Schema::connection('tenant')->table('checklists', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
        });
        Schema::connection('tenant')->create('checklist_shift', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained('checklists')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['checklist_id', 'shift_id']);
        });

        DB::connection('tenant')->table('checklists')->orderBy('id')->each(function (object $checklist): void {
            DB::connection('tenant')->table('checklist_shift')->insertOrIgnore(['checklist_id' => $checklist->id, 'shift_id' => $checklist->shift_id, 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('checklist_shift');
        Schema::connection('tenant')->table('checklists', fn (Blueprint $table) => $table->dropColumn('description'));
        Schema::connection('tenant')->table('assignments', fn (Blueprint $table) => $table->dropUnique('assignments_user_date_unique'));
    }
};
