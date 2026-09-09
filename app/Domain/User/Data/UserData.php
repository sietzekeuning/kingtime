<?php

declare(strict_types=1);

namespace App\Domain\User\Data;

use App\Domain\Shared\Data\BaseData;
use App\Domain\User\Models\User;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class UserData extends BaseData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?int $harvest_id = null,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            harvest_id: $user->harvest_id,
        );
    }
}
