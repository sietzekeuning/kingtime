<?php

declare(strict_types=1);

namespace App\Domain\Desktop\Actions;

use App\Domain\Desktop\Data\MacReleaseData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The newest release of the Mac app, read from GitHub and cached for an
 * hour. Null when GitHub cannot be reached or nothing is released yet; the
 * homepage then links the releases page instead of a disk image.
 *
 * The cache holds a plain array, not the DTO: an object serialised by one
 * release and read by the next came back as __PHP_Incomplete_Class.
 */
class GetLatestMacReleaseAction
{
    public function handle(): ?MacReleaseData
    {
        /** @var array<string, mixed>|null $release */
        $release = Cache::remember('kingtime.mac.latest-release.v2', now()->addHour(), fn () => $this->fetch()?->toArray());

        return is_array($release) ? MacReleaseData::from($release) : null;
    }

    private function fetch(): ?MacReleaseData
    {
        $repository = config('kingtime.mac.repository');

        try {
            $response = Http::acceptJson()
                ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
                ->timeout(5)
                ->get("https://api.github.com/repos/{$repository}/releases/latest");
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        /** @var array{tag_name?: string, html_url?: string, published_at?: string, assets?: array<int, array{name: string, browser_download_url: string, size: int}>} $release */
        $release = $response->json();

        $asset = collect($release['assets'] ?? [])->first(fn (array $asset) => str_ends_with($asset['name'], '.dmg'));

        if ($asset === null || ! isset($release['tag_name'], $release['html_url'], $release['published_at'])) {
            return null;
        }

        return new MacReleaseData(
            version: ltrim($release['tag_name'], 'v'),
            download_url: $asset['browser_download_url'],
            release_url: $release['html_url'],
            published_on: Carbon::parse($release['published_at'])->toDateString(),
            size_bytes: $asset['size'],
        );
    }
}
