<?php

declare(strict_types=1);

namespace App\Domain\Desktop\Data;

use App\Domain\Shared\Data\BaseData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The newest Kingtime for Mac release, as the homepage offers it.
 */
#[TypeScript]
class MacReleaseData extends BaseData
{
    public function __construct(
        public string $version,
        public string $download_url,
        public string $release_url,
        public string $published_on,
        public int $size_bytes,
    ) {}
}
