<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tasks are gone: hours are logged on a project and billed at the project's
 * rate. Before the column goes, entries that had a task but no notes get the
 * task name as their notes so that information survives. The `bill_by`
 * column (project/task/none) is replaced by `is_billable` plus `hourly_rate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tasks') && Schema::hasColumn('time_entries', 'task_id')) {
            foreach (DB::table('tasks')->get(['id', 'name']) as $task) {
                DB::table('time_entries')
                    ->where('task_id', $task->id)
                    ->where(fn (Builder $query) => $query->whereNull('notes')->orWhere('notes', ''))
                    ->update(['notes' => $task->name]);
            }
        }

        if (Schema::hasColumn('time_entries', 'task_id')) {
            Schema::table('time_entries', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('task_id');
            });
        }

        if (Schema::hasColumn('invoice_lines', 'task_id')) {
            Schema::table('invoice_lines', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('task_id');
            });
        }

        Schema::dropIfExists('project_task');
        Schema::dropIfExists('tasks');

        if (Schema::hasColumn('projects', 'bill_by')) {
            Schema::table('projects', function (Blueprint $table): void {
                $table->dropColumn('bill_by');
            });
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('bill_by', 20)->default('project')->after('is_billable');
        });

        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_billable_by_default')->default(true);
            $table->decimal('default_hourly_rate', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('harvest_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('project_task', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_billable')->default(true);
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('harvest_id')->nullable()->unique();
            $table->timestamps();
            $table->unique(['project_id', 'task_id']);
        });

        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->foreignId('task_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
        });

        Schema::table('time_entries', function (Blueprint $table): void {
            $table->foreignId('task_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
        });
    }
};
