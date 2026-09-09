<?php

declare(strict_types=1);

namespace App\Domain\Moneybird\Models;

use App\Domain\User\Models\User;
use Database\Factories\MoneybirdConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's API token for one Moneybird administration, plus the optional
 * ids that are applied to every invoice line pushed with it. The token is
 * encrypted at rest and never sent to the browser.
 *
 * @property int $id
 * @property int $user_id
 * @property string $access_token
 * @property string $administration_id
 * @property string|null $administration_name
 * @property string|null $tax_rate_id
 * @property string|null $ledger_account_id
 * @property string|null $workflow_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class MoneybirdConnection extends Model
{
    /** @use HasFactory<MoneybirdConnectionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
