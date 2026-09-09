<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every user gets their own clients and projects. Existing rows are handed
 * to the user whose hours and invoices are on them, and whatever is left to
 * the oldest account. Harvest ids are unique per user from now on, so two
 * users can import from two Harvest accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['harvest_id']);
            $table->unique(['user_id', 'harvest_id']);
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['harvest_id']);
            $table->unique(['user_id', 'harvest_id']);
        });

        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropUnique(['harvest_id']);
            $table->unique(['user_id', 'harvest_id']);
        });

        $this->assignOwners();

        Schema::table('clients', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'harvest_id']);
            $table->unique(['harvest_id']);
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'harvest_id']);
            $table->dropConstrainedForeignId('user_id');
            $table->unique(['harvest_id']);
        });

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'harvest_id']);
            $table->dropConstrainedForeignId('user_id');
            $table->unique(['harvest_id']);
        });
    }

    /**
     * A project belongs to whoever logged its hours, a client to whoever owns
     * its projects or invoices. Rows nobody claims go to the oldest user.
     */
    private function assignOwners(): void
    {
        $oldestUserId = DB::table('users')->orderBy('id')->value('id');

        if ($oldestUserId === null) {
            return;
        }

        $projectOwners = DB::table('time_entries')
            ->select('project_id', DB::raw('MIN(user_id) AS user_id'))
            ->groupBy('project_id')
            ->havingRaw('COUNT(DISTINCT user_id) = 1')
            ->get();

        foreach ($projectOwners as $owner) {
            DB::table('projects')->where('id', $owner->project_id)->whereNull('user_id')->update(['user_id' => $owner->user_id]);
        }

        $clientOwners = DB::table('projects')
            ->select('client_id', DB::raw('MIN(user_id) AS user_id'))
            ->whereNotNull('user_id')
            ->groupBy('client_id')
            ->havingRaw('COUNT(DISTINCT user_id) = 1')
            ->get();

        foreach ($clientOwners as $owner) {
            DB::table('clients')->where('id', $owner->client_id)->whereNull('user_id')->update(['user_id' => $owner->user_id]);
        }

        $invoiceOwners = DB::table('invoices')
            ->select('client_id', DB::raw('MIN(user_id) AS user_id'))
            ->whereNotNull('user_id')
            ->groupBy('client_id')
            ->havingRaw('COUNT(DISTINCT user_id) = 1')
            ->get();

        foreach ($invoiceOwners as $owner) {
            DB::table('clients')->where('id', $owner->client_id)->whereNull('user_id')->update(['user_id' => $owner->user_id]);
        }

        DB::table('clients')->whereNull('user_id')->update(['user_id' => $oldestUserId]);

        DB::table('projects')->whereNull('user_id')->update([
            'user_id' => DB::raw('(SELECT clients.user_id FROM clients WHERE clients.id = projects.client_id)'),
        ]);

        DB::table('invoices')->whereNull('user_id')->update([
            'user_id' => DB::raw('(SELECT clients.user_id FROM clients WHERE clients.id = invoices.client_id)'),
        ]);
    }
};
