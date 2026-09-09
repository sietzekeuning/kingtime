<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('harvest_id')->nullable()->unique()->after('remember_token');
        });

        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('harvest_id')->nullable()->unique();
            $table->string('moneybird_contact_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('is_billable')->default(true);
            $table->string('bill_by', 20)->default('project');
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('budget_hours', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('color', 20)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('harvest_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
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

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('number')->nullable();
            $table->string('status', 20)->default('draft');
            $table->date('period_starts_on')->nullable();
            $table->date('period_ends_on')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('due_on')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->text('notes')->nullable();
            $table->string('moneybird_invoice_id')->nullable()->unique();
            $table->string('moneybird_url')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('amount', 12, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('time_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->date('spent_on')->index();
            $table->decimal('hours', 8, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_billable')->default(true);
            $table->boolean('is_billed')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_running')->default(false);
            $table->timestamp('timer_started_at')->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->unsignedBigInteger('harvest_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'spent_on']);
            $table->index(['is_billable', 'is_billed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('project_task');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('clients');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('harvest_id');
        });
    }
};
