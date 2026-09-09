<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\BrowserTestCase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(BrowserTestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Browser');

/*
| Playwright's default of 5 seconds is tight on the CI runner, where the
| browser suite shares two cores with the rest of the workflow: a click
| that waits for a dialog or an Inertia visit would fail on load, not on a
| bug. A broken page still fails, only later.
*/
pest()->browser()->timeout(15_000);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert various things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every test file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Wait until a text is visible in the browser. The plugin's `assertSee`
 * looks once and `click` does not wait for the request that follows, so
 * after a click that ends in an Inertia visit or a toast this is the only
 * way not to race the server.
 *
 * `visit()` returns a PendingAwaitablePage that forwards every call to the
 * Webpage, hence no tighter type than object.
 */
function browserWaitUntilSee(object $page, string $text, float $seconds = 15): object
{
    $deadline = microtime(true) + $seconds;

    while (true) {
        try {
            return $page->assertSee($text);
        } catch (ExpectationFailedException $exception) {
            if (microtime(true) >= $deadline) {
                throw $exception;
            }

            $page->wait(0.2);
        }
    }
}

/**
 * Counterpart of browserWaitUntilSee: wait until a text has left the page.
 */
function browserWaitUntilDontSee(object $page, string $text, float $seconds = 15): object
{
    $deadline = microtime(true) + $seconds;

    while (true) {
        try {
            return $page->assertDontSee($text);
        } catch (ExpectationFailedException $exception) {
            if (microtime(true) >= $deadline) {
                throw $exception;
            }

            $page->wait(0.2);
        }
    }
}

/**
 * Confirm the open delete dialog. Its red button carries the same label as
 * the button that opened it, so it is targeted inside the dialog itself.
 */
function browserConfirmDelete(object $page, string $title): object
{
    browserWaitUntilSee($page, $title);

    return $page->click('[role="alertdialog"] button:last-of-type');
}
