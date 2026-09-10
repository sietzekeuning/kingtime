<?php

declare(strict_types=1);

namespace App\Domain\Desktop\Data;

use App\Domain\Shared\Data\BaseData;
use App\Domain\User\Models\User;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The account the desktop app is signed in as: just enough to greet the
 * user and label the token in Settings > API tokens.
 */
#[TypeScript]
class DesktopUserData extends BaseData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
        );
    }
}
