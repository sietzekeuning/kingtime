<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the invoice PDF prints as the sender: the user's business details.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('company_name')->nullable()->after('harvest_id');
            $table->text('company_address')->nullable()->after('company_name');
            $table->string('vat_number', 50)->nullable()->after('company_address');
            $table->string('coc_number', 50)->nullable()->after('vat_number');
            $table->string('iban', 50)->nullable()->after('coc_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['company_name', 'company_address', 'vat_number', 'coc_number', 'iban']);
        });
    }
};
