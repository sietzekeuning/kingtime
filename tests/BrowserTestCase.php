<?php

declare(strict_types=1);

namespace Tests;

/**
 * Base for tests/Browser: the page is served to a real Chromium, so the
 * Vite manifest in public/build has to be present (run `npm run build`).
 */
abstract class BrowserTestCase extends TestCase
{
    protected function rendersWithoutVite(): bool
    {
        return false;
    }
}
