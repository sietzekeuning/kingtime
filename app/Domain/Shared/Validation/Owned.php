<?php

declare(strict_types=1);

namespace App\Domain\Shared\Validation;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Validation rules for ids that must point at a row of the authenticated
 * user. A plain `exists` would accept another user's client or project.
 */
final class Owned
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('user_id', auth()->id());
    }
}
