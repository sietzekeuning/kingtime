<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Harvest and Moneybird credentials move from the environment to one
 * connection per user. The credentials that are still in the environment
 * are handed to the oldest user account, together with the import history
 * and the invoices that were made with them.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private array $environment = [];

    public function up(): void
    {
        Schema::create('harvest_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('account_id');
            $table->text('access_token');
            $table->unsignedBigInteger('harvest_user_id')->nullable();
            $table->string('account_name')->nullable();
            $table->string('account_email')->nullable();
            $table->timestamps();
        });

        Schema::create('moneybird_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('access_token');
            $table->string('administration_id');
            $table->string('administration_name')->nullable();
            $table->string('tax_rate_id')->nullable();
            $table->string('ledger_account_id')->nullable();
            $table->string('workflow_id')->nullable();
            $table->timestamps();
        });

        Schema::table('harvest_imports', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        $this->moveEnvironmentCredentialsToTheOldestUser();
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('harvest_imports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::dropIfExists('moneybird_connections');
        Schema::dropIfExists('harvest_connections');
    }

    /**
     * Reads the .env file itself rather than env(): on a server with a cached
     * configuration env() returns null for keys that no config file mentions.
     */
    private function moveEnvironmentCredentialsToTheOldestUser(): void
    {
        $userId = DB::table('users')->orderBy('id')->value('id');

        if ($userId === null) {
            return;
        }

        DB::table('harvest_imports')->whereNull('user_id')->update(['user_id' => $userId]);
        DB::table('invoices')->whereNull('user_id')->update(['user_id' => $userId]);

        $this->environment = $this->readEnvironmentFile();

        $harvestAccountId = $this->environmentValue('HARVEST_ACCOUNT_ID');
        $harvestToken = $this->environmentValue('HARVEST_ACCESS_TOKEN');

        if ($harvestAccountId !== '' && $harvestToken !== '') {
            DB::table('harvest_connections')->insert([
                'user_id' => $userId,
                'account_id' => $harvestAccountId,
                'access_token' => Crypt::encryptString($harvestToken),
                'harvest_user_id' => DB::table('users')->where('id', $userId)->value('harvest_id'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $moneybirdToken = $this->environmentValue('MONEYBIRD_ACCESS_TOKEN');
        $administrationId = $this->environmentValue('MONEYBIRD_ADMINISTRATION_ID');

        if ($moneybirdToken !== '' && $administrationId !== '') {
            DB::table('moneybird_connections')->insert([
                'user_id' => $userId,
                'access_token' => Crypt::encryptString($moneybirdToken),
                'administration_id' => $administrationId,
                'tax_rate_id' => $this->optionalEnv('MONEYBIRD_TAX_RATE_ID'),
                'ledger_account_id' => $this->optionalEnv('MONEYBIRD_LEDGER_ACCOUNT_ID'),
                'workflow_id' => $this->optionalEnv('MONEYBIRD_WORKFLOW_ID'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function optionalEnv(string $key): ?string
    {
        $value = $this->environmentValue($key);

        return $value === '' ? null : $value;
    }

    private function environmentValue(string $key): string
    {
        return trim((string) ($this->environment[$key] ?? ''));
    }

    /**
     * @return array<string, string>
     */
    private function readEnvironmentFile(): array
    {
        $path = App::environmentFilePath();

        if (! is_file($path)) {
            return [];
        }

        /** @var array<string, string> $values */
        $values = Dotenv::parse((string) file_get_contents($path));

        return $values;
    }
};
