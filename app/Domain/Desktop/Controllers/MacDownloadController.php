<?php

declare(strict_types=1);

namespace App\Domain\Desktop\Controllers;

use App\Domain\Desktop\Actions\GetLatestMacReleaseAction;
use Illuminate\Http\RedirectResponse;

/**
 * kingtime.nl/download/mac: a stable link that always lands on the newest
 * disk image, or on the releases page when GitHub is not answering.
 */
class MacDownloadController
{
    public function __invoke(GetLatestMacReleaseAction $latestRelease): RedirectResponse
    {
        $release = $latestRelease->handle();

        $url = $release !== null
            ? $release->download_url
            : sprintf('https://github.com/%s/releases/latest', config('kingtime.mac.repository'));

        return redirect()->away($url);
    }
}
