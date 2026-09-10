<?php

declare(strict_types=1);

namespace App\Domain\Desktop\Data;

use App\Domain\Shared\Data\BaseData;
use App\Domain\Time\Data\ProjectOptionData;
use Illuminate\Support\Collection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Everything the menu bar app shows, in one payload: who is signed in, the
 * projects to pick from and the timer that is running right now (if any).
 * The app polls this, so a timer started on the web or through MCP shows
 * up in the menu bar too.
 */
#[TypeScript]
class DesktopStateData extends BaseData
{
    /**
     * @param  Collection<int, ProjectOptionData>  $projects
     */
    public function __construct(
        public DesktopUserData $user,
        public Collection $projects,
        public ?DesktopTimerData $timer,
        public string $server_time,
    ) {}
}
