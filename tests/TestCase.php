<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pages render through app.blade.php, which asks Vite for the page chunk.
        // The suite must not depend on a production build being present.
        if ($this->rendersWithoutVite()) {
            $this->withoutVite();
        }
    }

    /**
     * Browser tests load the real page in Chromium, so they need the built
     * assets and override this.
     */
    protected function rendersWithoutVite(): bool
    {
        return true;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
