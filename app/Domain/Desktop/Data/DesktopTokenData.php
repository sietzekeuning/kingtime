<?php

declare(strict_types=1);

namespace App\Domain\Desktop\Data;

use App\Domain\Shared\Data\BaseData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the desktop app gets back from signing in: a Sanctum token it keeps
 * in the keychain, and the user it belongs to.
 */
#[TypeScript]
class DesktopTokenData extends BaseData
{
    public function __construct(
        public string $token,
        public DesktopUserData $user,
    ) {}
}
