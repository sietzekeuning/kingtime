<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Models;

use App\Domain\User\Models\User;
use Database\Factories\HarvestConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's personal access token for Harvest. The token is encrypted at
 * rest and never leaves the server; the page only sees the account it
 * belongs to.
 *
 * @property int $id
 * @property int $user_id
 * @property string $account_id
 * @property string $access_token
 * @property int|null $harvest_user_id
 * @property string|null $account_name
 * @property string|null $account_email
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class HarvestConnection extends Model
{
    /** @use HasFactory<HarvestConnectionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'harvest_user_id' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
