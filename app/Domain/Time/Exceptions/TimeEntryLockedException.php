<?php

declare(strict_types=1);

namespace App\Domain\Time\Exceptions;

use App\Domain\Time\Models\TimeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when an action tries to change or delete an entry that is billed or
 * locked. Those entries back an invoice, so they are frozen.
 */
class TimeEntryLockedException extends RuntimeException
{
    public static function for(TimeEntry $entry): self
    {
        $reason = $entry->is_billed ? 'has been billed' : 'is locked';

        return new self("Time entry #{$entry->id} {$reason} and can no longer be changed.");
    }

    /**
     * Inertia requests land back on the page they came from with an error
     * toast; the actions themselves stay free of HTTP concerns.
     */
    public function render(Request $request): ?RedirectResponse
    {
        if ($request->expectsJson() && ! $request->inertia()) {
            return null;
        }

        return back()->with('toast', ['type' => 'error', 'message' => $this->getMessage()]);
    }
}
