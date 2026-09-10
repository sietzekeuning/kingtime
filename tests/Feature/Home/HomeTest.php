<?php

declare(strict_types=1);

use App\Domain\User\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

const HOME_GITHUB_LATEST = 'api.github.com/repos/sietzekeuning/kingtime-mac/releases/latest';

/**
 * @return array<string, mixed>
 */
function homeReleasePayload(): array
{
    return [
        'tag_name' => 'v1.2.0',
        'html_url' => 'https://github.com/sietzekeuning/kingtime-mac/releases/tag/v1.2.0',
        'published_at' => '2026-09-10T09:00:00Z',
        'assets' => [
            ['name' => 'Kingtime-1.2.0.zip', 'browser_download_url' => 'https://github.com/sietzekeuning/kingtime-mac/releases/download/v1.2.0/Kingtime-1.2.0.zip', 'size' => 3_000_000],
            ['name' => 'Kingtime-1.2.0.dmg', 'browser_download_url' => 'https://github.com/sietzekeuning/kingtime-mac/releases/download/v1.2.0/Kingtime-1.2.0.dmg', 'size' => 4_200_000],
        ],
    ];
}

beforeEach(function (): void {
    Cache::flush();
});

it('shows visitors the product page with the newest Mac release', function (): void {
    Http::fake([HOME_GITHUB_LATEST => Http::response(homeReleasePayload())]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('marketing/Home')
            ->where('canRegister', true)
            ->where('macRelease.version', '1.2.0')
            ->where('macRelease.download_url', 'https://github.com/sietzekeuning/kingtime-mac/releases/download/v1.2.0/Kingtime-1.2.0.dmg')
            ->where('macRelease.published_on', '2026-09-10')
            ->where('macRelease.size_bytes', 4_200_000)
            ->where('repositoryUrl', 'https://github.com/sietzekeuning/kingtime'));
});

it('keeps the page up when GitHub is down and says sign-up is closed once someone registered', function (): void {
    Http::fake([HOME_GITHUB_LATEST => Http::response(null, 503)]);
    User::factory()->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('marketing/Home')
            ->where('canRegister', false)
            ->where('macRelease', null));
});

it('shows a signed-in user the page as well, with their account in the shared props', function (): void {
    Http::fake([HOME_GITHUB_LATEST => Http::response(null, 503)]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('marketing/Home')
            ->where('auth.user.id', $user->id));
});

it('loads Google Tag Manager only when a container id is configured', function (): void {
    Http::fake([HOME_GITHUB_LATEST => Http::response(null, 503)]);

    config()->set('services.gtm.id', null);
    $this->get(route('home'))->assertOk()->assertDontSee('googletagmanager.com');

    config()->set('services.gtm.id', 'GTM-TEST123');
    $this->get(route('home'))->assertOk()
        ->assertSee('https://www.googletagmanager.com/gtm.js?id=', false)
        ->assertSee("'GTM-TEST123'", false)
        ->assertSee('https://www.googletagmanager.com/ns.html?id=GTM-TEST123', false);
});

it('caches the release lookup for an hour', function (): void {
    Http::fake([HOME_GITHUB_LATEST => Http::response(homeReleasePayload())]);

    $this->get(route('home'))->assertOk();
    $this->get(route('home'))->assertOk();

    Http::assertSentCount(1);
});

it('redirects the Mac download link to the newest disk image', function (): void {
    Http::fake([HOME_GITHUB_LATEST => Http::response(homeReleasePayload())]);

    $this->get(route('download.mac'))
        ->assertRedirect('https://github.com/sietzekeuning/kingtime-mac/releases/download/v1.2.0/Kingtime-1.2.0.dmg');
});

it('falls back to the releases page when there is no release to link', function (): void {
    Http::fake([HOME_GITHUB_LATEST => Http::response(['tag_name' => 'v1.0.0', 'assets' => []])]);

    $this->get(route('download.mac'))
        ->assertRedirect('https://github.com/sietzekeuning/kingtime-mac/releases/latest');
});

it('serves the Sparkle appcast from the app repository', function (): void {
    $this->get(route('download.appcast'))
        ->assertRedirect('https://raw.githubusercontent.com/sietzekeuning/kingtime-mac/main/appcast.xml');
});
